<?php

namespace App\Controllers\Material;

use App\Controllers\BaseController;
use App\Models\RawMaterialReceiveModel;
use App\Models\RawMaterialReceiveItemModel;
use App\Models\ProductModel;
use App\Models\ShiftModel;
use App\Models\CustomerModel;

class IncomingController extends BaseController
{
    protected $headerModel;
    protected $itemModel;

    public function __construct()
    {
        $this->headerModel = new RawMaterialReceiveModel();
        $this->itemModel   = new RawMaterialReceiveItemModel();
    }

    public function index()
    {
        return view('material/incoming/index', [
            'products' => model(ProductModel::class)->findAll(),
            'shifts'   => model(ShiftModel::class)->findAll(),
            'vendors'  => model(CustomerModel::class)->findAll(), // supplier
        ]);
    }

    public function store()
    {
        $db = db_connect();
        $db->transStart();

        $headerId = $this->headerModel->insert([
            'receive_date' => date('Y-m-d'),
            'shift_id'     => $this->request->getPost('shift_id'),
            'po_number'    => $this->request->getPost('po_number'),
            'supplier_id'  => $this->request->getPost('supplier_id'),
            'do_number'    => $this->request->getPost('do_number'),
        ]);

        foreach ($this->request->getPost('items') as $item) {
            if ($item['qty_received'] <= 0) continue;

            $this->itemModel->insert([
                'raw_material_receive_id' => $headerId,
                'product_id' => $item['product_id'],
                'qty_received' => $item['qty_received'],
                'qty_return'   => $item['qty_return'] ?? 0,
            ]);
        }

        $db->transComplete();

        return redirect()->to('/material/incoming')
            ->with('success','Incoming material berhasil disimpan');
    }
}
