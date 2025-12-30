<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<!-- ================= HEADER + FILTER ================= -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <i class="bi bi-cpu me-2"></i> Daily Production Die Casting
    </h4>

    <!-- FILTER TANGGAL (GET) -->
    <form method="get" class="d-flex gap-2">
        <input type="date"
               name="date"
               class="form-control form-control-sm"
               value="<?= esc($date) ?>">
        <button class="btn btn-sm btn-primary">
            <i class="bi bi-search"></i>
        </button>
    </form>
</div>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success">
    <?= session()->getFlashdata('success') ?>
</div>
<?php endif; ?>

<?php if (empty($data)): ?>
    <div class="alert alert-warning text-center">
        <i class="bi bi-exclamation-triangle"></i>
        Tidak ada schedule Die Casting pada tanggal
        <strong><?= date('d-m-Y', strtotime($date)) ?></strong>
    </div>
<?php else: ?>

<!-- ================= FORM SAVE PRODUCTION ================= -->
<form method="post" action="/die-casting/production/store">
<input type="hidden" name="production_date" value="<?= esc($date) ?>">

<div class="table-responsive">
<table class="table table-bordered table-sm align-middle text-center">

<thead class="table-light">
<tr>
    <th rowspan="2">MESIN</th>
    <?php foreach ($shifts as $s): ?>
        <th colspan="7"><?= strtoupper($s['shift_name']) ?></th>
    <?php endforeach ?>
</tr>
<tr>
    <?php foreach ($shifts as $s): ?>
        <th>Part</th>
        <th>Target</th>
        <th>P</th>
        <th>A</th>
        <th>NG</th>
        <th>Kg</th>
        <th>Status</th>
    <?php endforeach ?>
</tr>
</thead>

<tbody>
<?php $i = 0; ?>
<?php foreach ($data as $machine => $shiftRows): ?>
<tr>
    <td><strong><?= esc($machine) ?></strong></td>

    <?php foreach ($shifts as $s): ?>
        <?php $r = $shiftRows[$s['id']] ?? null; ?>

        <?php if (!$r): ?>
            <td colspan="7" class="bg-secondary text-white">OFF</td>
        <?php else: ?>

            <!-- HIDDEN IDENTITAS -->
            <input type="hidden" name="items[<?= $i ?>][shift_id]" value="<?= $s['id'] ?>">
            <input type="hidden" name="items[<?= $i ?>][machine_id]" value="<?= $r['machine_id'] ?>">
            <input type="hidden" name="items[<?= $i ?>][product_id]" value="<?= $r['product_id'] ?>">

            <td><?= esc($r['part_name']) ?></td>
            <td><?= $r['target_per_shift'] ?></td>

            <td>
                <input type="number"
                       name="items[<?= $i ?>][qty_p]"
                       class="form-control form-control-sm"
                       value="<?= $r['qty_p'] ?>">
            </td>

            <td>
                <input type="number"
                       name="items[<?= $i ?>][qty_a]"
                       class="form-control form-control-sm"
                       value="<?= $r['qty_a'] ?>">
            </td>

            <td>
                <input type="number"
                       name="items[<?= $i ?>][qty_ng]"
                       class="form-control form-control-sm"
                       value="<?= $r['qty_ng'] ?>">
            </td>

            <td>
                <input type="number"
                       step="0.01"
                       name="items[<?= $i ?>][weight_kg]"
                       class="form-control form-control-sm"
                       value="<?= $r['weight_kg'] ?>">
            </td>

            <?php
                $status = 'Trial';
                if ($r['qty_a'] > 0 && $r['qty_a'] >= ($r['target_per_shift'] * 0.8)) {
                    $status = 'Normal';
                } elseif ($r['qty_a'] > 0) {
                    $status = 'Recovery';
                }
            ?>
            <td>
                <span class="badge bg-<?= 
                    $status === 'Normal' ? 'success' :
                    ($status === 'Recovery' ? 'warning' : 'info')
                ?>">
                    <?= $status ?>
                </span>
            </td>

            <?php $i++; ?>
        <?php endif ?>
    <?php endforeach ?>
</tr>
<?php endforeach ?>
</tbody>

<!-- ================= TOTAL KG / SHIFT ================= -->
<tfoot class="table-warning fw-bold text-center">
<tr>
    <td>TOTAL KG</td>
    <?php foreach ($shifts as $s): ?>
        <td colspan="5"></td>
        <td><?= number_format($totalKg[$s['id']] ?? 0, 2) ?></td>
        <td></td>
    <?php endforeach ?>
</tr>
</tfoot>

</table>
</div>

<button class="btn btn-success mt-3">
    <i class="bi bi-save"></i> Save Production
</button>

</form>
<?php endif; ?>

<?= $this->endSection() ?>
