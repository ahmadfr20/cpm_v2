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

        $products = $this->productModel
            ->filterProducts($keyword, $customerId)
            ->paginate(15, 'products');

        return view('master/product/index', [
            'products'   => $products,
            'pager'      => $this->productModel->pager, // 🔥 WAJIB
            'customers'  => $this->customerModel->findAll(),
            'keyword'    => $keyword,
            'customerId' => $customerId
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
        $this->productModel->insert([
            'part_no'        => $this->request->getPost('part_no'),
            'part_name'      => $this->request->getPost('part_name'),
            'customer_id'    => $this->request->getPost('customer_id'),
            'weight_ascas'   => $this->request->getPost('weight_ascas'),
            'weight_runner'  => $this->request->getPost('weight_runner'),
            'notes'          => $this->request->getPost('notes'),
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
            'part_no'        => $this->request->getPost('part_no'),
            'part_name'      => $this->request->getPost('part_name'),
            'customer_id'    => $this->request->getPost('customer_id'),
            'weight_ascas'   => $this->request->getPost('weight_ascas'),
            'weight_runner'  => $this->request->getPost('weight_runner'),
            'notes'          => $this->request->getPost('notes'),
        ]);

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
