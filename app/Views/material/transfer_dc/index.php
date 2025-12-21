<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>RAW MATERIAL – TRANSFER TO DIE CASTING</h4>

<form method="post" action="/material/transfer-dc/store">

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
</div>

<table class="table table-bordered table-sm">
<thead>
<tr>
    <th>No</th>
    <th>Part Number</th>
    <th>Part Name</th>
    <th>QTY</th>
</tr>
</thead>
<tbody>

<?php foreach ($products as $i => $p): ?>
<tr>
    <td><?= $i+1 ?></td>
    <td><?= $p['part_no'] ?></td>
    <td><?= $p['part_name'] ?></td>
    <td>
        <input type="number" name="items[<?= $i ?>][qty_transfer]"
               class="form-control form-control-sm">
    </td>
    <input type="hidden" name="items[<?= $i ?>][product_id]" value="<?= $p['id'] ?>">
</tr>
<?php endforeach; ?>

</tbody>
</table>

<button class="btn btn-primary">Transfer</button>

</form>

<?= $this->endSection() ?>
