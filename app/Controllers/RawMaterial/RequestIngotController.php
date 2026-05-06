<?php

namespace App\Controllers\RawMaterial;

use App\Controllers\BaseController;

class RequestIngotController extends BaseController
{
    /**
     * Auto-create tabel jika belum ada
     */
    private function ensureTable($db): void
    {
        if ($db->tableExists('raw_material_ingot_requests')) return;

        $db->query("CREATE TABLE IF NOT EXISTS `raw_material_ingot_requests` (
            `id`           INT AUTO_INCREMENT PRIMARY KEY,
            `request_date` DATE NOT NULL,
            `shift_id`     INT NULL,
            `machine_id`   INT NULL,
            `weight_kg`    DECIMAL(10,3) NOT NULL DEFAULT 0 COMMENT 'Berat ingot yang diminta (Kg)',
            `requested_by` VARCHAR(100)  NULL,
            `notes`        TEXT          NULL,
            `created_at`   DATETIME      NULL,
            `updated_at`   DATETIME      NULL,
            INDEX idx_request_date (request_date),
            INDEX idx_shift_id (shift_id),
            INDEX idx_machine_id (machine_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /* =========================
     * INDEX
     * ========================= */
    public function index()
    {
        $db = db_connect();
        $this->ensureTable($db);

        $date = $this->request->getGet('date') ?? date('Y-m-d');

        // Stok INGOT saat ini (dalam Kg)
        $stockRow = $db->table('raw_material_stock')
            ->where('material_type', 'INGOT')
            ->where('unit', 'Kg')
            ->get()->getRowArray();
        $currentStockKg = $stockRow ? (float)$stockRow['total_qty'] : 0.0;

        // Daftar shift DC (untuk pilihan shift)
        $shifts = $db->table('shifts')
            ->where('is_active', 1)
            ->like('shift_name', 'DC')
            ->orderBy('CAST(shift_code AS UNSIGNED)', 'ASC')
            ->get()->getResultArray();

        // Daftar mesin Die Casting
        $machines = $db->table('machines')
            ->where('production_line', 'Die Casting')
            ->orderBy('line_position')
            ->get()->getResultArray();

        // Requests hari ini
        $todayRequests = $db->table('raw_material_ingot_requests r')
            ->select('r.*, s.shift_name, m.machine_code')
            ->join('shifts s', 's.id = r.shift_id', 'left')
            ->join('machines m', 'm.id = r.machine_id', 'left')
            ->where('r.request_date', $date)
            ->orderBy('r.created_at', 'DESC')
            ->get()->getResultArray();

        // History 50 request terakhir
        $history = $db->table('raw_material_ingot_requests r')
            ->select('r.*, s.shift_name, m.machine_code')
            ->join('shifts s', 's.id = r.shift_id', 'left')
            ->join('machines m', 'm.id = r.machine_id', 'left')
            ->orderBy('r.request_date', 'DESC')
            ->orderBy('r.created_at', 'DESC')
            ->limit(50)
            ->get()->getResultArray();

        return view('raw_material/request_ingot/index', [
            'date'           => $date,
            'currentStockKg' => $currentStockKg,
            'shifts'         => $shifts,
            'machines'       => $machines,
            'todayRequests'  => $todayRequests,
            'history'        => $history,
        ]);
    }

    /* =========================
     * AJAX: Info Stok Terkini
     * ========================= */
    public function stockInfo()
    {
        $db = db_connect();
        $row = $db->table('raw_material_stock')
            ->where('material_type', 'INGOT')
            ->where('unit', 'Kg')
            ->get()->getRowArray();

        return $this->response->setJSON([
            'stock_kg' => $row ? (float)$row['total_qty'] : 0.0,
        ]);
    }

    /* =========================
     * STORE (insert request & kurangi stok)
     * ========================= */
    public function store()
    {
        $db = db_connect();
        $this->ensureTable($db);

        $date        = $this->request->getPost('request_date');
        $shiftId     = (int)$this->request->getPost('shift_id');
        $machineId   = (int)$this->request->getPost('machine_id');
        $weightKg    = (float)$this->request->getPost('weight_kg');
        $requestedBy = trim((string)$this->request->getPost('requested_by'));
        $notes       = trim((string)$this->request->getPost('notes'));

        // Validasi dasar
        if (empty($date) || $weightKg <= 0) {
            return redirect()->back()->with('error', 'Tanggal dan berat harus diisi dan berat harus lebih dari 0 Kg.');
        }

        $now = date('Y-m-d H:i:s');

        $db->transBegin();
        try {
            // Cek stok INGOT (Kg)
            $stockRow = $db->table('raw_material_stock')
                ->where('material_type', 'INGOT')
                ->where('unit', 'Kg')
                ->get()->getRowArray();

            $currentStock = $stockRow ? (float)$stockRow['total_qty'] : 0.0;

            if ($currentStock < $weightKg) {
                throw new \Exception(
                    "Stok Ingot tidak mencukupi. Stok saat ini: " . number_format($currentStock, 3) . " Kg, " .
                    "diminta: " . number_format($weightKg, 3) . " Kg."
                );
            }

            // Insert request
            $db->table('raw_material_ingot_requests')->insert([
                'request_date' => $date,
                'shift_id'     => $shiftId > 0 ? $shiftId : null,
                'machine_id'   => $machineId > 0 ? $machineId : null,
                'weight_kg'    => $weightKg,
                'requested_by' => $requestedBy ?: null,
                'notes'        => $notes ?: null,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);

            // Kurangi stok INGOT (Kg)
            $newStock = $currentStock - $weightKg;

            if ($stockRow) {
                $db->table('raw_material_stock')
                    ->where('material_type', 'INGOT')
                    ->where('unit', 'Kg')
                    ->update([
                        'total_qty'  => $newStock,
                        'updated_at' => $now,
                    ]);
            } else {
                // Kalau belum ada baris stok, insert dengan nilai negatif (anomali)
                $db->table('raw_material_stock')->insert([
                    'material_type' => 'INGOT',
                    'unit'          => 'Kg',
                    'total_qty'     => -$weightKg,
                    'updated_at'    => $now,
                ]);
            }

            if ($db->transStatus() === false) {
                throw new \Exception('Terjadi kesalahan pada database.');
            }

            $db->transCommit();
            return redirect()->back()->with('success',
                "Request berhasil! Stok Ingot berkurang " . number_format($weightKg, 3) . " Kg. " .
                "Sisa stok: " . number_format($newStock, 3) . " Kg."
            );

        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
