<?php

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\DowntimeCategoryModel;

class DowntimeCategoryController extends BaseController
{
    protected DowntimeCategoryModel $model;

    public function __construct()
    {
        $this->model = new DowntimeCategoryModel();
    }

    public function index()
    {
        $keyword   = trim((string) $this->request->getGet('keyword'));
        $processId = $this->request->getGet('process_id');
        $status    = trim((string) $this->request->getGet('status'));
        $perPage   = (int) ($this->request->getGet('perPage') ?? 10);

        $perPageOptions = [10, 25, 50, 100];
        if (!in_array($perPage, $perPageOptions, true)) $perPage = 10;

        $db = db_connect();
        
        $processes = $db->table('production_processes')
                        ->orderBy('process_name', 'ASC')
                        ->get()
                        ->getResultArray();

        $builder = $this->model
            ->select('downtime_categories.*, production_processes.process_name')
            ->join('production_processes', 'production_processes.id = downtime_categories.process_id', 'left')
            ->orderBy('production_processes.process_name', 'ASC')
            ->orderBy('downtime_categories.downtime_code', 'ASC');

        if ($processId !== '' && $processId !== null) {
            $builder->where('downtime_categories.process_id', (int)$processId);
        }

        if ($status === '1' || $status === '0') {
            $builder->where('downtime_categories.is_active', (int)$status);
        }

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('downtime_categories.downtime_code', $keyword)
                ->orLike('downtime_categories.downtime_name', $keyword)
                ->orLike('production_processes.process_name', $keyword)
                ->groupEnd();
        }

        $rows = $builder->paginate($perPage, 'downtimecats');

        return view('master/downtime_categories/index', [
            'rows'           => $rows,
            'pager'          => $this->model->pager,
            'keyword'        => $keyword,
            'processId'      => $processId,
            'status'         => $status,
            'perPage'        => $perPage,
            'perPageOptions' => $perPageOptions,
            'processes'      => $processes,
        ]);
    }

    public function store()
    {
        $rules = [
            'process_id'    => 'required|integer',
            'downtime_code' => 'required|integer',
            'downtime_name' => 'required|min_length[2]|max_length[120]',
            'value'         => 'required|integer|greater_than_equal_to[0]', // Tambahan rules value
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $processId = (int)$this->request->getPost('process_id');
            $code      = (int)$this->request->getPost('downtime_code');
            $name      = trim((string)$this->request->getPost('downtime_name'));
            $value     = (int)($this->request->getPost('value') ?? 10); // Menangkap value (default 10)
            $active    = (int)($this->request->getPost('is_active') ?? 1);

            $exists = $this->model
                ->where('process_id', $processId)
                ->where('downtime_code', $code)
                ->first();
                
            if ($exists) {
                throw new \Exception("Downtime Code {$code} untuk proses ini sudah ada.");
            }

            $this->model->insert([
                'process_id'    => $processId,
                'downtime_code' => $code,
                'downtime_name' => $name,
                'value'         => $value, // Simpan value
                'is_active'     => $active,
            ]);

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return redirect()->to('/master/downtime-categories')->with('success', 'Downtime Category berhasil ditambahkan.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function update($id)
    {
        $id = (int)$id;
        $row = $this->model->find($id);
        if (!$row) return redirect()->to('/master/downtime-categories')->with('error', 'Data tidak ditemukan.');

        $rules = [
            'process_id'    => 'required|integer',
            'downtime_code' => 'required|integer',
            'downtime_name' => 'required|min_length[2]|max_length[120]',
            'value'         => 'required|integer|greater_than_equal_to[0]', // Tambahan rules value
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $processId = (int)$this->request->getPost('process_id');
            $code      = (int)$this->request->getPost('downtime_code');
            $name      = trim((string)$this->request->getPost('downtime_name'));
            $value     = (int)($this->request->getPost('value') ?? 10); // Menangkap value
            $active    = (int)($this->request->getPost('is_active') ?? ($row['is_active'] ?? 1));

            $dup = $this->model
                ->where('process_id', $processId)
                ->where('downtime_code', $code)
                ->where('id !=', $id)
                ->first();
                
            if ($dup) {
                throw new \Exception("Downtime Code {$code} untuk proses ini sudah ada.");
            }

            $this->model->update($id, [
                'process_id'    => $processId,
                'downtime_code' => $code,
                'downtime_name' => $name,
                'value'         => $value, // Update value
                'is_active'     => $active,
            ]);

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return redirect()->to('/master/downtime-categories')->with('success', 'Downtime Category berhasil diupdate.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function delete($id)
    {
        $id = (int)$id;
        $row = $this->model->find($id);
        if (!$row) return redirect()->to('/master/downtime-categories')->with('error', 'Data tidak ditemukan.');

        $this->model->delete($id);
        return redirect()->to('/master/downtime-categories')->with('success', 'Downtime Category berhasil dihapus.');
    }
}