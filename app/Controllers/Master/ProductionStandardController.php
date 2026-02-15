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

    private function isDieCastingMachine(int $machineId): bool
    {
        $m = $this->machineModel->find($machineId);
        $line = strtolower(trim((string)($m['production_line'] ?? '')));
        return $line === 'die casting';
    }

    private function normalizeMachineCycleTime(int $machineId, $cycleTimeInput): int
    {
        if ($this->isDieCastingMachine($machineId)) return 0;
        return (int)($cycleTimeInput ?? 0);
    }

    private function syncCycleTimeForMachine(int $machineId, int $cycleTimeSec): void
    {
        $this->standardModel
            ->where('machine_id', $machineId)
            ->set(['cycle_time_sec' => $cycleTimeSec])
            ->update();
    }

    /**
     * Ambil CT produk dari master products
     */
    private function getProductCycleTimes(int $productId): array
    {
        $p = $this->productModel->find($productId);
        if (!$p) {
            return [
                'cycle_time_die_casting_sec' => 0,
                'cycle_time_machining_sec'   => 0,
            ];
        }

        return [
            'cycle_time_die_casting_sec' => (int)($p['cycle_time'] ?? 0),
            'cycle_time_machining_sec'   => (int)($p['cycle_time_machining'] ?? 0),
        ];
    }

    public function store()
    {
        $machineId = (int)$this->request->getPost('machine_id');
        $productId = (int)$this->request->getPost('product_id');

        if ($machineId <= 0 || $productId <= 0) {
            return redirect()->back()->withInput()->with('error', 'Machine & Product wajib dipilih');
        }

        $exists = $this->standardModel
            ->where(['machine_id' => $machineId, 'product_id' => $productId])
            ->first();
        if ($exists) {
            return redirect()->back()->withInput()->with('error', 'Standard untuk machine & product ini sudah ada');
        }

        $ctMachine = $this->normalizeMachineCycleTime($machineId, $this->request->getPost('cycle_time_sec'));
        if (!$this->isDieCastingMachine($machineId) && $ctMachine <= 0) {
            return redirect()->back()->withInput()->with('error', 'Cycle time mesin (Machining) wajib > 0');
        }

        $ctProduct = $this->getProductCycleTimes($productId);

        $db = db_connect();
        $db->transBegin();

        try {
            $this->standardModel->insert([
                'machine_id'                  => $machineId,
                'product_id'                  => $productId,
                'cycle_time_sec'              => $ctMachine,
                'cycle_time_die_casting_sec'  => $ctProduct['cycle_time_die_casting_sec'],
                'cycle_time_machining_sec'    => $ctProduct['cycle_time_machining_sec'],
            ]);

            $this->syncCycleTimeForMachine($machineId, $ctMachine);

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->to('/master/production-standard')
                ->with('success', 'Production standard berhasil ditambahkan (CT produk auto dari master product)');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function update($id)
    {
        $id = (int)$id;

        $machineId = (int)$this->request->getPost('machine_id');
        $productId = (int)$this->request->getPost('product_id');

        if ($machineId <= 0 || $productId <= 0) {
            return redirect()->back()->withInput()->with('error', 'Machine & Product wajib dipilih');
        }

        $dup = $this->standardModel
            ->where(['machine_id' => $machineId, 'product_id' => $productId])
            ->where('id !=', $id)
            ->first();
        if ($dup) {
            return redirect()->back()->withInput()->with('error', 'Kombinasi machine & product sudah ada');
        }

        $ctMachine = $this->normalizeMachineCycleTime($machineId, $this->request->getPost('cycle_time_sec'));
        if (!$this->isDieCastingMachine($machineId) && $ctMachine <= 0) {
            return redirect()->back()->withInput()->with('error', 'Cycle time mesin (Machining) wajib > 0');
        }

        $ctProduct = $this->getProductCycleTimes($productId);

        $db = db_connect();
        $db->transBegin();

        try {
            $this->standardModel->update($id, [
                'machine_id'                 => $machineId,
                'product_id'                 => $productId,
                'cycle_time_sec'             => $ctMachine,
                'cycle_time_die_casting_sec' => $ctProduct['cycle_time_die_casting_sec'],
                'cycle_time_machining_sec'   => $ctProduct['cycle_time_machining_sec'],
            ]);

            $this->syncCycleTimeForMachine($machineId, $ctMachine);

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->to('/master/production-standard')
                ->with('success', 'Production standard berhasil diupdate (CT produk auto dari master product)');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function bulkStore()
    {
        $machineId = (int)$this->request->getPost('machine_id');
        $rows      = $this->request->getPost('rows');

        if ($machineId <= 0) return redirect()->back()->withInput()->with('error', 'Machine wajib dipilih');
        if (!$rows || !is_array($rows)) return redirect()->back()->withInput()->with('error', 'Row bulk kosong');

        $ctMachine = $this->normalizeMachineCycleTime($machineId, $this->request->getPost('cycle_time_sec'));
        if (!$this->isDieCastingMachine($machineId) && $ctMachine <= 0) {
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

                $ctProduct = $this->getProductCycleTimes($productId);

                $this->standardModel->insert([
                    'machine_id'                 => $machineId,
                    'product_id'                 => $productId,
                    'cycle_time_sec'             => $ctMachine,
                    'cycle_time_die_casting_sec' => $ctProduct['cycle_time_die_casting_sec'],
                    'cycle_time_machining_sec'   => $ctProduct['cycle_time_machining_sec'],
                ]);
                $inserted++;
            }

            $this->syncCycleTimeForMachine($machineId, $ctMachine);

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->to('/master/production-standard')
                ->with('success', "Bulk add selesai. Inserted: {$inserted}. (CT produk auto dari master product)");
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function bulkUpdate()
    {
        $ids = $this->request->getPost('ids');
        if (!$ids || !is_array($ids)) return redirect()->back()->with('error', 'Tidak ada data dipilih');

        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        if (!$ids) return redirect()->back()->with('error', 'Tidak ada data valid');

        $machineIdNew   = (int)($this->request->getPost('machine_id') ?? 0);
        $cycleRaw       = $this->request->getPost('cycle_time_sec');
        $cycleProvided  = ($cycleRaw !== null && $cycleRaw !== '');

        $db = db_connect();
        $db->transBegin();

        try {
            $rows = $this->standardModel->whereIn('id', $ids)->findAll();
            if (!$rows) throw new \Exception('Data standard tidak ditemukan');

            $machineToSync = [];

            if ($machineIdNew > 0) {
                foreach ($rows as $r) {
                    $this->standardModel->update((int)$r['id'], ['machine_id' => $machineIdNew]);
                }
                $machineToSync[$machineIdNew] = true;
            } else {
                foreach ($rows as $r) {
                    $mid = (int)($r['machine_id'] ?? 0);
                    if ($mid > 0) $machineToSync[$mid] = true;
                }
            }

            // Jika cycle time mesin diisi => sync CT mesin
            if ($cycleProvided) {
                foreach (array_keys($machineToSync) as $mid) {
                    $ctMachine = $this->normalizeMachineCycleTime((int)$mid, $cycleRaw);

                    if (!$this->isDieCastingMachine((int)$mid) && $ctMachine <= 0) {
                        throw new \Exception('Ada mesin Machining pada selection: cycle time wajib > 0');
                    }
                    $this->syncCycleTimeForMachine((int)$mid, (int)$ctMachine);
                }
            }

            // OPTIONAL: refresh CT produk untuk rows yang dipilih (biar selalu update dari master product)
            // Kalau kamu mau selalu refresh CT produk saat bulk edit (meski product tidak berubah), aktifkan ini:
            foreach ($rows as $r) {
                $sid = (int)$r['id'];
                $pid = (int)($r['product_id'] ?? 0);
                if ($pid <= 0) continue;

                $ctProduct = $this->getProductCycleTimes($pid);
                $this->standardModel->update($sid, [
                    'cycle_time_die_casting_sec' => $ctProduct['cycle_time_die_casting_sec'],
                    'cycle_time_machining_sec'   => $ctProduct['cycle_time_machining_sec'],
                ]);
            }

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->to('/master/production-standard')
                ->with('success', 'Bulk edit berhasil (CT mesin sync, CT produk refresh dari master product)');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function bulkDelete()
    {
        $ids = $this->request->getPost('ids');
        if (!$ids || !is_array($ids)) return redirect()->back()->with('error', 'Tidak ada data dipilih');

        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        if (!$ids) return redirect()->back()->with('error', 'Tidak ada data valid');

        $db = db_connect();
        $db->transBegin();

        try {
            $this->standardModel->whereIn('id', $ids)->delete();

            if ($db->transStatus() === false) throw new \Exception('DB error');
            $db->transCommit();

            return redirect()->to('/master/production-standard')
                ->with('success', 'Bulk delete berhasil: ' . count($ids) . ' data');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function delete($id)
    {
        $this->standardModel->delete((int)$id);
        return redirect()->to('/master/production-standard')->with('success', 'Production standard berhasil dihapus');
    }
}
