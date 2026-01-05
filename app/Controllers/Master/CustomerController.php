<?php

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\CustomerModel;

class CustomerController extends BaseController
{
    protected $customerModel;

    public function __construct()
    {
        $this->customerModel = new CustomerModel();
    }

    public function index()
    {
        $keyword = $this->request->getGet('keyword');

        return view('master/customer/index', [
            'customers' => $this->customerModel->getCustomers($keyword),
            'keyword'   => $keyword
        ]);
    }

    public function create()
    {
        return view('master/customer/create');
    }

    public function store()
    {
        $this->customerModel->insert([
            'customer_code' => $this->request->getPost('customer_code'),
            'customer_name' => $this->request->getPost('customer_name'),
            'address'       => $this->request->getPost('address'),
            'phone'         => $this->request->getPost('phone'),
            'email'         => $this->request->getPost('email'),
        ]);

        return redirect()->to('/master/customer')
            ->with('success', 'Customer berhasil ditambahkan');
    }

    public function edit($id)
    {
        return view('master/customer/edit', [
            'customer' => $this->customerModel->find($id)
        ]);
    }

    public function update($id)
    {
        $this->customerModel->update($id, [
            'customer_code' => $this->request->getPost('customer_code'),
            'customer_name' => $this->request->getPost('customer_name'),
            'address'       => $this->request->getPost('address'),
            'phone'         => $this->request->getPost('phone'),
            'email'         => $this->request->getPost('email'),
        ]);

        return redirect()->to('/master/customer')
            ->with('success', 'Customer berhasil diupdate');
    }

    public function delete($id)
    {
        $this->customerModel->delete($id);

        return redirect()->to('/master/customer')
            ->with('success', 'Customer berhasil dihapus');
    }
}
