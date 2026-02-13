<?php

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\ProductionStandardModel;
use App\Models\MachineModel;
use App\Models\ProductModel;

class ProductionStandardController extends BaseController
{
    protected $standardModel;
    protected $machineModel;
    protected $productModel;

    public function __construct()
    {
        $this->standardModel = new ProductionStandardModel();
        $this->machineModel  = new MachineModel();
        $this->productModel  = new ProductModel();
    }

    /* =========================
     * LIST
     * ========================= */
    public function index()
    {
        $standards = $this->standardModel
            ->getWithRelation()
            ->paginate(15, 'standards');

        return view('master/production_standard/index', [
            'standards' => $standards,
            'pager'     => $this->standardModel->pager,
            'machines'  => $this->machineModel->findAll(),
            'products'  => $this->productModel->findAll(),
        ]);
    }

    /* =====================================================
     * Helper: cek machine Die Casting
     * ===================================================== */
    private function isDieCastingMachine(int $machineId): bool
    {
        $m = $this->machineModel->find($machineId);
        $line = strtolower(trim((string)($m['production_line'] ?? '')));
        return $line === 'die casting';
    }

    /* =====================================================
     * Helper: cycle time mesin
     * - DC => 0
     * - Machining => harus > 0
     * ===================================================== */
    private function normalizeMachineCycleTime(int $machineId, $cycleTimeInput): int
    {
        if ($this->isDieCastingMachine($machineId)) {
            return 0;
        }

        $ct = (int)($cycleTimeInput ?? 0);
        return $ct;
    }

    /* =====================================================
     * Helper: update cycle time untuk semua standard mesin tsb
     * ===================================================== */
    private function syncCycleTimeForMachine(int $machineId, int $cycleTimeSec): void
    {
        // update semua rows standard untuk machine tsb
        $this->standardModel
            ->where('machine_id', $machineId)
            ->set(['cycle_time_sec' => $cycleTimeSec])
            ->update();
    }

    /* =========================
     * STORE (single add)
     * - cycle_time_sec dianggap cycle time mesin
     * ========================= */
    public function store()
    {
        $machineId = (int)$this->request->getPost('machine_id');
        $productId = (int)$this->request->getPost('product_id');

        if ($machineId <= 0 || $productId <= 0) {
            return redirect()->back()->withInput()->with('error', 'Machine & Product wajib dipilih');
        }

        // cek exist kombinasi
        $exists = $this->standardModel
            ->where(['machine_id' => $machineId, 'product_id' => $productId])
            ->first();

        if ($exists) {
            return redirect()->back()->withInput()->with('error', 'Standard untuk machine & product ini sudah ada');
        }

        $ct = $this->normalizeMachineCycleTime($machineId, $this->request->getPost('cycle_time_sec'));

        // machining wajib >0
        if (!$this->isDieCastingMachine($machineId) && $ct <= 0) {
            return redirect()->back()->withInput()->with('error', 'Cycle time mesin (Machining) wajib > 0');
        }

        $db = db_connect();
        $db->transBegin();

        try {
            // insert standard (cycle_time_sec disimpan tapi maknanya: cycle time mesin)
            $this->standardModel->insert([
                'machine_id'     => $machineId,
                'product_id'     => $productId,
                'cycle_time_sec' => $ct,
            ]);

            // sync CT untuk semua rows di mesin itu
            $this->syncCycleTimeForMachine($machineId, $ct);

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->to('/master/production-standard')
                ->with('success', 'Production standard berhasil ditambahkan (cycle time tersimpan sebagai cycle time mesin)');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /* =========================
     * UPDATE (single edit)
     * - cycle_time_sec dianggap cycle time mesin
     * ========================= */
    public function update($id)
    {
        $id = (int)$id;

        $machineId = (int)$this->request->getPost('machine_id');
        $productId = (int)$this->request->getPost('product_id');

        if ($machineId <= 0 || $productId <= 0) {
            return redirect()->back()->withInput()->with('error', 'Machine & Product wajib dipilih');
        }

        // optional: cegah duplicate kombinasi (kecuali row sendiri)
        $dup = $this->standardModel
            ->where(['machine_id' => $machineId, 'product_id' => $productId])
            ->where('id !=', $id)
            ->first();

        if ($dup) {
            return redirect()->back()->withInput()->with('error', 'Kombinasi machine & product sudah ada');
        }

        $ct = $this->normalizeMachineCycleTime($machineId, $this->request->getPost('cycle_time_sec'));

        if (!$this->isDieCastingMachine($machineId) && $ct <= 0) {
            return redirect()->back()->withInput()->with('error', 'Cycle time mesin (Machining) wajib > 0');
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $this->standardModel->update($id, [
                'machine_id'     => $machineId,
                'product_id'     => $productId,
                'cycle_time_sec' => $ct,
            ]);

            // sync CT untuk semua standard di mesin tsb
            $this->syncCycleTimeForMachine($machineId, $ct);

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->to('/master/production-standard')
                ->with('success', 'Production standard berhasil diupdate (cycle time = cycle time mesin)');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /* =========================
     * BULK STORE
     * - 1 machine + 1 cycle time mesin (sekali)
     * - banyak product
     * ========================= */
    public function bulkStore()
    {
        $machineId = (int)$this->request->getPost('machine_id');
        $rows      = $this->request->getPost('rows');

        if ($machineId <= 0) {
            return redirect()->back()->withInput()->with('error', 'Machine wajib dipilih');
        }
        if (!$rows || !is_array($rows)) {
            return redirect()->back()->withInput()->with('error', 'Row bulk kosong');
        }

        $ct = $this->normalizeMachineCycleTime($machineId, $this->request->getPost('cycle_time_sec'));
        if (!$this->isDieCastingMachine($machineId) && $ct <= 0) {
            return redirect()->back()->withInput()->with('error', 'Cycle time mesin (Machining) wajib > 0');
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $inserted = 0;

            foreach ($rows as $r) {
                $productId = (int)($r['product_id'] ?? 0);
                if ($productId <= 0) continue;

                $exists = $this->standardModel
                    ->where(['machine_id' => $machineId, 'product_id' => $productId])
                    ->first();

                if ($exists) continue;

                $this->standardModel->insert([
                    'machine_id'     => $machineId,
                    'product_id'     => $productId,
                    'cycle_time_sec' => $ct,
                ]);
                $inserted++;
            }

            // sync CT untuk semua standard di mesin tsb
            $this->syncCycleTimeForMachine($machineId, $ct);

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->to('/master/production-standard')
                ->with('success', "Bulk add selesai. Inserted: {$inserted}. (Cycle time = mesin)");
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /* =========================
     * BULK UPDATE
     * - ids[] wajib
     * - machine_id optional (pindah machine)
     * - cycle_time_sec optional -> dianggap cycle time mesin
     *   Jika machine_id dipilih -> sync untuk machine itu
     *   Jika machine_id kosong -> sync untuk machine dari row-row terpilih
     * ========================= */
    public function bulkUpdate()
    {
        $ids = $this->request->getPost('ids');
        if (!$ids || !is_array($ids)) {
            return redirect()->back()->with('error', 'Tidak ada data dipilih');
        }

        $ids = array_values(array_filter(array_map('intval', $ids), fn($v)=> $v > 0));
        if (!$ids) return redirect()->back()->with('error', 'Tidak ada data valid');

        $machineIdNew = (int)($this->request->getPost('machine_id') ?? 0);
        $cycleRaw     = $this->request->getPost('cycle_time_sec');
        $cycleProvided = ($cycleRaw !== null && $cycleRaw !== '');

        $db = db_connect();
        $db->transBegin();

        try {
            // ambil rows terpilih
            $rows = $this->standardModel->whereIn('id', $ids)->findAll();
            if (!$rows) throw new \Exception('Data standard tidak ditemukan');

            // daftar machine yang akan di-sync cycle time-nya
            $machineToSync = [];

            // jika pindah machine
            if ($machineIdNew > 0) {
                // update machine_id untuk semua selected
                foreach ($rows as $r) {
                    $this->standardModel->update((int)$r['id'], ['machine_id' => $machineIdNew]);
                }
                $machineToSync[$machineIdNew] = true;
            } else {
                // tidak pindah machine, sync per machine yang terseleksi
                foreach ($rows as $r) {
                    $mid = (int)($r['machine_id'] ?? 0);
                    if ($mid > 0) $machineToSync[$mid] = true;
                }
            }

            // jika cycle time diisi, maka sync CT (cycle time mesin)
            if ($cycleProvided) {
                foreach (array_keys($machineToSync) as $mid) {
                    $ct = $this->normalizeMachineCycleTime((int)$mid, $cycleRaw);

                    if (!$this->isDieCastingMachine((int)$mid) && $ct <= 0) {
                        throw new \Exception('Ada mesin Machining pada selection: cycle time wajib > 0');
                    }

                    $this->syncCycleTimeForMachine((int)$mid, (int)$ct);
                }
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->to('/master/production-standard')
                ->with('success', 'Bulk edit berhasil (cycle time = cycle time mesin)');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /* =========================
     * BULK DELETE
     * ========================= */
    public function bulkDelete()
    {
        $ids = $this->request->getPost('ids');
        if (!$ids || !is_array($ids)) {
            return redirect()->back()->with('error', 'Tidak ada data dipilih');
        }

        $ids = array_values(array_filter(array_map('intval', $ids), fn($v)=> $v > 0));
        if (!$ids) return redirect()->back()->with('error', 'Tidak ada data valid');

        $db = db_connect();
        $db->transBegin();

        try {
            $this->standardModel->whereIn('id', $ids)->delete();

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->to('/master/production-standard')
                ->with('success', 'Bulk delete berhasil: '.count($ids).' data');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /* =========================
     * DELETE single
     * ========================= */
    public function delete($id)
    {
        $this->standardModel->delete((int)$id);
        return redirect()->to('/master/production-standard')->with('success', 'Production standard berhasil dihapus');
    }
}
