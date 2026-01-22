<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">MACHINING – LEAK TEST PRODUCTION PER SHIFT</h4>

<div class="mb-3">
    <strong>Tanggal:</strong> <?= esc($date) ?><br>
    <strong>Operator:</strong> <?= esc($operator) ?>
</div>

<form method="get" class="mb-3" style="max-width:220px">
    <label class="fw-bold">Tanggal Produksi</label>
    <input type="date"
           name="date"
           value="<?= esc($date) ?>"
           class="form-control"
           onchange="this.form.submit()">
</form>

<?php foreach ($shifts as $shift): ?>

<h5 class="mt-4 mb-2"><?= esc($shift['shift_name']) ?></h5>

<table class="table table-bordered table-sm align-middle text-center">
<thead class="table-secondary">
<tr>
    <th>Machine</th>
    <th>Part</th>
    <th>Target Shift</th>
    <th>OK</th>
    <th>NG</th>
    <th>Total</th>
    <th>Efficiency</th>
</tr>
</thead>

<tbody>
<?php if (empty($shift['rows'])): ?>
<tr>
    <td colspan="7" class="text-muted">
        Tidak ada data Leak Test
    </td>
</tr>
<?php else: ?>
<?php foreach ($shift['rows'] as $row): ?>
<tr>
    <td><?= esc($row['machine_code']) ?></td>
    <td class="text-start">
        <?= esc($row['part_no'].' - '.$row['part_name']) ?>
    </td>
    <td class="fw-bold"><?= esc($row['target_per_shift']) ?></td>
    <td class="text-success fw-bold"><?= esc($row['ok']) ?></td>
    <td class="text-danger fw-bold"><?= esc($row['ng']) ?></td>
    <td><?= esc($row['total']) ?></td>
    <td><?= esc($row['eff']) ?>%</td>
</tr>
<?php endforeach ?>
<?php endif ?>
</tbody>
</table>

<?php endforeach ?>

<?= $this->endSection() ?>
