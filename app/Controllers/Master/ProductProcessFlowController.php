<?php

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\ProductProcessFlowModel;
use App\Models\ProductModel;
use App\Models\ProductionProcessModel;

class ProductProcessFlowController extends BaseController
{
    protected $flowModel;
    protected $productModel;
    protected $processModel;

    public function __construct()
    {
        $this->flowModel    = new ProductProcessFlowModel();
        $this->productModel = new ProductModel();
        $this->processModel = new ProductionProcessModel();
    }

    public function index()
    {
        $perPage   = (int) ($this->request->getGet('per_page') ?? 10);
        $keyword   = $this->request->getGet('keyword');
        $sort      = $this->request->getGet('sort') ?? 'part_no';
        $direction = strtoupper($this->request->getGet('dir') ?? 'ASC');

        $allowedSort = ['part_no', 'part_name'];
        if (!in_array($sort, $allowedSort, true)) $sort = 'part_no';
        $direction = $direction === 'DESC' ? 'DESC' : 'ASC';

        $query = $this->productModel->where('is_active', 1);
        if ($keyword) {
            $query->groupStart()
                ->like('part_no', $keyword)
                ->orLike('part_name', $keyword)
                ->groupEnd();
        }

        $products = $query->orderBy($sort, $direction)->paginate($perPage, 'products');
        $pager    = $this->productModel->pager;

        $processes = $this->processModel->orderBy('id', 'ASC')->findAll();

        // Ambil flow aktif dengan urutan sequence
        $flows = $this->flowModel
            ->where('is_active', 1)
            ->orderBy('sequence', 'ASC')
            ->findAll();

        $flowMap   = [];
        $flowOrder = [];
        foreach ($flows as $f) {
            $pid = (int)$f['product_id'];
            $prc = (int)$f['process_id'];
            $flowMap[$pid][$prc] = true;
            $flowOrder[$pid][]   = $prc;
        }

        return view('master/production_flow/index_side_chip', compact(
            'products',
            'processes',
            'flowMap',
            'flowOrder',
            'pager',
            'perPage',
            'keyword',
            'sort',
            'direction'
        ));
    }

    public function bulkUpdate()
    {
        $productIds   = $this->request->getPost('product_ids') ?? [];
        $selectedPost = $this->request->getPost('flows_selected') ?? [];
        $orderPost    = $this->request->getPost('flows_order') ?? [];

        if (!is_array($productIds) || count($productIds) === 0) {
            return redirect()->back()->with('error', 'Tidak ada data produk yang dikirim.');
        }

        $db = db_connect();
        $db->transBegin();

        try {
            foreach ($productIds as $raw) {
                $productId = (int)$raw;
                if ($productId <= 0) continue;

                // selected[] dari checkbox chip
                $selected = $selectedPost[$productId] ?? [];
                if (!is_array($selected)) $selected = [];

                $selected = array_values(array_unique(array_filter($selected, function ($v) {
                    return ctype_digit((string)$v) && (int)$v > 0;
                })));
                $selected = array_map('intval', $selected);

                // order string dari hidden (hasil drag)
                $orderStr = (string)($orderPost[$productId] ?? '');
                $orderIds = [];
                if ($orderStr !== '') {
                    foreach (array_filter(array_map('trim', explode(',', $orderStr))) as $v) {
                        if (ctype_digit($v) && (int)$v > 0) $orderIds[] = (int)$v;
                    }
                }

                // final order = orderIds yang tercentang + sisa selected yang belum masuk
                $final = [];
                foreach ($orderIds as $oid) {
                    if (in_array($oid, $selected, true)) $final[] = $oid;
                }
                foreach ($selected as $sid) {
                    if (!in_array($sid, $final, true)) $final[] = $sid;
                }

                $this->saveOneProductFlow($productId, $final);
            }

            $db->transCommit();
            return redirect()->back()->with('success', 'Production flow berhasil diperbarui (Bulk Save).');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', 'Gagal update: ' . $e->getMessage());
        }
    }

    public function saveIndividual()
    {
        $productId = (int)$this->request->getPost('product_id');
        $selected  = $this->request->getPost('selected') ?? [];
        $orderStr  = (string)($this->request->getPost('order') ?? '');

        if ($productId <= 0) {
            return $this->response->setJSON(['ok' => false, 'message' => 'Product tidak valid']);
        }

        if (!is_array($selected)) $selected = [];
        $selected = array_values(array_unique(array_filter($selected, function ($v) {
            return ctype_digit((string)$v) && (int)$v > 0;
        })));
        $selected = array_map('intval', $selected);

        $orderIds = [];
        if ($orderStr !== '') {
            foreach (array_filter(array_map('trim', explode(',', $orderStr))) as $v) {
                if (ctype_digit($v) && (int)$v > 0) $orderIds[] = (int)$v;
            }
        }

        $final = [];
        foreach ($orderIds as $oid) {
            if (in_array($oid, $selected, true)) $final[] = $oid;
        }
        foreach ($selected as $sid) {
            if (!in_array($sid, $final, true)) $final[] = $sid;
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $this->saveOneProductFlow($productId, $final);
            $db->transCommit();
            return $this->response->setJSON(['ok' => true, 'message' => 'Flow disimpan']);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    private function saveOneProductFlow(int $productId, array $finalOrder): void
    {
        $existingRows = $this->flowModel->where('product_id', $productId)->findAll();

        $existingMap = [];
        foreach ($existingRows as $row) {
            $existingMap[(int)$row['process_id']] = [
                'id'        => (int)$row['id'],
                'is_active' => (int)$row['is_active'],
            ];
        }

        // deactivate yang tidak dipilih
        foreach ($existingMap as $procId => $info) {
            if (!in_array($procId, $finalOrder, true) && $info['is_active'] === 1) {
                $this->flowModel->update($info['id'], ['is_active' => 0]);
            }
        }

        // activate + update sequence
        $seq = 1;
        foreach ($finalOrder as $procId) {
            if (isset($existingMap[$procId])) {
                $this->flowModel->update($existingMap[$procId]['id'], [
                    'sequence'  => $seq,
                    'is_active' => 1,
                ]);
            } else {
                $this->flowModel->insert([
                    'product_id' => $productId,
                    'process_id' => $procId,
                    'sequence'   => $seq,
                    'is_active'  => 1,
                ]);
            }
            $seq++;
        }
    }
}
