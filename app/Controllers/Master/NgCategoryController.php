<?php

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\NgCategoryModel;

class NgCategoryController extends BaseController
{
    protected NgCategoryModel $model;

    public function __construct()
    {
        $this->model = new NgCategoryModel();
    }

    public function index()
    {
        $keyword = trim((string) $this->request->getGet('keyword'));
        $process = trim((string) $this->request->getGet('process'));
        $status  = trim((string) $this->request->getGet('status')); // '', '1', '0'
        $perPage = (int) ($this->request->getGet('perPage') ?? 10);

        $perPageOptions = [10, 25, 50, 100];
        if (!in_array($perPage, $perPageOptions, true)) $perPage = 10;

        // untuk dropdown process
        $processOptions = $this->model->select('process_name')
            ->where('process_name IS NOT NULL', null, false)
            ->groupBy('process_name')
            ->orderBy('process_name', 'ASC')
            ->findAll();

        $processOptions = array_values(array_filter(array_map(
            fn($r) => (string)($r['process_name'] ?? ''),
            $processOptions
        )));

        $builder = $this->model->orderBy('process_name', 'ASC')->orderBy('ng_code', 'ASC');

        if ($process !== '') {
            $builder->where('process_name', $process);
        }

        if ($status === '1' || $status === '0') {
            $builder->where('is_active', (int)$status);
        }

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('ng_code', $keyword)
                ->orLike('ng_name', $keyword)
                ->orLike('process_name', $keyword)
                ->groupEnd();
        }

        $rows = $builder->paginate($perPage, 'ngcats');

        return view('master/ng_categories/index', [
            'rows'           => $rows,
            'pager'          => $this->model->pager,
            'keyword'        => $keyword,
            'process'        => $process,
            'status'         => $status,
            'perPage'        => $perPage,
            'perPageOptions' => $perPageOptions,
            'processOptions' => $processOptions,
        ]);
    }

    public function store()
    {
        $rules = [
            'process_name' => 'required|min_length[2]|max_length[60]',
            'ng_code'      => 'required|integer',
            'ng_name'      => 'required|min_length[2]|max_length[120]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $process = trim((string)$this->request->getPost('process_name'));
            $code    = (int)$this->request->getPost('ng_code');
            $name    = trim((string)$this->request->getPost('ng_name'));
            $active  = (int)($this->request->getPost('is_active') ?? 1);

            // unique per process (umumnya ng_code unik per process)
            $exists = $this->model
                ->where('process_name', $process)
                ->where('ng_code', $code)
                ->first();
            if ($exists) {
                throw new \Exception("NG Code {$code} untuk process {$process} sudah ada.");
            }

            $this->model->insert([
                'process_name' => $process,
                'ng_code'      => $code,
                'ng_name'      => $name,
                'is_active'    => $active,
            ]);

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return redirect()->to('/master/ng-categories')->with('success', 'NG Category berhasil ditambahkan.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function update($id)
    {
        $id = (int)$id;
        $row = $this->model->find($id);
        if (!$row) {
            return redirect()->to('/master/ng-categories')->with('error', 'Data tidak ditemukan.');
        }

        $rules = [
            'process_name' => 'required|min_length[2]|max_length[60]',
            'ng_code'      => 'required|integer',
            'ng_name'      => 'required|min_length[2]|max_length[120]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $process = trim((string)$this->request->getPost('process_name'));
            $code    = (int)$this->request->getPost('ng_code');
            $name    = trim((string)$this->request->getPost('ng_name'));
            $active  = (int)($this->request->getPost('is_active') ?? ($row['is_active'] ?? 1));

            // cek unique per process kecuali dirinya
            $dup = $this->model
                ->where('process_name', $process)
                ->where('ng_code', $code)
                ->where('id !=', $id)
                ->first();
            if ($dup) {
                throw new \Exception("NG Code {$code} untuk process {$process} sudah ada.");
            }

            $this->model->update($id, [
                'process_name' => $process,
                'ng_code'      => $code,
                'ng_name'      => $name,
                'is_active'    => $active,
            ]);

            if ($db->transStatus() === false) throw new \Exception('DB error');

            $db->transCommit();
            return redirect()->to('/master/ng-categories')->with('success', 'NG Category berhasil diupdate.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function delete($id)
    {
        $id = (int)$id;
        $row = $this->model->find($id);
        if (!$row) {
            return redirect()->to('/master/ng-categories')->with('error', 'Data tidak ditemukan.');
        }

        // optional: kalau dipakai di detail NG, biasanya boleh delete; kalau mau aman, bisa cek relasi dulu
        $this->model->delete($id);

        return redirect()->to('/master/ng-categories')->with('success', 'NG Category berhasil dihapus.');
    }
}
