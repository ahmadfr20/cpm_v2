<?php

namespace App\Controllers\FinishedGood;

use App\Controllers\BaseController;

class SpecialControlDeliveryController extends BaseController
{
    /* ─────────────────────────────────────────────────────────────
       AUTO-CREATE TABLES
    ───────────────────────────────────────────────────────────── */
    private function ensureTables(\CodeIgniter\Database\BaseConnection $db): void
    {
        if (!$db->tableExists('scd_settings')) {
            $db->query("CREATE TABLE scd_settings (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                board_date  DATE NOT NULL,
                ost_label   VARCHAR(150) DEFAULT '',
                rit1_time   VARCHAR(5)   DEFAULT '',
                rit2_time   VARCHAR(5)   DEFAULT '',
                rit3_time   VARCHAR(5)   DEFAULT '',
                rit4_time   VARCHAR(5)   DEFAULT '',
                rit5_time   VARCHAR(5)   DEFAULT '',
                UNIQUE KEY uk_scd_date (board_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!$db->tableExists('scd_rows')) {
            $db->query("CREATE TABLE scd_rows (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                board_date    DATE NOT NULL,
                customer_id   INT          DEFAULT 0,
                customer_name VARCHAR(200) DEFAULT '',
                product_id    INT          DEFAULT 0,
                part_name     VARCHAR(200) DEFAULT '',
                plan_qty      INT          DEFAULT 0,
                rit1_qty      INT          DEFAULT 0,
                rit2_qty      INT          DEFAULT 0,
                rit3_qty      INT          DEFAULT 0,
                rit4_qty      INT          DEFAULT 0,
                rit5_qty      INT          DEFAULT 0,
                keterangan    TEXT         DEFAULT NULL,
                row_order     INT          DEFAULT 0,
                created_at    DATETIME     DEFAULT NULL,
                updated_at    DATETIME     DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    /* ─────────────────────────────────────────────────────────────
       INDEX  (public — no auth required)
    ───────────────────────────────────────────────────────────── */
    public function index()
    {
        $db   = db_connect();
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        $this->ensureTables($db);

        /* Settings (OST label + RIT times) */
        $settings = $db->table('scd_settings')
                       ->where('board_date', $date)
                       ->get()->getRowArray();
        if (!$settings) {
            $settings = [
                'ost_label' => '',
                'rit1_time' => '', 'rit2_time' => '', 'rit3_time' => '',
                'rit4_time' => '', 'rit5_time' => '',
            ];
        }

        /* Rows for this date */
        $rows = $db->table('scd_rows')
                   ->where('board_date', $date)
                   ->orderBy('row_order', 'ASC')
                   ->orderBy('id', 'ASC')
                   ->get()->getResultArray();

        /* Reference lists */
        $customers = $db->tableExists('customers')
            ? $db->table('customers')->orderBy('customer_name', 'ASC')->get()->getResultArray()
            : [];
        $products = $db->tableExists('products')
            ? $db->table('products')->where('is_active', 1)->orderBy('part_name', 'ASC')->get()->getResultArray()
            : [];

        return view('finished_good/special_control_delivery/index', [
            'date'      => $date,
            'settings'  => $settings,
            'rows'      => $rows,
            'customers' => $customers,
            'products'  => $products,
            'logged_in' => (bool) session()->get('logged_in'),
        ]);
    }

    /* ─────────────────────────────────────────────────────────────
       SAVE SETTINGS  (OST label + RIT times – AJAX)
    ───────────────────────────────────────────────────────────── */
    public function saveSettings()
    {
        $db   = db_connect();
        $this->ensureTables($db);
        $post = $this->request->getJSON(true) ?: $this->request->getPost();
        $date = $post['board_date'] ?? date('Y-m-d');

        $data = [
            'ost_label' => trim($post['ost_label'] ?? ''),
            'rit1_time' => substr(trim($post['rit1_time'] ?? ''), 0, 5),
            'rit2_time' => substr(trim($post['rit2_time'] ?? ''), 0, 5),
            'rit3_time' => substr(trim($post['rit3_time'] ?? ''), 0, 5),
            'rit4_time' => substr(trim($post['rit4_time'] ?? ''), 0, 5),
            'rit5_time' => substr(trim($post['rit5_time'] ?? ''), 0, 5),
        ];

        $exist = $db->table('scd_settings')->where('board_date', $date)->get()->getRowArray();
        if ($exist) {
            $db->table('scd_settings')->where('board_date', $date)->update($data);
        } else {
            $db->table('scd_settings')->insert(array_merge($data, ['board_date' => $date]));
        }
        return $this->response->setJSON(['ok' => true]);
    }

    /* ─────────────────────────────────────────────────────────────
       SAVE ROW  (create or update – AJAX)
    ───────────────────────────────────────────────────────────── */
    public function saveRow()
    {
        $db   = db_connect();
        $this->ensureTables($db);
        $post = $this->request->getJSON(true) ?: $this->request->getPost();
        $now  = date('Y-m-d H:i:s');
        $id   = (int)($post['id'] ?? 0);

        $data = [
            'board_date'    => $post['board_date'] ?? date('Y-m-d'),
            'customer_id'   => (int)($post['customer_id']   ?? 0),
            'customer_name' => trim($post['customer_name']   ?? ''),
            'product_id'    => (int)($post['product_id']    ?? 0),
            'part_name'     => trim($post['part_name']       ?? ''),
            'plan_qty'      => (int)($post['plan_qty']       ?? 0),
            'rit1_qty'      => (int)($post['rit1_qty']       ?? 0),
            'rit2_qty'      => (int)($post['rit2_qty']       ?? 0),
            'rit3_qty'      => (int)($post['rit3_qty']       ?? 0),
            'rit4_qty'      => (int)($post['rit4_qty']       ?? 0),
            'rit5_qty'      => (int)($post['rit5_qty']       ?? 0),
            'keterangan'    => trim($post['keterangan'] ?? '') ?: null,
            'row_order'     => (int)($post['row_order'] ?? 0),
            'updated_at'    => $now,
        ];

        if ($id > 0) {
            $db->table('scd_rows')->where('id', $id)->update($data);
            return $this->response->setJSON(['ok' => true, 'id' => $id]);
        }

        $data['created_at'] = $now;
        $db->table('scd_rows')->insert($data);
        return $this->response->setJSON(['ok' => true, 'id' => (int)$db->insertID()]);
    }

    /* ─────────────────────────────────────────────────────────────
       DELETE ROW  (AJAX)
    ───────────────────────────────────────────────────────────── */
    public function deleteRow(int $id)
    {
        $db = db_connect();
        $this->ensureTables($db);
        $db->table('scd_rows')->where('id', $id)->delete();
        return $this->response->setJSON(['ok' => true]);
    }
}
