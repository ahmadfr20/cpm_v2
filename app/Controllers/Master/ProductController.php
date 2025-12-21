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
        $keyword    = $this->request->getGet('keyword');
        $customerId = $this->request->getGet('customer_id');

        return view('master/product/index', [
            'products'  => $this->productModel->getProducts($keyword, $customerId),
            'customers' => $this->customerModel->findAll(),
            'keyword'   => $keyword,
            'customerId'=> $customerId
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
        $this->productModel->insert($this->request->getPost());

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
        $this->productModel->update($id, $this->request->getPost());

        return redirect()->to('/master/product')
            ->with('success', 'Product berhasil diupdate');
    }

    public function delete($id)
    {
        $this->productModel->delete($id);

        return redirect()->to('/master/product')
            ->with('success', 'Product berhasil dihapus');
    }
}
