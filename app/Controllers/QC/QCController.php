<?php

namespace App\Controllers\QC;

use App\Controllers\BaseController;
use CodeIgniter\Files\File;

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
     * For a given product, find the process that comes right before the QC/FI process.
     * This is the "last production step" before inspection.
     */
    private function getProcessBeforeQc($db, int $productId, int $qcProcessId): int
    {
        $flows = $db->table('product_process_flows')
            ->select('process_id')
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->orderBy('sequence', 'ASC')
            ->get()->getResultArray();

        $prev = 0;
        foreach ($flows as $f) {
            if ((int)$f['process_id'] === $qcProcessId) {
                return $prev; // return the process right before QC
            }
            $prev = (int)$f['process_id'];
        }
        return $prev; // fallback: return last process if QC not found in flow
    }

    /**
     * Collect all WIP items ready for QC inspection from two sources:
     *   1) Explicitly transferred to QC: to_process_id = qcProcessId (FI)
     *   2) Stock sitting at the last production step before FI (fallback when
     *      "Finish Shift" transfer hasn't been done yet)
     */
    private function getWipItemsForQc($db, string $date, int $qcProcessId): array
    {
        if (!$db->tableExists('production_wip')) return [];

        $wipDateCol = $db->fieldExists('wip_date', 'production_wip') ? 'wip_date' : 'production_date';

        // --- Source 1: WIP explicitly destined for QC/FI ---
        $rows = $db->table('production_wip pw')
            ->select('pw.id AS wip_id, pw.product_id, pw.from_process_id, pw.to_process_id,
                      pw.qty, pw.qty_in, pw.qty_out, pw.stock, pw.status,
                      pw.' . $wipDateCol . ' AS production_date,
                      p.part_no, p.part_name,
                      pr_from.process_name AS from_process_name,
                      pr_to.process_name AS to_process_name')
            ->join('products p', 'p.id = pw.product_id')
            ->join('production_processes pr_from', 'pr_from.id = pw.from_process_id', 'left')
            ->join('production_processes pr_to', 'pr_to.id = pw.to_process_id', 'left')
            ->where('pw.to_process_id', $qcProcessId)
            ->where('pw.stock >', 0)
            ->where("pw.status !=", 'DONE')
            ->where("pw.$wipDateCol <=", $date)
            ->orderBy("pw.$wipDateCol", 'DESC')
            ->orderBy('pw.id', 'DESC')
            ->get()->getResultArray();

        // --- Source 2: Stock at the last production step before FI ---
        // For each product, determine which process is right before FI,
        // then fetch WIP where to_process_id = that process and stock > 0
        $products = $db->table('products')->where('is_active', 1)->get()->getResultArray();
        $seenProducts = [];

        foreach ($products as $p) {
            $pid = (int)$p['id'];
            $lastProdProcessId = $this->getProcessBeforeQc($db, $pid, $qcProcessId);
            if ($lastProdProcessId <= 0) continue;

            // Find WIP records where items are AT the last production step
            // (to_process_id = last process before FI, meaning stock arrived there)
            $fallbackRows = $db->table('production_wip pw')
                ->select('pw.id AS wip_id, pw.product_id, pw.from_process_id, pw.to_process_id,
                          pw.qty, pw.qty_in, pw.qty_out, pw.stock, pw.status,
                          pw.' . $wipDateCol . ' AS production_date,
                          p.part_no, p.part_name,
                          pr_from.process_name AS from_process_name,
                          pr_to.process_name AS to_process_name')
                ->join('products p', 'p.id = pw.product_id')
                ->join('production_processes pr_from', 'pr_from.id = pw.from_process_id', 'left')
                ->join('production_processes pr_to', 'pr_to.id = pw.to_process_id', 'left')
                ->where('pw.product_id', $pid)
                ->where('pw.to_process_id', $lastProdProcessId)
                ->where('pw.stock >', 0)
                ->where("pw.status !=", 'DONE')
                ->where("pw.$wipDateCol <=", $date)
                ->orderBy("pw.$wipDateCol", 'DESC')
                ->orderBy('pw.id', 'DESC')
                ->get()->getResultArray();

            $rows = array_merge($rows, $fallbackRows);
        }

        // Aggregate by product (multiple WIP rows for same product)
        $items = [];
        foreach ($rows as $r) {
            $pid = (int)$r['product_id'];
            if (!isset($items[$pid])) {
                $fromName = $r['from_process_name'] ?: 'Production';
                $toName   = $r['to_process_name'] ?: '';
                $label    = $fromName;
                if ($toName && (int)$r['to_process_id'] !== $qcProcessId) {
                    $label = $toName . ' (output)';
                }

                $items[$pid] = [
                    'wip_ids'         => [],
                    'product_id'      => $pid,
                    'part_no'         => $r['part_no'],
                    'part_name'       => $r['part_name'],
                    'from_process'    => $label,
                    'production_date' => $r['production_date'],
                    'total_stock'     => 0,
                    'total_qty_in'    => 0,
                    'total_qty_out'   => 0,
                ];
            }

            $items[$pid]['wip_ids'][] = [
                'id'    => (int)$r['wip_id'],
                'stock' => (int)$r['stock'],
            ];
            $items[$pid]['total_stock']   += (int)$r['stock'];
            $items[$pid]['total_qty_in']  += (int)($r['qty_in'] ?? $r['qty'] ?? 0);
            $items[$pid]['total_qty_out'] += (int)($r['qty_out'] ?? 0);
        }

        return array_values($items);
    }

    public function index()
    {
        $db = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        // Get IDs
        $qcProcessId = $this->getQcProcessId($db);
        $fgProcessId = $this->getFgProcessId($db);

        // Get WIP items destined for QC/FI with stock > 0
        $wips = [];
        if ($qcProcessId > 0) {
            $wips = $this->getWipItemsForQc($db, $date, $qcProcessId);
        }

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
            ->select('qc.*, p.part_no, p.part_name, s.shift_name')
            ->join('products p', 'p.id = qc.product_id')
            ->join('shifts s', 's.id = qc.shift_id', 'left')
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
        $shiftId    = (int)$this->request->getPost('shift_id');
        $productId  = (int)$this->request->getPost('product_id');
        $qtyOk      = (int)$this->request->getPost('qty_ok');
        $inspectedBy = session()->get('fullname') ?? 'QC Operator';

        if (empty($date) || empty($shiftId) || empty($productId)) {
            return redirect()->back()->with('error', 'Data input tidak lengkap.');
        }

        $qcProcessId = $this->getQcProcessId($db);
        $fgProcessId = $this->getFgProcessId($db);

        $db->transBegin();
        try {
            // 1. Save main inspection record
            $db->table('qc_inspections')->insert([
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
            //    First try: WIP with to_process_id = QC (explicit transfers)
            //    Second try: WIP at the last production step before QC (fallback)
            if ($qcProcessId > 0 && $db->tableExists('production_wip')) {
                $wipDateCol = $db->fieldExists('wip_date', 'production_wip') ? 'wip_date' : 'production_date';

                // Source 1: WIP destined for QC
                $wipRows = $db->table('production_wip')
                    ->where('to_process_id', $qcProcessId)
                    ->where('product_id', $productId)
                    ->where('stock >', 0)
                    ->where("status !=", 'DONE')
                    ->where("$wipDateCol <=", $date)
                    ->orderBy($wipDateCol, 'ASC')
                    ->orderBy('id', 'ASC')
                    ->get()->getResultArray();

                // Source 2: WIP at the last production step before QC
                $lastProdProcessId = $this->getProcessBeforeQc($db, $productId, $qcProcessId);
                if ($lastProdProcessId > 0) {
                    $fallbackWip = $db->table('production_wip')
                        ->where('to_process_id', $lastProdProcessId)
                        ->where('product_id', $productId)
                        ->where('stock >', 0)
                        ->where("status !=", 'DONE')
                        ->where("$wipDateCol <=", $date)
                        ->orderBy($wipDateCol, 'ASC')
                        ->orderBy('id', 'ASC')
                        ->get()->getResultArray();
                    $wipRows = array_merge($wipRows, $fallbackWip);
                }

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
