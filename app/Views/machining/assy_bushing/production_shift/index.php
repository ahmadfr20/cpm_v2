<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">MACHINING – ASSY BUSHING PRODUCTION PER SHIFT</h4>

<div class="mb-3">
    <strong>Tanggal:</strong> <?= esc($date) ?><br>
    <strong>Operator:</strong> <?= esc($operator) ?>
</div>

<form method="get" class="mb-3">
    <label class="fw-bold me-2">Tanggal Produksi:</label>
    <input type="date"
           name="date"
           value="<?= esc($date) ?>"
           class="form-control d-inline-block"
           style="width:180px"
           onchange="this.form.submit()">
</form>

<?php foreach ($shifts as $shift): ?>

<h5 class="mt-4"><?= esc($shift['shift_name']) ?></h5>

<div class="table-responsive">
<table class="table table-bordered table-sm align-middle">

<thead class="table-secondary">
<tr>
    <th style="width:60px">Line</th>
    <th style="width:120px">Machine</th>
    <th>Part</th>
    <th style="width:120px">Target Shift</th>
    <th style="width:90px">OK</th>
    <th style="width:90px">NG</th>
    <th style="width:110px">Efficiency</th>
</tr>
</thead>

<tbody>
<?php
$totalTarget = 0;
$totalOK     = 0;
$totalNG     = 0;
?>

<?php foreach ($shift['items'] as $item): ?>

<?php
$totalTarget += $item['target_per_shift'];
$totalOK     += $item['ok'];
$totalNG     += $item['ng'];

$eff = $item['target_per_shift'] > 0
    ? round(($item['ok'] / $item['target_per_shift']) * 100, 1)
    : 0;
?>

<tr>
    <td class="text-center fw-bold"><?= esc($item['line_position']) ?></td>
    <td class="text-center"><?= esc($item['machine_code']) ?></td>
    <td><?= esc($item['part_no'].' - '.$item['part_name']) ?></td>
    <td class="text-center fw-bold"><?= esc($item['target_per_shift']) ?></td>
    <td class="text-center text-success fw-bold"><?= esc($item['ok']) ?></td>
    <td class="text-center text-danger fw-bold"><?= esc($item['ng']) ?></td>
    <td class="text-center fw-bold"><?= $eff ?>%</td>
</tr>

<?php endforeach ?>
</tbody>

<tfoot class="table-light fw-bold">
<tr>
    <td colspan="3" class="text-end">TOTAL</td>
    <td class="text-center"><?= $totalTarget ?></td>
    <td class="text-center"><?= $totalOK ?></td>
    <td class="text-center"><?= $totalNG ?></td>
    <td class="text-center">
        <?= $totalTarget > 0
            ? round(($totalOK / $totalTarget) * 100, 1)
            : 0 ?>%
    </td>
</tr>
</tfoot>

</table>
</div>

<?php endforeach ?>

<?= $this->endSection() ?>
