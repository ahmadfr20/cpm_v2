<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-2 fw-bold">SHOT BLASTING</h4>
<h5 class="mb-4 text-muted">RECEIVING FROM VENDOR</h5>

<form method="post" action="/shot-blasting/receiving/store">
<?= csrf_field() ?>

<table class="table table-bordered table-sm align-middle text-center">
<thead class="table-secondary">
<tr>
    <th style="width:60px">No</th>
    <th>Date</th>
    <th>Shift</th>
    <th>Vendor</th>
    <th>Part No</th>
    <th>Part Name</th>
    <th>Qty Delivery</th>
    <th>Qty Received</th>
    <th style="width:140px">Qty Receive</th>
</tr>
</thead>
<tbody>

<?php if (empty($deliveries)): ?>
<tr>
    <td colspan="9" class="text-muted">Tidak ada delivery outstanding</td>
</tr>
<?php endif ?>

<?php foreach ($deliveries as $i => $d): ?>
<tr>
    <td>
        <?= ($i + 1) + ($pager->getCurrentPage() - 1) * $pager->getPerPage() ?>
    </td>

    <td><?= esc($d['transaction_date']) ?></td>

    <td><?= esc($d['shift_name']) ?></td>

    <td><?= esc($d['supplier_name']) ?></td>

    <td><?= esc($d['part_no']) ?></td>

    <td class="text-start"><?= esc($d['part_name']) ?></td>

    <td><?= esc($d['qty_out']) ?></td>

    <td><?= esc($d['qty_in']) ?></td>

    <td>
        <input type="number"
               name="items[<?= $i ?>][qty]"
               class="form-control form-control-sm text-center"
               min="0"
               max="<?= esc($d['qty_out'] - $d['qty_in']) ?>">

        <input type="hidden"
               name="items[<?= $i ?>][product_id]"
               value="<?= $d['product_id'] ?>">

        <input type="hidden"
               name="items[<?= $i ?>][shift_id]"
               value="<?= $d['shift_id'] ?>">

        <input type="hidden"
               name="items[<?= $i ?>][supplier_id]"
               value="<?= $d['supplier_id'] ?>">
    </td>
</tr>
<?php endforeach ?>

</tbody>
</table>

<?= $pager->links('default', 'bootstrap_pagination') ?>

<button class="btn btn-primary btn-sm mt-3">
    <i class="bi bi-box-arrow-in-down"></i> Simpan Receiving
</button>

</form>

<?= $this->endSection() ?>
