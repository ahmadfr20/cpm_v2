<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">
    <i class="bi bi-cpu"></i> Daily Schedule Produciton Casting
</h4>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success">
    <?= session()->getFlashdata('success') ?>
</div>
<?php endif ?>

<form method="post" action="/die-casting/production/store">

<div class="row mb-3">
    <div class="col-md-3">
        <label>Tanggal</label>
        <input class="form-control" value="<?= $date ?>" readonly>
    </div>

    <div class="col-md-3">
        <label>Shift</label>
        <select name="shift_id" class="form-select" required>
            <?php foreach ($shifts as $s): ?>
                <option value="<?= $s['id'] ?>"><?= $s['shift_name'] ?></option>
            <?php endforeach ?>
        </select>
    </div>
</div>

<div class="table-responsive">
<table class="table table-bordered table-sm align-middle text-center">
    <thead class="table-dark">
        <tr>
            <th>No</th>
            <th style="min-width:180px">Machine</th>
            <th style="min-width:260px">Part Name</th>
            <th>P</th>
            <th>A</th>
            <th>NG</th>
            <th>Weight (Kg)</th>
        </tr>
    </thead>
    <tbody>
        <?php for ($i = 0; $i < 10; $i++): ?>
        <tr>
            <td><?= $i + 1 ?></td>

            <!-- MACHINE -->
            <td class="text-start">
                <select name="items[<?= $i ?>][machine_id]" class="form-select form-select-sm">
                    <option value="">-- machine --</option>
                    <?php foreach ($machines as $m): ?>
                        <option value="<?= $m['id'] ?>">
                            <?= $m['machine_name'] ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </td>

            <!-- PART -->
            <td class="text-start">
                <select name="items[<?= $i ?>][product_id]" class="form-select form-select-sm">
                    <option value="">-- part --</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>">
                            <?= $p['part_no'] ?> - <?= $p['part_name'] ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </td>

            <td><input type="number" min="0" name="items[<?= $i ?>][qty_p]" class="form-control form-control-sm"></td>
            <td><input type="number" min="0" name="items[<?= $i ?>][qty_a]" class="form-control form-control-sm"></td>
            <td><input type="number" min="0" name="items[<?= $i ?>][qty_ng]" class="form-control form-control-sm"></td>
            <td><input type="number" step="0.01" name="items[<?= $i ?>][weight_kg]" class="form-control form-control-sm"></td>
        </tr>
        <?php endfor ?>
    </tbody>
</table>
</div>

<button class="btn btn-success mt-3">
    <i class="bi bi-save"></i> Save Production
</button>

</form>

<?= $this->endSection() ?>
