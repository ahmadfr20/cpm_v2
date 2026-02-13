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
                ->like('vendor_code', $keyword)
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
     * Generate vendor_code: VEN-0001, VEN-0002, ...
     */
    private function generateVendorCode(): string
    {
        $row = $this->vendorModel
            ->select('vendor_code')
            ->like('vendor_code', 'VEN-', 'after')
            ->orderBy('vendor_code', 'DESC')
            ->first();

        $lastNumber = 0;
        if ($row && !empty($row['vendor_code'])) {
            $num = (int) preg_replace('/\D+/', '', (string) $row['vendor_code']);
            $lastNumber = $num;
        }

        $next = $lastNumber + 1;
        return 'VEN-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }

    public function store()
    {
        $rules = [
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
            $code = $this->generateVendorCode();

            // antisipasi race condition (cek ulang)
            $tries = 0;
            while ($this->vendorModel->where('vendor_code', $code)->first()) {
                $tries++;
                if ($tries > 10) {
                    throw new \Exception('Gagal generate vendor code. Silakan coba lagi.');
                }
                $code = $this->generateVendorCode();
            }

            $this->vendorModel->insert([
                'vendor_code' => $code,
                'vendor_name' => trim((string) $this->request->getPost('vendor_name')),
                'is_active'   => (int) ($this->request->getPost('is_active') ?? 1),
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
            'vendor_name' => 'required|min_length[2]|max_length[100]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $this->vendorModel->update($id, [
            // vendor_code tetap (tidak diubah)
            'vendor_name' => trim((string) $this->request->getPost('vendor_name')),
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
