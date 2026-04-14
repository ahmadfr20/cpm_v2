<?php

namespace App\Controllers\FinishedGood;

use App\Controllers\BaseController;

class DeliveryController extends BaseController
{
    /* ─── helpers ─── */
    private function onlyExistingColumns($db, string $table, array $data): array
    {
        $clean = [];
        foreach ($data as $k => $v) if ($db->fieldExists($k, $table)) $clean[$k] = $v;
        return $clean;
    }

    private function getFgProcessId($db): int
    {
        $names = ['FINISHED GOOD', 'Finished Good', 'FG'];
        foreach ($names as $n) {
            $r = $db->table('production_processes')->select('id')->where('process_name', $n)->get()->getRowArray();
            if ($r) return (int)$r['id'];
        }
        return 0;
    }

    private function getQcProcessId($db): int
    {
        $names = ['FINAL INSPECTION', 'Final Inspection', 'Final Inspection / QC', 'QC'];
        foreach ($names as $n) {
            $r = $db->table('production_processes')->select('id')->where('process_name', $n)->get()->getRowArray();
            if ($r) return (int)$r['id'];
        }
        return 0;
    }

    /**
     * Collect FG stock from production_wip where to_process_id = FG
     * Returns map  product_id => total_stock
     */
    private function getFgStockMap($db, string $date, int $fgProcessId): array
    {
        if (!$db->tableExists('production_wip') || $fgProcessId <= 0) return [];

        $wipDateCol = $db->fieldExists('wip_date', 'production_wip') ? 'wip_date' : 'production_date';

        $rows = $db->table('production_wip')
            ->select("product_id, SUM(stock) AS total_stock")
            ->where('to_process_id', $fgProcessId)
            ->where('stock >', 0)
            ->where("status !=", 'DONE')
            ->where("$wipDateCol <=", $date)
            ->groupBy('product_id')
            ->get()->getResultArray();

        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['product_id']] = (int)$r['total_stock'];
        }
        return $map;
    }

    /* ─── INDEX ─── */
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        $fgProcessId = $this->getFgProcessId($db);

        $customers = $db->tableExists('customers')
            ? $db->table('customers')->orderBy('customer_name', 'ASC')->get()->getResultArray()
            : [];

        $products = $db->tableExists('products')
            ? $db->table('products')->where('is_active', 1)->orderBy('part_no', 'ASC')->get()->getResultArray()
            : [];

        // FG stock from WIP (to_process_id = FG)
        $availableMap = $this->getFgStockMap($db, $date, $fgProcessId);

        // delivery history
        $history = $this->getDeliveryHistory($db, $date);

        return view('finished_good/delivery/index', [
            'date'         => $date,
            'customers'    => $customers,
            'products'     => $products,
            'availableMap' => $availableMap,
            'history'      => $history,
            'fgProcessId'  => $fgProcessId,
        ]);
    }

    /* ─── AJAX: ready stock ─── */
    public function getReadyStock()
    {
        $db   = db_connect();
        $date = date('Y-m-d');
        $pid  = (int)$this->request->getGet('product_id');
        if ($pid <= 0) return $this->response->setJSON(['stock' => 0]);

        $fgProcessId = $this->getFgProcessId($db);
        $map = $this->getFgStockMap($db, $date, $fgProcessId);
        return $this->response->setJSON(['stock' => $map[$pid] ?? 0]);
    }

    /* ─── STORE ─── */
    public function store()
    {
        $db    = db_connect();
        $date  = $this->request->getPost('delivery_date') ?: date('Y-m-d');
        $items = $this->request->getPost('items');

        if (!is_array($items) || empty($items)) {
            return redirect()->back()->with('error', 'Tidak ada data item untuk dikirim.');
        }

        $fgProcessId = $this->getFgProcessId($db);
        if ($fgProcessId <= 0) {
            return redirect()->back()->with('error', 'Process Finished Good belum terdaftar di master.');
        }

        $wipDateCol = $db->fieldExists('wip_date', 'production_wip') ? 'wip_date' : 'production_date';
        $now = date('Y-m-d H:i:s');

        // Generate invoice number: INV-YYYYMMDD-XXX
        $invoiceNo = $this->generateInvoiceNumber($db, $date);

        $db->transBegin();
        try {
            $deliveredItems = [];
            $generatedDos = [];

            foreach ($items as $row) {
                $productId  = (int)($row['product_id'] ?? 0);
                $customerId = (int)($row['customer_id'] ?? 0);
                $qty        = (int)($row['qty'] ?? 0);

                if ($productId <= 0 || $qty <= 0) continue;

                if (!isset($generatedDos[$customerId])) {
                    $generatedDos[$customerId] = $this->generateDoNumber($db, $customerId);
                }
                $doNumber = $generatedDos[$customerId];

                // Get total FG stock for this product
                $fgStock = $this->getFgStockMap($db, $date, $fgProcessId);
                $available = $fgStock[$productId] ?? 0;

                if ($qty > $available) {
                    $prod = $db->table('products')->where('id', $productId)->get()->getRowArray();
                    $pn = $prod['part_no'] ?? "ID $productId";
                    throw new \Exception("Qty delivery ($qty) melebihi stock FG ($available) untuk $pn.");
                }

                // Insert material_transactions
                // shift_id has FK to shifts table, get first valid shift
                $defaultShift = $db->table('shifts')->select('id')->where('is_active', 1)->orderBy('id', 'ASC')->limit(1)->get()->getRowArray();
                $defaultShiftId = $defaultShift ? (int)$defaultShift['id'] : 1;

                $trxPayload = [
                    'transaction_date' => $date,
                    'shift_id'         => $defaultShiftId,
                    'product_id'       => $productId,
                    'qty'              => $qty,
                    'transaction_type' => 'DELIVERY',
                    'process_from'     => $fgProcessId,
                    'created_at'       => $now,
                ];

                if ($db->fieldExists('customer_id', 'material_transactions')) {
                    $trxPayload['customer_id'] = $customerId;
                } elseif ($db->fieldExists('process_to', 'material_transactions')) {
                    $trxPayload['process_to'] = $customerId;
                }

                if ($db->fieldExists('do_number', 'material_transactions')) {
                    $trxPayload['do_number'] = $doNumber;
                }
                if ($db->fieldExists('invoice_no', 'material_transactions')) {
                    $trxPayload['invoice_no'] = $invoiceNo;
                }
                if ($db->fieldExists('source_table', 'material_transactions')) {
                    $trxPayload['source_table'] = 'direct_delivery';
                }
                if ($db->fieldExists('source_id', 'material_transactions')) {
                    $trxPayload['source_id'] = 0;
                }

                $db->table('material_transactions')->insert($this->onlyExistingColumns($db, 'material_transactions', $trxPayload));

                // Deduct from production_wip FG stock (FIFO)
                $wipRows = $db->table('production_wip')
                    ->where('to_process_id', $fgProcessId)
                    ->where('product_id', $productId)
                    ->where('stock >', 0)
                    ->where("status !=", 'DONE')
                    ->where("$wipDateCol <=", $date)
                    ->orderBy($wipDateCol, 'ASC')
                    ->orderBy('id', 'ASC')
                    ->get()->getResultArray();

                $remaining = $qty;
                foreach ($wipRows as $wip) {
                    if ($remaining <= 0) break;
                    $wipStock = (int)$wip['stock'];
                    $deduct   = min($remaining, $wipStock);
                    $newStock = $wipStock - $deduct;
                    $newOut   = (int)($wip['qty_out'] ?? 0) + $deduct;

                    $upd = [
                        'stock'   => $newStock,
                        'qty_out' => $newOut,
                        'status'  => ($newStock <= 0) ? 'DONE' : 'WAITING',
                    ];
                    $db->table('production_wip')->where('id', (int)$wip['id'])->update($upd);
                    $remaining -= $deduct;
                }

                // Also insert FG delivery history in production_wip
                if ($db->fieldExists('transfer', 'production_wip')) {
                    $transferCol = 'transfer';
                } else {
                    $transferCol = null;
                }

                $fgWipOut = [
                    $wipDateCol       => $date,
                    'product_id'      => $productId,
                    'from_process_id' => $fgProcessId,
                    'to_process_id'   => $fgProcessId,
                    'qty'             => $qty,
                    'qty_in'          => 0,
                    'qty_out'         => $qty,
                    'stock'           => 0,
                    'source_table'    => 'material_transactions',
                    'source_id'       => (int)$db->insertID(),
                    'status'          => 'DONE',
                    'created_at'      => $now,
                ];
                if ($transferCol) $fgWipOut[$transferCol] = $qty;

                $db->table('production_wip')->insert($this->onlyExistingColumns($db, 'production_wip', $fgWipOut));

                $deliveredItems[] = [
                    'product_id'  => $productId,
                    'customer_id' => $customerId,
                    'qty'         => $qty,
                    'do_number'   => $doNumber,
                ];
            }

            // Save delivery summary (fg_deliveries table, create if not exists)
            $this->ensureFgDeliveriesTable($db);
            $db->table('fg_deliveries')->insert([
                'invoice_no'    => $invoiceNo,
                'delivery_date' => $date,
                'total_items'   => count($deliveredItems),
                'total_qty'     => array_sum(array_column($deliveredItems, 'qty')),
                'created_by'    => session()->get('fullname') ?? 'System',
                'created_at'    => $now,
            ]);
            $deliveryId = (int)$db->insertID();

            $this->ensureFgDeliveryItemsTable($db);
            foreach ($deliveredItems as $di) {
                $db->table('fg_delivery_items')->insert([
                    'fg_delivery_id' => $deliveryId,
                    'product_id'     => $di['product_id'],
                    'customer_id'    => $di['customer_id'],
                    'qty'            => $di['qty'],
                    'do_number'      => $di['do_number'],
                    'created_at'     => $now,
                ]);
            }

            if ($db->transStatus() === false) throw new \Exception('DB error saat proses delivery.');

            $db->transCommit();
            return redirect()->back()->with('success', "Delivery berhasil! Invoice: $invoiceNo | " . count($deliveredItems) . " item(s) terkirim.");

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /* ─── INVOICE VIEW ─── */
    public function invoice(int $id)
    {
        $db = db_connect();

        $this->ensureFgDeliveriesTable($db);
        $this->ensureFgDeliveryItemsTable($db);

        $delivery = $db->table('fg_deliveries')->where('id', $id)->get()->getRowArray();
        if (!$delivery) {
            return redirect()->to('/finished-good/delivery')->with('error', 'Invoice tidak ditemukan.');
        }

        $items = $db->table('fg_delivery_items di')
            ->select('di.*, p.part_no, p.part_name, c.customer_name, c.address AS customer_address')
            ->join('products p', 'p.id = di.product_id', 'left')
            ->join('customers c', 'c.id = di.customer_id', 'left')
            ->where('di.fg_delivery_id', $id)
            ->get()->getResultArray();

        return view('finished_good/delivery/invoice', [
            'delivery' => $delivery,
            'items'    => $items,
        ]);
    }

    /* ─── EXPORT CSV ─── */
    public function export()
    {
        $db = db_connect();
        $dateFrom = $this->request->getGet('from') ?: date('Y-m-01');
        $dateTo   = $this->request->getGet('to') ?: date('Y-m-d');

        $this->ensureFgDeliveriesTable($db);
        $this->ensureFgDeliveryItemsTable($db);

        $rows = $db->table('fg_delivery_items di')
            ->select('d.invoice_no, d.delivery_date, p.part_no, p.part_name, c.customer_name, di.qty, di.do_number, d.created_by')
            ->join('fg_deliveries d', 'd.id = di.fg_delivery_id')
            ->join('products p', 'p.id = di.product_id', 'left')
            ->join('customers c', 'c.id = di.customer_id', 'left')
            ->where('d.delivery_date >=', $dateFrom)
            ->where('d.delivery_date <=', $dateTo)
            ->orderBy('d.delivery_date', 'DESC')
            ->orderBy('d.id', 'DESC')
            ->get()->getResultArray();

        $filename = "FG_Delivery_{$dateFrom}_to_{$dateTo}.csv";

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=$filename");

        $out = fopen('php://output', 'w');
        // BOM for Excel
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['Invoice No', 'Delivery Date', 'Part No', 'Part Name', 'Customer', 'Qty', 'DO Number', 'Created By']);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['invoice_no'],
                $r['delivery_date'],
                $r['part_no'],
                $r['part_name'],
                $r['customer_name'],
                $r['qty'],
                $r['do_number'],
                $r['created_by'],
            ]);
        }
        fclose($out);
        exit;
    }

    /* ─── helpers ─── */

    private function getDeliveryHistory($db, string $date): array
    {
        $this->ensureFgDeliveriesTable($db);
        $this->ensureFgDeliveryItemsTable($db);

        $deliveries = $db->table('fg_deliveries')
            ->where('delivery_date', $date)
            ->orderBy('id', 'DESC')
            ->get()->getResultArray();

        foreach ($deliveries as &$d) {
            $d['items'] = $db->table('fg_delivery_items di')
                ->select('di.*, p.part_no, p.part_name, c.customer_name')
                ->join('products p', 'p.id = di.product_id', 'left')
                ->join('customers c', 'c.id = di.customer_id', 'left')
                ->where('di.fg_delivery_id', (int)$d['id'])
                ->get()->getResultArray();
        }
        unset($d);

        return $deliveries;
    }

    private function generateInvoiceNumber($db, string $date): string
    {
        $this->ensureFgDeliveriesTable($db);
        $prefix = 'INV-' . str_replace('-', '', $date) . '-';
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
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    private function generateDoNumber($db, int $customerId): string
    {
        $this->ensureFgDeliveryItemsTable($db);
        $customer = $db->table('customers')->where('id', $customerId)->get()->getRowArray();
        
        $custCode = 'NOCUST';
        if ($customer) {
            $custCode = !empty($customer['customer_code_app']) ? $customer['customer_code_app'] : (!empty($customer['customer_code']) ? $customer['customer_code'] : 'UNK');
        }

        $prefix = "DO-{$custCode}-";

        $last = $db->table('fg_delivery_items')
            ->select('do_number')
            ->where('customer_id', $customerId)
            ->like('do_number', $prefix, 'after')
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()->getRowArray();

        $seq = 1;
        if ($last && !empty($last['do_number'])) {
            $parts = explode('-', $last['do_number']);
            $seq = (int)end($parts) + 1;
        }

        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    private function ensureFgDeliveriesTable($db): void
    {
        if ($db->tableExists('fg_deliveries')) return;

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

    private function ensureFgDeliveryItemsTable($db): void
    {
        if ($db->tableExists('fg_delivery_items')) return;

        $db->query("CREATE TABLE fg_delivery_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fg_delivery_id INT NOT NULL,
            product_id INT NOT NULL,
            customer_id INT DEFAULT 0,
            qty INT DEFAULT 0,
            do_number VARCHAR(100) DEFAULT NULL,
            created_at DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}
