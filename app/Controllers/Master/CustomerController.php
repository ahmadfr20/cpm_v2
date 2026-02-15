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
                ->like('customer_code', $keyword)        // Accurate code
                ->orLike('customer_code_app', $keyword)   // App code
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
     * ✅ Generate customer_code_app: CUST-0001, CUST-0002, ...
     * Aman dari duplicate dengan loop check + transaksi.
     */
    private function generateCustomerCodeApp(): string
    {
        // Ambil customer_code_app terbesar yang diawali "CUST-"
        $row = $this->customerModel
            ->select('customer_code_app')
            ->like('customer_code_app', 'CUST-', 'after')
            ->orderBy('customer_code_app', 'DESC')
            ->first();

        $lastNumber = 0;
        if ($row && !empty($row['customer_code_app'])) {
            $num = (int) preg_replace('/\D+/', '', (string) $row['customer_code_app']);
            $lastNumber = $num;
        }

        $next = $lastNumber + 1;
        return 'CUST-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }

    public function store()
    {
        $rules = [
            'customer_name' => 'required|min_length[2]|max_length[150]',
            // customer_code (Accurate) bebas, optional
            'customer_code' => 'permit_empty|max_length[30]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $db = db_connect();
        $db->transBegin();

        try {
            // ✅ customer_code_app auto-generate, anti tabrakan
            $codeApp = $this->generateCustomerCodeApp();
            $tries = 0;

            while ($this->customerModel->where('customer_code_app', $codeApp)->first()) {
                $tries++;
                if ($tries > 20) {
                    throw new \Exception('Gagal generate Customer Code App. Silakan coba lagi.');
                }
                $codeApp = $this->generateCustomerCodeApp();
            }

            $this->customerModel->insert([
                // ✅ Accurate code (bebas)
                'customer_code'     => trim((string)$this->request->getPost('customer_code')),
                // ✅ App code (auto)
                'customer_code_app' => $codeApp,
                'customer_name'     => trim((string)$this->request->getPost('customer_name')),
                'is_active'         => (int) ($this->request->getPost('is_active') ?? 1),
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
            'customer_code' => 'permit_empty|max_length[30]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $this->customerModel->update($id, [
            // ✅ Accurate code boleh diubah (bebas)
            'customer_code' => trim((string)$this->request->getPost('customer_code')),
            // ✅ App code TIDAK diubah
            'customer_name' => trim((string)$this->request->getPost('customer_name')),
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
