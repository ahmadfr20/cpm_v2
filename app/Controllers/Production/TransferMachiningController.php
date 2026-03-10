<?php

namespace App\Controllers\Production;

use App\Controllers\BaseController;

class TransferMachiningController extends BaseController
{
    // Fungsi dinamis untuk mencari ID Proses Machining
    private function getProcessIdMachining($db): int
    {
        $row = $db->table('production_processes')
            ->select('id')
            ->where('process_name', 'Machining')
            ->get()
            ->getRowArray();

        if (!$row) throw new \Exception('Process "Machining" belum ada di master production_processes');
        return (int)$row['id'];
    }

    public function index()
    {
        $db = db_connect();
        $machiningProcessId = $this->getProcessIdMachining($db);

        $shifts = $db->table('shifts')->where('is_active', 1)->get()->getResultArray();
        $ngCategories = $db->table('ng_categories')->where('is_active', 1)->orderBy('ng_name', 'ASC')->get()->getResultArray();

        // 1. Ambil semua product yang aktif
        $products = $db->table('products')->where('is_active', 1)->get()->getResultArray();
        
        $eligibleTransfers = [];

        // 2. LOGIKA KETAT: Susun array alur produksi dan ambil TEPAT 1 index di belakang Machining
        foreach ($products as $p) {
            $productId = $p['id'];
            
            // Ambil alur lengkap untuk product ini, urut dari sequence terkecil ke terbesar
            $flows = $db->table('product_process_flows')
                        ->select('process_id, sequence')
                        ->where('product_id', $productId)
                        ->where('is_active', 1)
                        ->orderBy('sequence', 'ASC') // Kunci utama: Urutkan secara fisik
                        ->get()->getResultArray();
            
            $prevProcessId = null;
            
            // Cari posisi Machining di dalam urutan alur
            for ($i = 0; $i < count($flows); $i++) {
                if ((int)$flows[$i]['process_id'] === $machiningProcessId) {
                    // Jika Machining ada, ambil persis 1 langkah (index) sebelumnya
                    if ($i > 0) {
                        $prevProcessId = (int)$flows[$i - 1]['process_id'];
                    }
                    break; // Selesai pencarian untuk produk ini
                }
            }

            // Jika ada proses tepat sebelum Machining, cek stoknya
            if ($prevProcessId !== null) {
                // Cari data WIP (Running Balance) terakhir dari proses sebelumnya tersebut
                $wip = $db->table('production_wip w')
                          ->select('w.id as wip_id, w.stock, proc.process_name as prev_process_name')
                          ->join('production_processes proc', 'proc.id = w.to_process_id')
                          ->where('w.product_id', $productId)
                          ->where('w.to_process_id', $prevProcessId)
                          ->orderBy('w.id', 'DESC') // Pastikan mengambil saldo stok paling update
                          ->limit(1)
                          ->get()->getRowArray();
                
                // Jika stoknya lebih dari 0, masukkan ke tabel Transfer
                if ($wip && (int)$wip['stock'] > 0) {
                    $eligibleTransfers[] = [
                        'wip_id'            => $wip['wip_id'],
                        'product_id'        => $productId,
                        'prev_process_id'   => $prevProcessId,
                        'part_no'           => $p['part_no'],
                        'part_name'         => $p['part_name'],
                        'prev_process_name' => $wip['prev_process_name'],
                        'stock'             => (int)$wip['stock']
                    ];
                }
            }
        }

        // Urutkan abjad berdasarkan part_no
        usort($eligibleTransfers, function($a, $b) {
            return strcmp($a['part_no'], $b['part_no']);
        });

        // 3. Histori Transfer hari ini
        $history = $db->table('material_transactions mt')
                      ->select('mt.*, p.part_name, p.part_no, s.shift_name, pf.process_name as from_process')
                      ->join('products p', 'p.id = mt.product_id')
                      ->join('shifts s', 's.id = mt.shift_id')
                      ->join('production_processes pf', 'pf.id = mt.process_from', 'left')
                      ->where('mt.transaction_type', 'TRANSFER')
                      ->where('mt.process_to', $machiningProcessId)
                      ->orderBy('mt.created_at', 'DESC')
                      ->limit(20)
                      ->get()->getResultArray();

        return view('production/transfer_machining/index', [
            'eligibleTransfers' => $eligibleTransfers,
            'shifts'            => $shifts,
            'ngCategories'      => $ngCategories,
            'history'           => $history
        ]);
    }

    public function store()
    {
        $db = db_connect();
        $date      = $this->request->getPost('transaction_date');
        $shiftId   = (int)$this->request->getPost('shift_id');
        $transfers = $this->request->getPost('transfers');

        if (empty($date) || empty($shiftId) || empty($transfers)) {
            return redirect()->back()->with('error', 'Tanggal, Shift, dan Data Transfer tidak boleh kosong.');
        }

        $machiningProcessId = $this->getProcessIdMachining($db);
        $db->transBegin();

        try {
            $totalProcessed = 0;

            foreach ($transfers as $productId => $data) {
                $qtyOk = (int)($data['qty_ok'] ?? 0);
                
                $ngQtys = isset($data['ng']['qty']) ? array_map('intval', $data['ng']['qty']) : [];
                $totalNg = array_sum($ngQtys);
                $totalPull = $qtyOk + $totalNg;

                if ($totalPull <= 0) continue;

                $wipId = (int)$data['wip_id'];
                $prevProcessId = (int)$data['prev_process_id'];

                // 1. Kurangi Stok Proses Sebelumnya (Asal)
                $wipPrev = $db->table('production_wip')->where('id', $wipId)->get()->getRowArray();
                if (!$wipPrev || $wipPrev['stock'] < $totalPull) {
                    throw new \Exception("Stok asal tidak cukup untuk Part ID $productId.");
                }

                $db->table('production_wip')->where('id', $wipId)->update([
                    'qty_out' => $wipPrev['qty_out'] + $totalPull,
                    'stock'   => $wipPrev['stock'] - $totalPull
                ]);

                // 2. Tambah WIP di area Machining (HANYA FG / OK)
                if ($qtyOk > 0) {
                    // Cari stok Machining yang paling baru
                    $wipMachining = $db->table('production_wip')
                                       ->where('product_id', $productId)
                                       ->where('to_process_id', $machiningProcessId)
                                       ->orderBy('id', 'DESC')
                                       ->limit(1)
                                       ->get()->getRowArray();

                    if ($wipMachining) {
                        $db->table('production_wip')->where('id', $wipMachining['id'])->update([
                            'qty_in' => $wipMachining['qty_in'] + $qtyOk,
                            'stock'  => $wipMachining['stock'] + $qtyOk
                        ]);
                    } else {
                        $db->table('production_wip')->insert([
                            'production_date' => $date,
                            'product_id'      => $productId,
                            'from_process_id' => $prevProcessId,
                            'to_process_id'   => $machiningProcessId,
                            'qty_in'          => $qtyOk,
                            'qty_out'         => 0,
                            'stock'           => $qtyOk,
                            'status'          => 'WAITING'
                        ]);
                    }

                    // Catat Histori Material (FG)
                    $db->table('material_transactions')->insert([
                        'transaction_date' => $date,
                        'shift_id'         => $shiftId,
                        'product_id'       => $productId,
                        'process_from'     => $prevProcessId,
                        'process_to'       => $machiningProcessId,
                        'qty'              => $qtyOk,
                        'transaction_type' => 'TRANSFER'
                    ]);
                }

                // 3. Simpan Data NG ke tabel machining_transfer_ng
                if (!empty($data['ng']['category']) && $db->tableExists('machining_transfer_ng')) {
                    foreach ($data['ng']['category'] as $idx => $ngCatId) {
                        $ngQty = (int)($data['ng']['qty'][$idx] ?? 0);
                        if ($ngQty > 0 && $ngCatId != '') {
                            $db->table('machining_transfer_ng')->insert([
                                'transaction_date' => $date,
                                'shift_id'         => $shiftId,
                                'product_id'       => $productId,
                                'from_process_id'  => $prevProcessId,
                                'to_process_id'    => $machiningProcessId,
                                'ng_category_id'   => $ngCatId,
                                'qty'              => $ngQty
                            ]);
                        }
                    }
                }

                $totalProcessed++;
            }

            if ($totalProcessed === 0) throw new \Exception('Tidak ada data transfer dengan Qty lebih dari 0.');
            if ($db->transStatus() === false) throw new \Exception('Database Error.');

            $db->transCommit();
            return redirect()->to('/production/transfer-machining')->with('success', "Part berhasil ditransfer ke Machining.");

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}