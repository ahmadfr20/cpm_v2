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

public function index()
{
    $keyword    = trim((string)$this->request->getGet('keyword'));
    $customerId = $this->request->getGet('customer_id');

    // ✅ perPage filter
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

        // 🔥 VALIDASI FK
        if (empty($customerId)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Customer wajib dipilih');
        }

        $this->productModel->insert([
            'part_no'          => $this->request->getPost('part_no'),
            'part_name'        => $this->request->getPost('part_name'),
            'customer_id'      => $customerId,
            'weight_ascas'     => $this->request->getPost('weight_ascas'),
            'weight_runner'    => $this->request->getPost('weight_runner'),
            'cycle_time'       => $this->request->getPost('cycle_time'),
            'cavity'           => $this->request->getPost('cavity'),
            'efficiency_rate'  => $this->request->getPost('efficiency_rate'),
            'notes'            => $this->request->getPost('notes'),
            'is_active'        => 1
        ]);

        return redirect()->to('/master/product')
            ->with('success', 'Product berhasil ditambahkan');
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
        $this->productModel->update($id, [
            'part_no'           => $this->request->getPost('part_no'),
            'part_name'         => $this->request->getPost('part_name'),
            'customer_id'       => $this->request->getPost('customer_id'),

            'cycle_time'    => $this->request->getPost('cycle_time'),
            'cavity'            => $this->request->getPost('cavity'),
            'efficiency_rate'   => $this->request->getPost('efficiency_rate'),

            'weight_ascas'      => $this->request->getPost('weight_ascas'),
            'weight_runner'     => $this->request->getPost('weight_runner'),
            'notes'             => $this->request->getPost('notes'),
        ]);

        return redirect()->to('/master/product')
            ->with('success', 'Product berhasil diupdate');
    }

    public function delete($id)
    {
        $this->productModel->update($id, ['is_active' => 0]);

        return redirect()->to('/master/product')
            ->with('success', 'Product berhasil dihapus');
    }
}
