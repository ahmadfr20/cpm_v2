<?php

namespace App\Controllers\FinishedGood;

use App\Controllers\BaseController;

class DeliveryControlBoardController extends BaseController
{
    /* ─────────────────────────────────────────────────────────────────────
       FIXED TEMPLATE DATA  (always shown on the board for every date)
    ───────────────────────────────────────────────────────────────────── */
    /** Private: used internally */
    private static function getFixedData(): array
    {
        return self::getFixedDataPublic();
    }

    /** Public: called by the view to get the fixed customer list */
    public static function getFixedDataPublic(): array
    {
        return [
            [
                'customer_name' => 'PT. DENSO MFG INDONESIA',
                'parts' => [
                    'Holder 7100', 'Holder 7110', 'Holder 7690', 'Holder 7700', 'Holder 7710',
                    'Holder 7720', 'Holder 7791', 'Holder 7590', 'Holder 7600', 'Holder 7610',
                    'Holder 7620', 'Holder 9690', 'Holder 9700', 'Holder 9710', 'Holder 9720',
                    'Holder 9730', 'Holder 9740', 'Holder 9750', 'Holder 9760', 'Holder 0580',
                    'Holder 0590', 'Holder 0600', 'Holder 0610', 'Holder 1220', 'Holder 1230',
                    'Holder 1240', 'Holder 1250', 'Holder 1320', 'Holder 1330', 'Holder 1340',
                    'Holder 1350', 'Holder 1470', 'Holder 1481', 'Holder 1490', 'Holder 1501',
                    'Holder 1650', 'Holder 1660', 'Holder 1670', 'Holder 1680', 'Holder 1750',
                    'Holder 1760', 'Holder 1910', 'Holder 1920', 'Holder 2090', 'Holder 2100',
                    'Holder 2110', 'Holder 2120', 'Housing-0160', 'Housing-571DC',
                ],
            ],
            [
                'customer_name' => 'PT. SUZUKI INDOMOBIL MOTOR',
                'parts' => [
                    'Case Comp Thermostat', 'CGSL APV', 'CWO', 'Bracket YR9', 'Cov.Gear Case 4JA',
                ],
            ],
            [
                'customer_name' => 'PT. MESIN ISUZU INDONESIA',
                'parts' => [
                    'Duct Thermostat', 'Duct Asm Water', 'Bracket Asm Generator', 'Plate MSG',
                ],
            ],
        ];
    }

    /* ─────────────────────────────────────────────────────────────────────
       TABLE / COLUMN HELPERS
    ───────────────────────────────────────────────────────────────────── */

    private function ensureBoardTable($db): void
    {
        if ($db->tableExists('delivery_control_board')) return;

        $db->query("CREATE TABLE delivery_control_board (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            board_date      DATE NOT NULL,
            customer_id     INT DEFAULT 0,
            customer_name   VARCHAR(200) DEFAULT '',
            product_id      INT DEFAULT 0,
            part_name       VARCHAR(200) DEFAULT '',
            is_fixed        TINYINT DEFAULT 0,
            target_qty      INT DEFAULT 0,
            target_kanban   INT DEFAULT NULL,
            keterangan      TEXT DEFAULT NULL,
            created_at      DATETIME DEFAULT NULL,
            updated_at      DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /** Add new columns to existing tables if they were created before this update */
    private function ensureColumns($db): void
    {
        $cols = [
            'customer_name' => "ALTER TABLE delivery_control_board ADD COLUMN customer_name VARCHAR(200) DEFAULT '' AFTER customer_id",
            'part_name'     => "ALTER TABLE delivery_control_board ADD COLUMN part_name VARCHAR(200) DEFAULT '' AFTER product_id",
            'is_fixed'      => "ALTER TABLE delivery_control_board ADD COLUMN is_fixed TINYINT DEFAULT 0 AFTER part_name",
        ];
        foreach ($cols as $col => $sql) {
            if (!$db->fieldExists($col, 'delivery_control_board')) {
                $db->query($sql);
            }
        }
    }

    private function ensureRitColumn($db): void
    {
        if ($db->tableExists('fg_delivery_items') && !$db->fieldExists('rit', 'fg_delivery_items')) {
            $db->query("ALTER TABLE fg_delivery_items ADD COLUMN rit VARCHAR(10) DEFAULT 'RIT-1' AFTER qty");
        }
    }

    /** Normalise a string key: "CUSTOMER::PART" (lowercase, trimmed) */
    private function fixedKey(string $customer, string $part): string
    {
        return strtolower(trim($customer)) . '::' . strtolower(trim($part));
    }

    /* ─────────────────────────────────────────────────────────────────────
       INDEX
    ───────────────────────────────────────────────────────────────────── */
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');

        $this->ensureBoardTable($db);
        $this->ensureColumns($db);
        $this->ensureRitColumn($db);

        /* ── Load customers and products (for the "Add custom row" form) ── */
        $customers = $db->tableExists('customers')
            ? $db->table('customers')->orderBy('customer_name', 'ASC')->get()->getResultArray()
            : [];

        $products = $db->tableExists('products')
            ? $db->table('products')->where('is_active', 1)->orderBy('part_name', 'ASC')->get()->getResultArray()
            : [];

        /* ── Build part_name → product_id lookup (for actual delivery) ── */
        $productByName = [];  // strtolower(part_name) => product_id
        $productByNo   = [];  // strtolower(part_no)   => product_id
        foreach ($products as $p) {
            $nameKey = preg_replace('/[^a-z0-9]/', '', strtolower($p['part_name']));
            $productByName[$nameKey] = (int)$p['id'];
            if (!empty($p['part_no'])) {
                $noKey = preg_replace('/[^a-z0-9]/', '', strtolower($p['part_no']));
                $productByNo[$noKey] = (int)$p['id'];
            }
        }

        /* ── Load all DB rows for this date ── */
        $dbRows = $db->table('delivery_control_board')
            ->where('board_date', $date)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();

        /* ── Index by key for fixed rows, separate custom rows ── */
        $fixedDbMap   = [];  // fixedKey => db row
        $customDbRows = [];  // non-fixed rows from DB

        foreach ($dbRows as $row) {
            if ((int)$row['is_fixed'] === 1) {
                $k = $this->fixedKey($row['customer_name'], $row['part_name']);
                $fixedDbMap[$k] = $row;
            } else {
                $customDbRows[] = $row;
            }
        }

        /* ── Build display groups ─────────────────────────────────────── */
        $displayGroups = [];   // customer_name => [rows]

        // 1) Fixed groups (DENSO, SUZUKI, ISUZU) — always present
        foreach (self::getFixedData() as $group) {
            $custName = $group['customer_name'];
            $displayGroups[$custName] = $displayGroups[$custName] ?? [];

            foreach ($group['parts'] as $partName) {
                $k      = $this->fixedKey($custName, $partName);
                $dbRow  = $fixedDbMap[$k] ?? null;

                // Try to resolve product_id for actual delivery lookup
                $pid = (int)($dbRow['product_id'] ?? 0);
                if ($pid === 0) {
                    $searchKey = preg_replace('/[^a-z0-9]/', '', strtolower($partName));
                    $pid = $productByName[$searchKey]
                        ?? $productByNo[$searchKey]
                        ?? 0;
                }

                $displayGroups[$custName][] = [
                    'id'            => (int)($dbRow['id'] ?? 0),
                    'is_fixed'      => 1,
                    'customer_name' => $custName,
                    'customer_id'   => 0,
                    'product_id'    => $pid,
                    'part_name'     => $partName,
                    'target_qty'    => (int)($dbRow['target_qty'] ?? 0),
                    'target_kanban' => $dbRow['target_kanban'] ?? null,
                    'keterangan'    => $dbRow['keterangan'] ?? '',
                ];
            }
        }

        // 2) Custom rows from DB (non-fixed)
        $custMap = [];
        foreach ($customers as $c) {
            $custMap[(int)$c['id']] = $c['customer_name'];
        }
        $prodMap = [];
        foreach ($products as $p) {
            $prodMap[(int)$p['id']] = ($p['part_no'] ? $p['part_no'] . ' — ' : '') . $p['part_name'];
        }

        foreach ($customDbRows as $row) {
            $custName = !empty($row['customer_name'])
                ? $row['customer_name']
                : ($custMap[(int)$row['customer_id']] ?? "Customer #{$row['customer_id']}");
            $partName = !empty($row['part_name'])
                ? $row['part_name']
                : ($prodMap[(int)$row['product_id']] ?? "Product #{$row['product_id']}");

            $displayGroups[$custName][] = [
                'id'            => (int)$row['id'],
                'is_fixed'      => 0,
                'customer_name' => $custName,
                'customer_id'   => (int)$row['customer_id'],
                'product_id'    => (int)$row['product_id'],
                'part_name'     => $partName,
                'target_qty'    => (int)$row['target_qty'],
                'target_kanban' => $row['target_kanban'],
                'keterangan'    => $row['keterangan'] ?? '',
            ];
        }

        /* ── Actual delivery per product_id per RIT for this date ── */
        $actualMap = [];
        if ($db->tableExists('fg_delivery_items') && $db->tableExists('fg_deliveries')) {
            $ritCol = $db->fieldExists('rit', 'fg_delivery_items') ? 'di.rit' : "'RIT-1'";
            $rows = $db->query("
                SELECT di.product_id, {$ritCol} AS rit, SUM(di.qty) AS total_qty
                FROM fg_delivery_items di
                JOIN fg_deliveries d ON d.id = di.fg_delivery_id
                WHERE d.delivery_date = ?
                GROUP BY di.product_id, {$ritCol}
            ", [$date])->getResultArray();

            foreach ($rows as $r) {
                $pid = (int)$r['product_id'];
                $rit = $r['rit'] ?: 'RIT-1';
                $actualMap[$pid][$rit] = (int)$r['total_qty'];
            }
        }

        return view('finished_good/delivery_control_board/index', [
            'date'           => $date,
            'customers'      => $customers,
            'products'       => $products,
            'displayGroups'  => $displayGroups,
            'actualMap'      => $actualMap,
            'fixedCustomers' => array_column(self::getFixedDataPublic(), 'customer_name'),
            'logged_in'      => (bool) session()->get('logged_in'),
        ]);
    }

    /* ─────────────────────────────────────────────────────────────────────
       SAVE TARGET  (AJAX POST, JSON response)
    ───────────────────────────────────────────────────────────────────── */
    public function saveTarget()
    {
        $db   = db_connect();
        $this->ensureBoardTable($db);
        $this->ensureColumns($db);

        $post = $this->request->getJSON(true) ?: $this->request->getPost();

        $date         = $post['board_date']    ?? date('Y-m-d');
        $isFixed      = (int)($post['is_fixed']       ?? 0);
        $custName     = trim($post['customer_name']   ?? '');
        $partName     = trim($post['part_name']       ?? '');
        $customerId   = (int)($post['customer_id']    ?? 0);
        $productId    = (int)($post['product_id']     ?? 0);
        $targetQty    = (int)($post['target_qty']     ?? 0);
        $targetKanban = (isset($post['target_kanban']) && $post['target_kanban'] !== '') ? (int)$post['target_kanban'] : null;
        $keterangan   = trim($post['keterangan']      ?? '');
        $rowId        = (int)($post['id']             ?? 0);
        $now          = date('Y-m-d H:i:s');

        // Validate
        if ($isFixed && ($custName === '' || $partName === '')) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'customer_name dan part_name wajib untuk baris tetap.']);
        }
        if (!$isFixed && $productId <= 0) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'product_id wajib untuk baris kustom.']);
        }

        $updateData = [
            'target_qty'    => $targetQty,
            'target_kanban' => $targetKanban,
            'keterangan'    => $keterangan ?: null,
            'updated_at'    => $now,
        ];

        if ($rowId > 0) {
            // Update existing row
            $db->table('delivery_control_board')->where('id', $rowId)->update($updateData);
            return $this->response->setJSON(['ok' => true, 'id' => $rowId, 'msg' => 'Tersimpan.']);
        }

        // Look for existing record first
        $qb = $db->table('delivery_control_board')->where('board_date', $date);

        if ($isFixed) {
            $qb->where("LOWER(customer_name) = " . $db->escape(strtolower($custName)), null, false)
               ->where("LOWER(part_name) = " . $db->escape(strtolower($partName)), null, false);
            // Try to resolve product_id if not set
            if ($productId === 0) {
                // Gunakan REPLACE untuk mengabaikan spasi dan tanda hubung
                $escapedPart = $db->escape(preg_replace('/[^a-z0-9]/', '', strtolower($partName)));
                $pRow = $db->query(
                    "SELECT id FROM products WHERE REPLACE(REPLACE(LOWER(part_name), '-', ''), ' ', '') = {$escapedPart} OR REPLACE(REPLACE(LOWER(part_no), '-', ''), ' ', '') = {$escapedPart} LIMIT 1"
                )->getRowArray();
                if ($pRow) $productId = (int)$pRow['id'];
            }
        } else {
            $qb->where('customer_id', $customerId)
               ->where('product_id', $productId)
               ->where('is_fixed', 0);
        }

        $exist = $qb->get()->getRowArray();

        if ($exist) {
            $db->table('delivery_control_board')->where('id', (int)$exist['id'])->update($updateData);
            return $this->response->setJSON(['ok' => true, 'id' => (int)$exist['id'], 'msg' => 'Diperbarui.']);
        }

        // Insert new
        $insertData = array_merge($updateData, [
            'board_date'    => $date,
            'customer_id'   => $isFixed ? 0 : $customerId,
            'customer_name' => $custName,
            'product_id'    => $productId,
            'part_name'     => $partName,
            'is_fixed'      => $isFixed,
            'created_at'    => $now,
        ]);
        $db->table('delivery_control_board')->insert($insertData);
        $newId = (int)$db->insertID();
        return $this->response->setJSON(['ok' => true, 'id' => $newId, 'msg' => 'Ditambahkan.']);
    }

    /* ─────────────────────────────────────────────────────────────────────
       DELETE ROW  (only allowed for non-fixed rows)
    ───────────────────────────────────────────────────────────────────── */
    public function deleteRow(int $id)
    {
        $db = db_connect();
        $this->ensureBoardTable($db);

        $row = $db->table('delivery_control_board')->where('id', $id)->get()->getRowArray();
        if ($row && (int)$row['is_fixed'] === 1) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Baris tetap tidak dapat dihapus.']);
        }

        $db->table('delivery_control_board')->where('id', $id)->where('is_fixed', 0)->delete();
        return $this->response->setJSON(['ok' => true]);
    }
}
