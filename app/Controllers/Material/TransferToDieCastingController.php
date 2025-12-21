<?php

namespace App\Controllers\Material;

use App\Controllers\BaseController;
use App\Models\RawMaterialTransferModel;
use App\Models\RawMaterialTransferItemModel;
use App\Models\ProductModel;
use App\Models\ShiftModel;

class TransferToDieCastingController extends BaseController
{
    protected $headerModel;
    protected $itemModel;

    public function __construct()
    {
        $this->headerModel = new RawMaterialTransferModel();
        $this->itemModel   = new RawMaterialTransferItemModel();
    }

    public function index()
    {
        return view('material/transfer_dc/index', [
            'products' => model(ProductModel::class)->findAll(),
            'shifts'   => model(ShiftModel::class)->findAll(),
        ]);
    }

    public function store()
    {
        $db = db_connect();
        $db->transStart();

        $headerId = $this->headerModel->insert([
            'transfer_date' => date('Y-m-d'),
            'shift_id'      => $this->request->getPost('shift_id'),
        ]);

        foreach ($this->request->getPost('items') as $item) {
            if ($item['qty_transfer'] <= 0) continue;

            $this->itemModel->insert([
                'raw_material_transfer_id' => $headerId,
                'product_id' => $item['product_id'],
                'qty_transfer' => $item['qty_transfer'],
            ]);
        }

        $db->transComplete();

        return redirect()->to('/material/transfer-dc')
            ->with('success','Material berhasil ditransfer ke Die Casting');
    }
}
