<?php

namespace App\Controllers\QC;

use App\Controllers\BaseController;

class QCController extends BaseController
{
    /**
     * Get the process ID for QC / Final Inspection.
     * Tries multiple possible names.
     */
    private function getQcProcessId($db): int
    {
        $names = ['FINAL INSPECTION', 'Final Inspection', 'Final Inspection / QC', 'QC'];
        foreach ($names as $name) {
            $row = $db->table('production_processes')
                ->select('id')
                ->where('process_name', $name)
                ->get()->getRowArray();
            if ($row) return (int)$row['id'];
        }
        return 0;
    }

    /**
     * Get the process ID for Finished Good.
     */
    private function getFgProcessId($db): int
    {
        $names = ['FINISHED GOOD', 'Finished Good', 'FG'];
        foreach ($names as $name) {
            $row = $db->table('production_processes')
                ->select('id')
                ->where('process_name', $name)
                ->get()->getRowArray();
            if ($row) return (int)$row['id'];
        }
        return 0;
    }

    /**
     * Get items that have been scheduled for QC inspection today.
     */
    private function getScheduledItemsForQc($db, string $date): array
    {
        if (!$db->tableExists('qc_schedules')) return [];

        $schedules = $db->table('qc_schedules qs')
            ->select('qs.id as schedule_id, qs.product_id, qs.source_process_id, qs.qty_plan, qs.qty_inspected,
                      p.part_no, p.part_name, pp.process_name as source_process_name')
            ->join('products p', 'p.id = qs.product_id', 'left')
            ->join('production_processes pp', 'pp.id = qs.source_process_id', 'left')
            ->where('qs.schedule_date', $date)
            ->where('qs.status !=', 'COMPLETED')
            ->orderBy('qs.created_at', 'DESC')
            ->get()->getResultArray();

        $items = [];
        foreach ($schedules as $sched) {
            $pid = (int)$sched['product_id'];
            $remaining = (int)$sched['qty_plan'] - (int)$sched['qty_inspected'];
            
            if ($remaining <= 0) continue; // Safety check

            $items[] = [
                'schedule_id'       => $sched['schedule_id'],
                'product_id'        => $pid,
                'part_no'           => $sched['part_no'],
                'part_name'         => $sched['part_name'],
                'source_process_id' => $sched['source_process_id'],
                'from_process'      => $sched['source_process_name'] ?: 'Unknown Process',
                'production_date'   => $date,
                'total_stock'       => $remaining, // remaining in schedule
                'qty_plan'          => $sched['qty_plan'],
                'qty_inspected'     => $sched['qty_inspected']
            ];
        }

        return $items;
    }

    public function index()
    {
        $db = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        // Get IDs
        $qcProcessId = $this->getQcProcessId($db);
        $fgProcessId = $this->getFgProcessId($db);

        // Get scheduled items for QC
        $wips = $this->getScheduledItemsForQc($db, $date);

        $shifts = $db->table('shifts')
            ->select('id, shift_code, shift_name')
            ->where('is_active', 1)
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        $ngCategories = $db->table('ng_categories')
            ->orderBy('process_name')
            ->orderBy('ng_code')
            ->get()->getResultArray();

        // Get today's inspections to display what has been inspected
        $inspections = $db->table('qc_inspections qc')
            ->select('qc.*, p.part_no, p.part_name, s.shift_name, pp.process_name')
            ->join('products p', 'p.id = qc.product_id')
            ->join('shifts s', 's.id = qc.shift_id', 'left')
            ->join('production_processes pp', 'pp.id = qc.source_process_id', 'left')
            ->where('qc.production_date', $date)
            ->orderBy('qc.created_at', 'DESC')
            ->get()->getResultArray();

        // Map NG details to inspections
        $inspectionNgs = [];
        if (!empty($inspections)) {
            $ids = array_column($inspections, 'id');
            $ngRows = $db->table('qc_inspection_ngs qn')
                ->select('qn.*, nc.ng_code, nc.ng_name')
                ->join('ng_categories nc', 'nc.id = qn.ng_category_id', 'left')
                ->whereIn('qn.qc_inspection_id', $ids)
                ->get()->getResultArray();

            foreach ($ngRows as $ng) {
                $inspectionNgs[$ng['qc_inspection_id']][] = $ng;
            }
        }

        return view('qc/index', [
            'date'          => $date,
            'wips'          => $wips,
            'shifts'        => $shifts,
            'ngCategories'  => $ngCategories,
            'qcProcessId'   => $qcProcessId,
            'fgProcessId'   => $fgProcessId,
            'inspections'   => $inspections,
            'inspectionNgs' => $inspectionNgs
        ]);
    }

    public function store()
    {
        $db = db_connect();

        $date       = $this->request->getPost('production_date');
        $productId  = (int)$this->request->getPost('product_id');
        $scheduleId = (int)$this->request->getPost('schedule_id');
        $qtyOk      = (int)$this->request->getPost('qty_ok');
        $inspectedBy = session()->get('fullname') ?? 'QC Operator';

        if (empty($date) || empty($productId) || empty($scheduleId)) {
            return redirect()->back()->with('error', 'Data input tidak lengkap.');
        }

        $qcProcessId = $this->getQcProcessId($db);
        $fgProcessId = $this->getFgProcessId($db);

        $schedule = $db->table('qc_schedules')->where('id', $scheduleId)->get()->getRowArray();
        if (!$schedule) {
            return redirect()->back()->with('error', 'QC Schedule tidak ditemukan.');
        }
        $sourceProcessId = (int)$schedule['source_process_id'];
        $shiftId = (int)$schedule['shift_id'];

        $db->transBegin();
        try {
            // 1. Save main inspection record
            $db->table('qc_inspections')->insert([
                'qc_schedule_id'  => $scheduleId,
                'source_process_id'=> $sourceProcessId,
                'production_date' => $date,
                'shift_id'        => $shiftId,
                'product_id'      => $productId,
                'qty_in'          => $qtyOk, // will recalculate
                'qty_ok'          => $qtyOk,
                'qty_ng'          => 0, // will calculate below
                'inspected_by'    => $inspectedBy,
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s')
            ]);
            $inspectionId = $db->insertID();

            $totalNg = 0;

            // 2. Handle NG entries
            $ngCats = $this->request->getPost('ng_category_id');
            $ngQtys = $this->request->getPost('ng_qty');
            $images = $this->request->getFiles();

            if (!empty($ngCats) && is_array($ngCats)) {
                $ngImages = $images['ng_image'] ?? [];

                foreach ($ngCats as $index => $catId) {
                    if (empty($catId) || empty($ngQtys[$index]) || $ngQtys[$index] <= 0) {
                        continue;
                    }

                    $catId = (int)$catId;
                    $q = (int)$ngQtys[$index];
                    $totalNg += $q;

                    $imagePath = null;
                    if (isset($ngImages[$index]) && $ngImages[$index]->isValid() && !$ngImages[$index]->hasMoved()) {
                        $newName = $ngImages[$index]->getRandomName();
                        $ngImages[$index]->move(FCPATH . 'uploads/qc_images', $newName);
                        $imagePath = 'uploads/qc_images/' . $newName;
                    }

                    $db->table('qc_inspection_ngs')->insert([
                        'qc_inspection_id' => $inspectionId,
                        'ng_category_id'   => $catId,
                        'qty'              => $q,
                        'image_path'       => $imagePath,
                        'created_at'       => date('Y-m-d H:i:s')
                    ]);
                }
            }

            $totalInspected = $qtyOk + $totalNg;

            // 3. Update total NG & qty_in on the inspection record
            $db->table('qc_inspections')->where('id', $inspectionId)->update([
                'qty_ng' => $totalNg,
                'qty_in' => $totalInspected
            ]);

            // 4. Deduct stock from WIP records
            //    Source: WIP lying at source_process_id
            if ($sourceProcessId > 0 && $db->tableExists('production_wip')) {
                $wipDateCol = $db->fieldExists('wip_date', 'production_wip') ? 'wip_date' : 'production_date';

                $wipRows = $db->table('production_wip')
                    ->where('to_process_id', $sourceProcessId)
                    ->where('product_id', $productId)
                    ->where('stock >', 0)
                    ->where("status !=", 'DONE')
                    ->where("$wipDateCol <=", $date)
                    ->orderBy($wipDateCol, 'ASC')
                    ->orderBy('id', 'ASC')
                    ->get()->getResultArray();

                $remaining = $totalInspected;

                foreach ($wipRows as $wip) {
                    if ($remaining <= 0) break;

                    $wipStock = (int)$wip['stock'];
                    $deduct = min($remaining, $wipStock);
                    $newStock = $wipStock - $deduct;
                    $newQtyOut = (int)($wip['qty_out'] ?? 0) + $deduct;

                    $upd = [
                        'stock'   => $newStock,
                        'qty_out' => $newQtyOut,
                        'status'  => ($newStock <= 0) ? 'DONE' : 'WAITING',
                    ];

                    $db->table('production_wip')->where('id', (int)$wip['id'])->update($upd);
                    $remaining -= $deduct;
                }
            }

            // Update qc_schedules qty_inspected
            $newInspected = (int)$schedule['qty_inspected'] + $totalInspected;
            $status = ($newInspected >= (int)$schedule['qty_plan']) ? 'COMPLETED' : 'IN_PROGRESS';
            $db->table('qc_schedules')->where('id', $scheduleId)->update([
                'qty_inspected' => $newInspected,
                'status'        => $status,
                'updated_at'    => date('Y-m-d H:i:s')
            ]);

            // 5. Forward PASS (OK) qty to Finished Good stock
            if ($qtyOk > 0 && $fgProcessId > 0 && $qcProcessId > 0 && $db->tableExists('production_wip')) {
                $wipDateCol = $db->fieldExists('wip_date', 'production_wip') ? 'wip_date' : 'production_date';
                $now = date('Y-m-d H:i:s');

                // Check if a WIP record for FG already exists for this product on this date
                $fgKey = [
                    $wipDateCol       => $date,
                    'product_id'      => $productId,
                    'from_process_id' => $qcProcessId,
                    'to_process_id'   => $fgProcessId,
                ];

                $existFg = $db->table('production_wip')->where($fgKey)->get()->getRowArray();

                if ($existFg) {
                    // Accumulate stock
                    $newFgStock = (int)($existFg['stock'] ?? 0) + $qtyOk;
                    $newFgQtyIn = (int)($existFg['qty_in'] ?? 0) + $qtyOk;
                    $newFgQty   = (int)($existFg['qty'] ?? 0) + $qtyOk;

                    $fgUpd = [
                        'stock'  => $newFgStock,
                        'qty_in' => $newFgQtyIn,
                        'qty'    => $newFgQty,
                        'status' => 'WAITING',
                    ];

                    $db->table('production_wip')->where('id', (int)$existFg['id'])->update($fgUpd);
                } else {
                    // Create new FG WIP record
                    $fgIns = $fgKey + [
                        'qty'          => $qtyOk,
                        'qty_in'       => $qtyOk,
                        'qty_out'      => 0,
                        'stock'        => $qtyOk,
                        'source_table' => 'qc_inspections',
                        'source_id'    => $inspectionId,
                        'status'       => 'WAITING',
                        'created_at'   => $now,
                    ];

                    $db->table('production_wip')->insert($fgIns);
                }
            }

            if ($db->transStatus() === false) {
                throw new \Exception('Database error saat menyimpan hasil QC.');
            }
            $db->transCommit();

            return redirect()->back()->with('success', 'Hasil QC berhasil disimpan. ' . $qtyOk . ' pcs PASS masuk ke stock Finished Good.');

        } catch (\Exception $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
