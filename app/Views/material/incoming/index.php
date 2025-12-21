<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>INCOMING – GOOD RECEIVE</h4>

<form method="post" action="/material/incoming/store">

<div class="row mb-3">
    <div class="col-md-3">
        <label>Date</label>
        <input class="form-control" value="<?= date('Y-m-d') ?>" readonly>
    </div>

    <div class="col-md-3">
        <label>Time</label>
        <input class="form-control" value="<?= date('H:i:s') ?>" readonly>
    </div>

    <div class="col-md-3">
        <label>Shift</label>
        <select name="shift_id" class="form-control">
            <?php foreach ($shifts as $s): ?>
            <option value="<?= $s['id'] ?>"><?= $s['shift_name'] ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-3">
        <label>PO #</label>
        <input name="po_number" class="form-control" required>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-6">
        <label>Supplier / Vendor</label>
        <select name="supplier_id" class="form-control">
            <?php foreach ($vendors as $v): ?>
            <option value="<?= $v['id'] ?>"><?= $v['customer_name'] ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-6">
        <label>DO #</label>
        <input name="do_number" class="form-control">
    </div>
</div>

<table class="table table-bordered table-sm">
<thead>
<tr>
    <th>No</th>
    <th>Part Number</th>
    <th>Part Name</th>
    <th>QTY Received</th>
    <th>QTY Return</th>
</tr>
</thead>
<tbody>

<?php foreach ($products as $i => $p): ?>
<tr>
    <td><?= $i+1 ?></td>
    <td><?= $p['part_no'] ?></td>
    <td><?= $p['part_name'] ?></td>
    <td>
        <input type="number" name="items[<?= $i ?>][qty_received]"
               class="form-control form-control-sm">
    </td>
    <td>
        <input type="number" name="items[<?= $i ?>][qty_return]"
               class="form-control form-control-sm" value="0">
    </td>
    <input type="hidden" name="items[<?= $i ?>][product_id]" value="<?= $p['id'] ?>">
</tr>
<?php endforeach; ?>

</tbody>
</table>

<button class="btn btn-primary">Save</button>

</form>

<?= $this->endSection() ?>
