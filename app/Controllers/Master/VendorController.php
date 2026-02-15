<?php

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\VendorModel;

class VendorController extends BaseController
{
    protected VendorModel $vendorModel;

    public function __construct()
    {
        $this->vendorModel = new VendorModel();
    }

    public function index()
    {
        $keyword = trim((string) $this->request->getGet('keyword'));
        $perPage = (int) ($this->request->getGet('perPage') ?? 10);

        $perPageOptions = [10, 25, 50, 100];
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 10;
        }

        $builder = $this->vendorModel->orderBy('id', 'DESC');

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('vendor_code', $keyword)        // accurate code
                ->orLike('vendor_code_app', $keyword)  // app code
                ->orLike('vendor_name', $keyword)
                ->groupEnd();
        }

        $vendors = $builder->paginate($perPage, 'vendors');

        return view('master/vendors/index', [
            'vendors'        => $vendors,
            'pager'          => $this->vendorModel->pager,
            'keyword'        => $keyword,
            'perPage'        => $perPage,
            'perPageOptions' => $perPageOptions,
        ]);
    }

    /**
     * Generate vendor_code_app: VEND-1, VEND-2, ...
     * (auto increment berdasarkan max angka yang sudah ada)
     */
    private function generateVendorCodeApp(): string
    {
        $row = $this->vendorModel
            ->select('vendor_code_app')
            ->like('vendor_code_app', 'VEND-', 'after')
            ->orderBy('vendor_code_app', 'DESC')
            ->first();

        $lastNumber = 0;
        if ($row && !empty($row['vendor_code_app'])) {
            $num = (int) preg_replace('/\D+/', '', (string) $row['vendor_code_app']);
            $lastNumber = $num;
        }

        $next = $lastNumber + 1;
        return 'VEND-' . $next; // kalau mau padding: 'VEND-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }

    public function store()
    {
        $rules = [
            'vendor_code' => 'required|min_length[1]|max_length[50]',   // bebas (accurate)
            'vendor_name' => 'required|min_length[2]|max_length[100]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $db = db_connect();
        $db->transBegin();

        try {
            // auto generate vendor_code_app (antisipasi tabrakan)
            $codeApp = $this->generateVendorCodeApp();
            $tries = 0;

            while ($this->vendorModel->where('vendor_code_app', $codeApp)->first()) {
                $tries++;
                if ($tries > 20) {
                    throw new \Exception('Gagal generate vendor code app. Silakan coba lagi.');
                }
                $codeApp = $this->generateVendorCodeApp();
            }

            $this->vendorModel->insert([
                'vendor_code'     => trim((string)$this->request->getPost('vendor_code')), // manual
                'vendor_code_app' => $codeApp, // auto
                'vendor_name'     => trim((string)$this->request->getPost('vendor_name')),
                'is_active'       => (int) ($this->request->getPost('is_active') ?? 1),
            ]);

            if ($db->transStatus() === false) {
                throw new \Exception('DB error');
            }

            $db->transCommit();
            return redirect()->to('/master/vendor')->with('success', 'Vendor berhasil ditambahkan.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function update($id)
    {
        $vendor = $this->vendorModel->find($id);
        if (!$vendor) {
            return redirect()->to('/master/vendor')->with('error', 'Vendor tidak ditemukan.');
        }

        $rules = [
            'vendor_code' => 'required|min_length[1]|max_length[50]',
            'vendor_name' => 'required|min_length[2]|max_length[100]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $this->vendorModel->update($id, [
            // vendor_code_app tetap (tidak diubah)
            'vendor_code' => trim((string)$this->request->getPost('vendor_code')), // manual
            'vendor_name' => trim((string)$this->request->getPost('vendor_name')),
            'is_active'   => (int) ($this->request->getPost('is_active') ?? ($vendor['is_active'] ?? 1)),
        ]);

        return redirect()->to('/master/vendor')->with('success', 'Vendor berhasil diupdate.');
    }

    public function delete($id)
    {
        $vendor = $this->vendorModel->find($id);
        if (!$vendor) {
            return redirect()->to('/master/vendor')->with('error', 'Vendor tidak ditemukan.');
        }

        $this->vendorModel->delete($id);

        return redirect()->to('/master/vendor')->with('success', 'Vendor berhasil dihapus.');
    }
}
