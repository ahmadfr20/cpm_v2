<?php

namespace App\Controllers\StockOpname;

use App\Controllers\BaseController;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StockOpnameController extends BaseController
{
    // Mapping section text in Excel row 6 to production_processes ID
    // Supports multiple variants of section names
    protected $processMapping = [
        'CASTING PROD'   => 1, // Die Casting
        'PROD CASTING'   => 1, // Die Casting (variant)
        'DIE CASTING'    => 1,
        'PROD MACHINING' => 2, // Machining
        'MACHINING PROD' => 2, // Machining (variant)
        'MACHINING'      => 2,
        'PC STORE PPC'   => 12, // FINISHED GOOD
        'PC STORE'       => 12,
        'FINISHED GOOD'  => 12,
        'QC'             => 11, // FINAL INSPECTION
        'FINAL INSPECTION' => 11,
    ];

    private function getStoSumByDate($date)
    {
        $db = db_connect();
        $existing = $db->table('production_wip')
            ->where('production_date', $date)
            ->like('source_table', 'STOCK_OPNAME')
            ->get()->getResultArray();
            
        $stoData = [];
        foreach ($existing as $row) {
            $pid = $row['product_id'];
            $proc = $row['to_process_id'];
            $src = $row['source_table'];
            
            if (!isset($stoData[$pid])) $stoData[$pid] = [];
            if (!isset($stoData[$pid][$proc])) $stoData[$pid][$proc] = [];
            
            if (strpos($src, 'WIP') !== false) {
                $stoData[$pid][$proc]['wip'] = ($stoData[$pid][$proc]['wip'] ?? 0) + $row['qty_in'];
            } elseif (strpos($src, 'STORE') !== false) {
                $stoData[$pid][$proc]['store'] = ($stoData[$pid][$proc]['store'] ?? 0) + $row['stock'];
            } elseif (strpos($src, 'NG') !== false) {
                $stoData[$pid][$proc]['ng'] = ($stoData[$pid][$proc]['ng'] ?? 0) + $row['qty'];
            } elseif (strpos($src, 'SHIP') !== false) {
                $stoData[$pid][$proc]['ship'] = ($stoData[$pid][$proc]['ship'] ?? 0) + $row['qty_out'];
            }
        }
        return $stoData;
    }

    public function index()
    {
        $db = db_connect();
        
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        $stoData = $this->getStoSumByDate($date);
        
        $products = $db->table('products')->where('is_active', 1)->orderBy('part_name', 'ASC')->get()->getResultArray();
        $processes = $db->table('production_processes')->orderBy('id', 'ASC')->get()->getResultArray();

        return view('stock_opname/index', [
            'products'  => $products,
            'processes' => $processes,
            'stoData'   => $stoData,
            'date'      => $date
        ]);
    }

    public function create()
    {
        $db = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        
        $products = $db->table('products')->where('is_active', 1)->orderBy('part_name', 'ASC')->get()->getResultArray();
        $processes = $db->table('production_processes')->orderBy('id', 'ASC')->get()->getResultArray();
        $stoData = $this->getStoSumByDate($date);

        return view('stock_opname/create', [
            'products'  => $products,
            'processes' => $processes,
            'date'      => $date,
            'stoData'   => $stoData
        ]);
    }

    public function storeManual()
    {
        $db = db_connect();

        $date   = $this->request->getPost('production_date');
        $sto    = $this->request->getPost('sto'); // array: sto[product_id][process_id][category] = qty

        if (!$date) {
            return redirect()->back()->with('error', 'Tanggal harus diisi.');
        }

        if (empty($sto) || !is_array($sto)) {
            return redirect()->back()->with('error', 'Tidak ada data STO yang dikirim.');
        }

        $now        = date('Y-m-d H:i:s');
        $insertData = [];
        $processedProductIds = [];
        $shipDeliveries = [];
        $existingSto = $this->getStoSumByDate($date);

        foreach ($sto as $productId => $processes) {
            $productId = (int)$productId;
            if (!$productId) continue;

            foreach ($processes as $processId => $categories) {
                $processId = (int)$processId;
                if (!$processId) continue;

                $qtyWip   = isset($categories['wip'])   ? (float)$categories['wip']   : 0;
                $qtyStore = isset($categories['store']) ? (float)$categories['store'] : 0;
                $qtyNg    = isset($categories['ng'])    ? (float)$categories['ng']    : 0;
                $qtyShip  = isset($categories['ship'])  ? (float)$categories['ship']  : 0;

                $exWip   = $existingSto[$productId][$processId]['wip'] ?? 0;
                $exStore = $existingSto[$productId][$processId]['store'] ?? 0;
                $exNg    = $existingSto[$productId][$processId]['ng'] ?? 0;
                $exShip  = $existingSto[$productId][$processId]['ship'] ?? 0;

                $diffWip   = $qtyWip - $exWip;
                $diffStore = $qtyStore - $exStore;
                $diffNg    = $qtyNg - $exNg;
                $diffShip  = $qtyShip - $exShip;

                // Insert baris WIP jika diff != 0
                if ($diffWip != 0) {
                    $insertData[] = [
                        'production_date' => $date,
                        'product_id'      => $productId,
                        'from_process_id' => null,
                        'to_process_id'   => $processId,
                        'qty'             => 0,
                        'qty_in'          => $diffWip,
                        'qty_out'         => 0,
                        'stock'           => 0,
                        'source_table'    => 'STOCK_OPNAME_WIP',
                        'status'          => 'WAITING',
                        'created_at'      => $now,
                    ];
                    $processedProductIds[$productId] = true;
                }

                // Insert baris STORE jika diff != 0
                if ($diffStore != 0) {
                    $insertData[] = [
                        'production_date' => $date,
                        'product_id'      => $productId,
                        'from_process_id' => null,
                        'to_process_id'   => $processId,
                        'qty'             => 0,
                        'qty_in'          => 0,
                        'qty_out'         => 0,
                        'stock'           => $diffStore,
                        'source_table'    => 'STOCK_OPNAME_STORE',
                        'status'          => 'WAITING',
                        'created_at'      => $now,
                    ];
                    $processedProductIds[$productId] = true;
                }

                // Insert baris NG jika diff != 0
                if ($diffNg != 0) {
                    $insertData[] = [
                        'production_date' => $date,
                        'product_id'      => $productId,
                        'from_process_id' => null,
                        'to_process_id'   => $processId,
                        'qty'             => $diffNg,
                        'qty_in'          => 0,
                        'qty_out'         => 0,
                        'stock'           => 0,
                        'source_table'    => 'STOCK_OPNAME_NG',
                        'status'          => 'WAITING',
                        'created_at'      => $now,
                    ];
                    $processedProductIds[$productId] = true;
                }

                // Insert baris SHIP jika diff != 0
                if ($diffShip != 0) {
                    $insertData[] = [
                        'production_date' => $date,
                        'product_id'      => $productId,
                        'from_process_id' => null,
                        'to_process_id'   => $processId,
                        'qty'             => 0,
                        'qty_in'          => 0,
                        'qty_out'         => $diffShip,
                        'stock'           => 0,
                        'source_table'    => 'STOCK_OPNAME_SHIP',
                        'status'          => 'DONE',
                        'created_at'      => $now,
                    ];
                    $processedProductIds[$productId] = true;

                    // Juga catat sebagai delivery di material_transactions
                    if ($diffShip > 0 && $processId == 12) {
                        $shipDeliveries[] = [
                            'product_id' => $productId,
                            'qty'        => $diffShip,
                            'date'       => $date,
                        ];
                    }
                }
            }
        }

        if (empty($insertData)) {
            return redirect()->back()->with('error', 'Tidak ada data perubahan stok yang disimpan.');
        }

        try {
            $db->table('production_wip')->insertBatch($insertData);

            // Proses SHIP delivery records
            if (!empty($shipDeliveries)) {
                $this->createShipDeliveryRecords($db, $shipDeliveries, $now);
            }

            $totalProducts = count($processedProductIds);
            return redirect()->to('/sto?date='.$date)->with('success', $totalProducts . ' produk (' . count($insertData) . ' penyesuaian) Stock Opname manual berhasil disimpan.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }

    public function import()
    {
        return view('stock_opname/import');
    }

    public function preview()
    {
        $db = db_connect();
        
        $date = $this->request->getPost('production_date');
        $file = $this->request->getFile('excel_file');

        if (!$date || !$file || !$file->isValid()) {
            return redirect()->back()->with('error', 'Tanggal dan file Excel wajib diisi.');
        }

        $ext = $file->getExtension();
        if (!in_array($ext, ['xls', 'xlsx'])) {
            return redirect()->back()->with('error', 'Format file harus .xls atau .xlsx');
        }

        try {
            $spreadsheet = IOFactory::load($file->getTempName());
            $sheet = $spreadsheet->getActiveSheet();
            
            $highestColumn = $sheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
            
            // Batasi hanya memproses sampai kolom AD (index 30)
            $maxColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString('AD');
            if ($highestColumnIndex > $maxColumnIndex) {
                $highestColumnIndex = $maxColumnIndex;
            }

            $highestRow = $sheet->getHighestRow();

            $sectionHeaders = [];
            $categoryHeaders = [];
            $currentSection = null;
            
            // Loop baris 6 (Section) & 7 (Category)
            for ($col = 4; $col <= $highestColumnIndex; $col++) { 
                $val6 = trim((string)$sheet->getCell([$col, 6])->getValue());
                if (!empty($val6)) {
                    $currentSection = $val6;
                }
                
                $val7 = trim((string)$sheet->getCell([$col, 7])->getValue());
                
                $sectionHeaders[$col]  = $currentSection;
                $categoryHeaders[$col] = $val7;
            }

            // Products mapping
            $products = $db->table('products')->select('id, part_no, part_name')->where('is_active', 1)->orderBy('part_name', 'ASC')->get()->getResultArray();
            $productMap = [];
            foreach ($products as $p) {
                $cleanName = trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-zA-Z0-9]+/', ' ', strtolower($p['part_name']))));
                $productMap[$cleanName] = (int)$p['id'];
            }

            // Build matrix from Excel: matrixData[product_id][process_id][category] = qty
            $matrixData = [];
            $unmappedRows = [];
            
            for ($row = 8; $row <= $highestRow; $row++) {
                $partName = trim((string)$sheet->getCell([3, $row])->getValue()); // Col 3 (C)
                if (empty($partName)) continue;
                
                $cleanPartName = trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-zA-Z0-9]+/', ' ', strtolower($partName))));
                $productId = $productMap[$cleanPartName] ?? null;

                for ($col = 4; $col <= $highestColumnIndex; $col++) {
                    $qtyCell = $sheet->getCell([$col, $row])->getCalculatedValue(); 
                    $qty = (float)$qtyCell;
                    
                    if ($qty > 0) {
                        $section = $sectionHeaders[$col] ?? '';
                        $category = strtolower(trim($categoryHeaders[$col] ?? ''));
                        
                        $toProcessId = null;
                        foreach ($this->processMapping as $key => $val) {
                            if (stripos($section, $key) !== false) {
                                $toProcessId = $val;
                                break;
                            }
                        }

                        if (!$productId || !$toProcessId) {
                            $unmappedRows[] = [
                                'part_name' => $partName,
                                'section'   => $section,
                                'category'  => $category,
                                'qty'       => $qty,
                                'product_id'=> $productId,
                                'to_process_id' => $toProcessId,
                            ];
                            continue;
                        }

                        // Normalize category to wip/store/ng/ship
                        $catKey = 'store'; // default
                        if (stripos($category, 'wip') !== false)  $catKey = 'wip';
                        elseif (stripos($category, 'ng') !== false)   $catKey = 'ng';
                        elseif (stripos($category, 'ship') !== false) $catKey = 'ship';
                        elseif (stripos($category, 'store') !== false) $catKey = 'store';
                        elseif (stripos($category, 'stok') !== false)  $catKey = 'store';

                        if (!isset($matrixData[$productId])) $matrixData[$productId] = [];
                        if (!isset($matrixData[$productId][$toProcessId])) $matrixData[$productId][$toProcessId] = [];
                        
                        $matrixData[$productId][$toProcessId][$catKey] = 
                            ($matrixData[$productId][$toProcessId][$catKey] ?? 0) + $qty;
                    }
                }
            }

            $processes = $db->table('production_processes')->orderBy('id', 'ASC')->get()->getResultArray();
            $existingSto = $this->getStoSumByDate($date);

            // Filter products to only those found in the Excel
            $filteredProducts = array_filter($products, function($p) use ($matrixData) {
                return isset($matrixData[$p['id']]);
            });

            return view('stock_opname/preview', [
                'matrixData'       => $matrixData,
                'products'         => array_values($filteredProducts),
                'processes'        => $processes,
                'existingSto'      => $existingSto,
                'unmappedRows'     => $unmappedRows,
                'date'             => $date,
                'totalExcelItems'  => count($matrixData),
            ]);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan saat membaca file: ' . $e->getMessage());
        }
    }

    public function store()
    {
        $db = db_connect();
        $sto  = $this->request->getPost('sto');
        $date = $this->request->getPost('production_date');

        if (empty($sto) || empty($date)) {
            return redirect()->to('/sto/import')->with('error', 'Data tidak valid atau telah kedaluwarsa, silakan upload ulang.');
        }

        $now        = date('Y-m-d H:i:s');
        $insertData = [];
        $processedProductIds = [];
        $shipDeliveries = [];
        $existingSto = $this->getStoSumByDate($date);

        foreach ($sto as $productId => $processes) {
            $productId = (int)$productId;
            if (!$productId) continue;

            foreach ($processes as $processId => $categories) {
                $processId = (int)$processId;
                if (!$processId) continue;

                $qtyWip   = isset($categories['wip'])   ? (float)$categories['wip']   : 0;
                $qtyStore = isset($categories['store']) ? (float)$categories['store'] : 0;
                $qtyNg    = isset($categories['ng'])    ? (float)$categories['ng']    : 0;
                $qtyShip  = isset($categories['ship'])  ? (float)$categories['ship']  : 0;

                $exWip   = $existingSto[$productId][$processId]['wip'] ?? 0;
                $exStore = $existingSto[$productId][$processId]['store'] ?? 0;
                $exNg    = $existingSto[$productId][$processId]['ng'] ?? 0;
                $exShip  = $existingSto[$productId][$processId]['ship'] ?? 0;

                $diffWip   = $qtyWip - $exWip;
                $diffStore = $qtyStore - $exStore;
                $diffNg    = $qtyNg - $exNg;
                $diffShip  = $qtyShip - $exShip;

                if ($diffWip != 0) {
                    $insertData[] = [
                        'production_date' => $date, 'product_id' => $productId,
                        'from_process_id' => null, 'to_process_id' => $processId,
                        'qty' => 0, 'qty_in' => $diffWip, 'qty_out' => 0, 'stock' => 0,
                        'source_table' => 'STOCK_OPNAME_WIP', 'status' => 'WAITING', 'created_at' => $now,
                    ];
                    $processedProductIds[$productId] = true;
                }
                if ($diffStore != 0) {
                    $insertData[] = [
                        'production_date' => $date, 'product_id' => $productId,
                        'from_process_id' => null, 'to_process_id' => $processId,
                        'qty' => 0, 'qty_in' => 0, 'qty_out' => 0, 'stock' => $diffStore,
                        'source_table' => 'STOCK_OPNAME_STORE', 'status' => 'WAITING', 'created_at' => $now,
                    ];
                    $processedProductIds[$productId] = true;
                }
                if ($diffNg != 0) {
                    $insertData[] = [
                        'production_date' => $date, 'product_id' => $productId,
                        'from_process_id' => null, 'to_process_id' => $processId,
                        'qty' => $diffNg, 'qty_in' => 0, 'qty_out' => 0, 'stock' => 0,
                        'source_table' => 'STOCK_OPNAME_NG', 'status' => 'WAITING', 'created_at' => $now,
                    ];
                    $processedProductIds[$productId] = true;
                }
                if ($diffShip != 0) {
                    $insertData[] = [
                        'production_date' => $date, 'product_id' => $productId,
                        'from_process_id' => null, 'to_process_id' => $processId,
                        'qty' => 0, 'qty_in' => 0, 'qty_out' => $diffShip, 'stock' => 0,
                        'source_table' => 'STOCK_OPNAME_SHIP', 'status' => 'DONE', 'created_at' => $now,
                    ];
                    $processedProductIds[$productId] = true;

                    if ($diffShip > 0 && $processId == 12) {
                        $shipDeliveries[] = [
                            'product_id' => $productId,
                            'qty'        => $diffShip,
                            'date'       => $date,
                        ];
                    }
                }
            }
        }

        if (empty($insertData)) {
            return redirect()->to('/sto/import')->with('error', 'Tidak ada perubahan data stok yang perlu disimpan.');
        }

        try {
            $db->table('production_wip')->insertBatch($insertData);

            // Proses SHIP delivery records
            if (!empty($shipDeliveries)) {
                $this->createShipDeliveryRecords($db, $shipDeliveries, $now);
            }

            $totalProducts = count($processedProductIds);
            return redirect()->to('/sto?date='.$date)->with('success', $totalProducts . ' produk (' . count($insertData) . ' penyesuaian) Stok Opname berhasil diimpor!');
        } catch (\Exception $e) {
            return redirect()->to('/sto/import')->with('error', 'Gagal menyimpan ke DB: ' . $e->getMessage());
        }
    }

    public function export()
    {
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        $db = db_connect();

        $products = $db->table('products')->where('is_active', 1)->orderBy('part_name', 'ASC')->get()->getResultArray();
        $processes = $db->table('production_processes')->orderBy('id', 'ASC')->get()->getResultArray();
        $stoData = $this->getStoSumByDate($date);

        $filename = 'STO_Matrix_' . date('Ymd', strtotime($date)) . '.xls';
        
        header("Content-type: application/vnd-ms-excel");
        header("Content-Disposition: attachment; filename=$filename");
        
        // Mulai cetak HTML Table untuk Excel
        echo '<table border="1">';
        echo '<thead>';
        
        // Baris Header 1
        echo '<tr>';
        echo '<th rowspan="2">#</th>';
        echo '<th rowspan="2">Part No</th>';
        echo '<th rowspan="2">Part Name</th>';
        foreach ($processes as $pr) {
            $isFg = ($pr['id'] == 12);
            $colSpan = $isFg ? 2 : 3;
            echo '<th colspan="'.$colSpan.'">'.esc($pr['process_name']).'</th>';
        }
        echo '</tr>';
        
        // Baris Header 2
        echo '<tr>';
        foreach ($processes as $pr) {
            if ($pr['id'] == 12) {
                echo '<th>SHIP</th><th>STORE</th>';
            } else {
                echo '<th>WIP</th><th>STORE</th><th>NG</th>';
            }
        }
        echo '</tr>';
        echo '</thead>';
        
        // Body
        echo '<tbody>';
        foreach ($products as $i => $pd) {
            echo '<tr>';
            echo '<td>'.($i + 1).'</td>';
            echo '<td>'.esc($pd['part_no']).'</td>';
            echo '<td>'.esc($pd['part_name']).'</td>';
            
            foreach ($processes as $pr) {
                $valWip   = $stoData[$pd['id']][$pr['id']]['wip'] ?? 0;
                $valStore = $stoData[$pd['id']][$pr['id']]['store'] ?? 0;
                $valNg    = $stoData[$pd['id']][$pr['id']]['ng'] ?? 0;
                $valShip  = $stoData[$pd['id']][$pr['id']]['ship'] ?? 0;

                if ($pr['id'] == 12) {
                    echo '<td>'.($valShip > 0 ? $valShip : '').'</td>';
                    echo '<td>'.($valStore > 0 ? $valStore : '').'</td>';
                } else {
                    echo '<td>'.($valWip > 0 ? $valWip : '').'</td>';
                    echo '<td>'.($valStore > 0 ? $valStore : '').'</td>';
                    echo '<td>'.($valNg > 0 ? $valNg : '').'</td>';
                }
            }
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        exit;
    }

    /**
     * Mencatat SHIP STO sebagai delivery di material_transactions dan fg_deliveries
     * agar terlihat di halaman inventory finished good sebagai pengiriman
     */
    private function createShipDeliveryRecords($db, array $shipDeliveries, string $now)
    {
        if (empty($shipDeliveries)) return;

        $fgProcessId = 12; // FINISHED GOOD
        $date = $shipDeliveries[0]['date'] ?? date('Y-m-d');

        // Pastikan tabel fg_deliveries dan fg_delivery_items ada
        if (!$db->tableExists('fg_deliveries')) {
            $db->query("CREATE TABLE fg_deliveries (
                id INT AUTO_INCREMENT PRIMARY KEY,
                invoice_no VARCHAR(50) NOT NULL,
                delivery_date DATE NOT NULL,
                total_items INT DEFAULT 0,
                total_qty INT DEFAULT 0,
                created_by VARCHAR(100) DEFAULT NULL,
                created_at DATETIME DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        if (!$db->tableExists('fg_delivery_items')) {
            $db->query("CREATE TABLE fg_delivery_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                fg_delivery_id INT NOT NULL,
                product_id INT NOT NULL,
                customer_id INT DEFAULT 0,
                qty INT DEFAULT 0,
                rit VARCHAR(10) DEFAULT 'RIT-1',
                do_number VARCHAR(100) DEFAULT NULL,
                created_at DATETIME DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        // Generate invoice number
        $prefix = 'STO-' . str_replace('-', '', $date) . '-';
        $last = $db->table('fg_deliveries')
            ->select('invoice_no')
            ->like('invoice_no', $prefix, 'after')
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()->getRowArray();
        $seq = 1;
        if ($last) {
            $parts = explode('-', $last['invoice_no']);
            $seq = (int)end($parts) + 1;
        }
        $invoiceNo = $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);

        $totalQty = array_sum(array_column($shipDeliveries, 'qty'));

        // Insert delivery header
        $db->table('fg_deliveries')->insert([
            'invoice_no'    => $invoiceNo,
            'delivery_date' => $date,
            'total_items'   => count($shipDeliveries),
            'total_qty'     => $totalQty,
            'created_by'    => session()->get('fullname') ?? 'STO Import',
            'created_at'    => $now,
        ]);
        $deliveryId = (int)$db->insertID();

        // Insert delivery items
        foreach ($shipDeliveries as $sd) {
            $db->table('fg_delivery_items')->insert([
                'fg_delivery_id' => $deliveryId,
                'product_id'     => $sd['product_id'],
                'customer_id'    => 0,
                'qty'            => $sd['qty'],
                'do_number'      => 'STO-SHIP',
                'created_at'     => $now,
            ]);
        }

        // Insert material_transactions jika tabel ada
        if ($db->tableExists('material_transactions')) {
            $defaultShift = $db->table('shifts')->select('id')->where('is_active', 1)->orderBy('id', 'ASC')->limit(1)->get()->getRowArray();
            $defaultShiftId = $defaultShift ? (int)$defaultShift['id'] : 1;

            foreach ($shipDeliveries as $sd) {
                $trx = [
                    'transaction_date' => $date,
                    'shift_id'         => $defaultShiftId,
                    'product_id'       => $sd['product_id'],
                    'qty'              => $sd['qty'],
                    'transaction_type' => 'DELIVERY',
                    'process_from'     => $fgProcessId,
                    'created_at'       => $now,
                ];
                if ($db->fieldExists('source_table', 'material_transactions')) {
                    $trx['source_table'] = 'stock_opname_ship';
                }
                if ($db->fieldExists('invoice_no', 'material_transactions')) {
                    $trx['invoice_no'] = $invoiceNo;
                }
                if ($db->fieldExists('do_number', 'material_transactions')) {
                    $trx['do_number'] = 'STO-SHIP';
                }

                // Only insert columns that exist
                $clean = [];
                foreach ($trx as $k => $v) {
                    if ($db->fieldExists($k, 'material_transactions')) {
                        $clean[$k] = $v;
                    }
                }
                if (!empty($clean)) {
                    $db->table('material_transactions')->insert($clean);
                }
            }
        }

        // Kurangi stock FG di production_wip (deduct)
        foreach ($shipDeliveries as $sd) {
            $wipRows = $db->table('production_wip')
                ->where('to_process_id', $fgProcessId)
                ->where('product_id', $sd['product_id'])
                ->where('stock >', 0)
                ->where("status !=", 'DONE')
                ->orderBy('production_date', 'ASC')
                ->orderBy('id', 'ASC')
                ->get()->getResultArray();

            $remaining = (int)$sd['qty'];
            foreach ($wipRows as $wip) {
                if ($remaining <= 0) break;
                $wipStock = (int)$wip['stock'];
                $deduct   = min($remaining, $wipStock);
                $newStock = $wipStock - $deduct;
                $newOut   = (int)($wip['qty_out'] ?? 0) + $deduct;

                $db->table('production_wip')
                    ->where('id', (int)$wip['id'])
                    ->update([
                        'stock'   => $newStock,
                        'qty_out' => $newOut,
                        'status'  => ($newStock <= 0) ? 'DONE' : 'WAITING',
                    ]);
                $remaining -= $deduct;
            }
        }
    }
}
