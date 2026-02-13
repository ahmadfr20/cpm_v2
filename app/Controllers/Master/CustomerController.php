<?php

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\CustomerModel;

class CustomerController extends BaseController
{
    protected CustomerModel $customerModel;

    public function __construct()
    {
        $this->customerModel = new CustomerModel();
    }

    public function index()
    {
        $keyword = trim((string) $this->request->getGet('keyword'));
        $perPage = (int) ($this->request->getGet('perPage') ?? 10);

        $perPageOptions = [10, 25, 50, 100];
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 10;
        }

        $builder = $this->customerModel->orderBy('id', 'DESC');

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('customer_code', $keyword)
                ->orLike('customer_name', $keyword)
                ->groupEnd();
        }

        $customers = $builder->paginate($perPage, 'customers');

        return view('master/customer/index', [
            'customers'      => $customers,
            'pager'          => $this->customerModel->pager,
            'keyword'        => $keyword,
            'perPage'        => $perPage,
            'perPageOptions' => $perPageOptions,
        ]);
    }

    /**
     * Generate customer_code: CUS-0001, CUS-0002, ...
     * Aman dari duplicate dengan loop check + transaksi.
     */
    private function generateCustomerCode(): string
    {
        // Ambil customer_code terbesar yang diawali "CUS-"
        $row = $this->customerModel
            ->select('customer_code')
            ->like('customer_code', 'CUS-', 'after')
            ->orderBy('customer_code', 'DESC')
            ->first();

        $lastNumber = 0;
        if ($row && !empty($row['customer_code'])) {
            // ambil angka di belakang prefix
            $num = (int) preg_replace('/\D+/', '', (string) $row['customer_code']);
            $lastNumber = $num;
        }

        // generate berikutnya
        $next = $lastNumber + 1;
        return 'CUS-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }

    public function store()
    {
        $rules = [
            'customer_name' => 'required|min_length[2]|max_length[150]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $db = db_connect();
        $db->transBegin();

        try {
            // generate code (antisipasi tabrakan: cek ulang)
            $code = $this->generateCustomerCode();
            $tries = 0;

            while ($this->customerModel->where('customer_code', $code)->first()) {
                $tries++;
                if ($tries > 10) {
                    throw new \Exception('Gagal generate customer code. Silakan coba lagi.');
                }
                $code = $this->generateCustomerCode();
            }

            $this->customerModel->insert([
                'customer_code' => $code,
                'customer_name' => trim($this->request->getPost('customer_name')),
                'is_active'     => (int) ($this->request->getPost('is_active') ?? 1),
            ]);

            if ($db->transStatus() === false) {
                throw new \Exception('DB error');
            }

            $db->transCommit();
            return redirect()->to('/master/customer')->with('success', 'Customer berhasil ditambahkan');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function update($id)
    {
        $customer = $this->customerModel->find($id);
        if (!$customer) {
            return redirect()->to('/master/customer')->with('error', 'Customer tidak ditemukan');
        }

        $rules = [
            'customer_name' => 'required|min_length[2]|max_length[150]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $this->customerModel->update($id, [
            // customer_code tetap (tidak boleh diubah)
            'customer_name' => trim($this->request->getPost('customer_name')),
            'is_active'     => (int) ($this->request->getPost('is_active') ?? ($customer['is_active'] ?? 1)),
        ]);

        return redirect()->to('/master/customer')->with('success', 'Customer berhasil diupdate');
    }

    public function delete($id)
    {
        $customer = $this->customerModel->find($id);
        if (!$customer) {
            return redirect()->to('/master/customer')->with('error', 'Customer tidak ditemukan');
        }

        $this->customerModel->delete($id);

        return redirect()->to('/master/customer')->with('success', 'Customer berhasil dihapus');
    }
}
