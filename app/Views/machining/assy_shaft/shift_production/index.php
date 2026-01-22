<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">MACHINING – ASSY SHAFT PRODUCTION PER SHIFT</h4>

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

<div class="table-scroll mb-4">
<table class="table table-bordered table-sm production-table">

<thead class="table-secondary">
<tr>
    <th>Line</th>
    <th>Machine</th>
    <th>Part</th>
    <th>Target Shift</th>
    <th>OK</th>
    <th>NG</th>
    <th>Efficiency</th>
</tr>
</thead>

<tbody>
<?php foreach ($shift['items'] as $item):

$result = $shift['result_map']
    [$item['machine_id']]
    [$item['product_id']] ?? null;

$ok = $result['total_fg'] ?? 0;
$ng = $result['total_ng'] ?? 0;
$target = $item['target_per_shift'];

$eff = $target > 0
    ? round(($ok / $target) * 100, 1)
    : 0;
?>

<tr>
    <td class="text-center"><?= esc($item['line_position']) ?></td>
    <td><?= esc($item['machine_code']) ?></td>
    <td><?= esc($item['part_no'].' - '.$item['part_name']) ?></td>
    <td class="text-center fw-bold"><?= esc($target) ?></td>
    <td class="text-center text-success fw-bold"><?= $ok ?></td>
    <td class="text-center text-danger fw-bold"><?= $ng ?></td>
    <td class="text-center fw-bold">
        <?= $eff ?>%
    </td>
</tr>

<?php endforeach ?>
</tbody>

</table>
</div>

<?php endforeach ?>

<style>
.table-scroll{overflow-x:auto}
.production-table th,
.production-table td{
    font-size:13px;
    white-space:nowrap;
    text-align:center
}
</style>

<?= $this->endSection() ?>
