<?php

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\ProductModel;
use App\Models\CustomerModel;

class ProductController extends BaseController
{
    protected $productModel;
    protected $customerModel;

    public function __construct()
    {
        $this->productModel  = new ProductModel();
        $this->customerModel = new CustomerModel();
    }

    private function isAjaxRequest(): bool
    {
        return $this->request->isAJAX() || $this->request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
    }

    public function index()
    {
        $keyword    = trim((string)$this->request->getGet('keyword'));
        $customerId = $this->request->getGet('customer_id');

        $perPageOptions = [10, 15, 25, 50, 100];
        $perPage = (int)($this->request->getGet('perPage') ?? 15);
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 15;
        }

        $products = $this->productModel
            ->filterProducts($keyword, $customerId)
            ->paginate($perPage, 'products');

        return view('master/product/index', [
            'products'        => $products,
            'pager'           => $this->productModel->pager,
            'customers'       => $this->customerModel->findAll(),
            'keyword'         => $keyword,
            'customerId'      => $customerId,
            'perPage'         => $perPage,
            'perPageOptions'  => $perPageOptions,
        ]);
    }

    public function create()
    {
        return view('master/product/create', [
            'customers' => $this->customerModel->findAll()
        ]);
    }

    public function store()
    {
        $customerId = $this->request->getPost('customer_id');

        if (empty($customerId)) {
            if ($this->isAjaxRequest()) {
                return $this->response->setJSON([
                    'status'  => 'error',
                    'message' => 'Customer wajib dipilih',
                ])->setStatusCode(422);
            }
            return redirect()->back()->withInput()->with('error', 'Customer wajib dipilih');
        }

        // ✅ ambil weight
        $weightAscas    = (float)($this->request->getPost('weight_ascas') ?? 0);
        $weightRunner   = (float)($this->request->getPost('weight_runner') ?? 0);
        $weightMachining= (float)($this->request->getPost('weight_machining') ?? 0);

        // ✅ auto hitung die casting
        $weightDieCasting = $weightAscas + $weightRunner;

        $id = $this->productModel->insert([
            'part_no'            => $this->request->getPost('part_no'),
            'part_name'          => $this->request->getPost('part_name'),
            'part_prod'          => $this->request->getPost('part_prod'),
            'customer_id'        => $customerId,
            'weight_ascas'       => $weightAscas,
            'weight_runner'      => $weightRunner,

            // ✅ tambahan
            'weight_die_casting' => $weightDieCasting,
            'weight_machining'   => $weightMachining,

            'cycle_time'         => $this->request->getPost('cycle_time'),
            'cavity'             => $this->request->getPost('cavity'),
            'efficiency_rate'    => $this->request->getPost('efficiency_rate'),
            'notes'              => $this->request->getPost('notes'),
            'is_active'          => 1
        ]);

        if ($this->isAjaxRequest()) {
            $row = $this->productModel->getOneWithCustomer($id);
            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'Product berhasil ditambahkan',
                'data'    => $row,
            ]);
        }

        return redirect()->to('/master/product')->with('success', 'Product berhasil ditambahkan');
    }

    public function edit($id)
    {
        return view('master/product/edit', [
            'product'   => $this->productModel->find($id),
            'customers' => $this->customerModel->findAll()
        ]);
    }

    public function update($id)
    {
        $customerId = $this->request->getPost('customer_id');

        if (empty($customerId)) {
            if ($this->isAjaxRequest()) {
                return $this->response->setJSON([
                    'status'  => 'error',
                    'message' => 'Customer wajib dipilih',
                ])->setStatusCode(422);
            }
            return redirect()->back()->withInput()->with('error', 'Customer wajib dipilih');
        }

        $weightAscas     = (float)($this->request->getPost('weight_ascas') ?? 0);
        $weightRunner    = (float)($this->request->getPost('weight_runner') ?? 0);
        $weightMachining = (float)($this->request->getPost('weight_machining') ?? 0);

        // ✅ auto hitung die casting
        $weightDieCasting = $weightAscas + $weightRunner;

        $this->productModel->update($id, [
            'part_no'            => $this->request->getPost('part_no'),
            'part_name'          => $this->request->getPost('part_name'),
            'part_prod'          => $this->request->getPost('part_prod'),
            'customer_id'        => $customerId,

            'weight_ascas'       => $weightAscas,
            'weight_runner'      => $weightRunner,

            // ✅ tambahan
            'weight_die_casting' => $weightDieCasting,
            'weight_machining'   => $weightMachining,

            'cycle_time'         => $this->request->getPost('cycle_time'),
            'cavity'             => $this->request->getPost('cavity'),
            'efficiency_rate'    => $this->request->getPost('efficiency_rate'),
            'notes'              => $this->request->getPost('notes'),
        ]);

        if ($this->isAjaxRequest()) {
            $row = $this->productModel->getOneWithCustomer($id);
            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'Product berhasil diupdate',
                'data'    => $row,
            ]);
        }

        return redirect()->to('/master/product')->with('success', 'Product berhasil diupdate');
    }

    public function delete($id)
    {
        $this->productModel->update($id, ['is_active' => 0]);
        return redirect()->to('/master/product')->with('success', 'Product berhasil dihapus');
    }
}
