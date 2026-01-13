<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">MACHINING – DAILY PRODUCTION PER HOUR</h4>

<div class="mb-3">
    <strong>Tanggal:</strong> <?= esc($date) ?><br>
    <strong>Operator:</strong> <?= esc($operator) ?>
</div>

<form method="post" action="/machining/daily-production/store">
<?= csrf_field() ?>

<?php foreach ($shifts as $shift): ?>

<h5 class="mt-4 mb-2"><?= esc($shift['shift_name']) ?></h5>

<div class="table-scroll">
<table class="production-table table table-bordered table-sm">

<thead>
<tr>
    <th rowspan="2" class="sticky-left col-line">Line</th>
    <th rowspan="2" class="sticky-left-2 col-part">Part</th>
    <th rowspan="2" class="sticky-left-3 col-target-shift">Target<br>Shift</th>

    <?php foreach ($shift['slots'] as $slot): ?>
        <th colspan="3">
            <?= substr($slot['time_start'],0,5) ?> - <?= substr($slot['time_end'],0,5) ?>
        </th>
    <?php endforeach ?>
</tr>

<tr>
<?php foreach ($shift['slots'] as $slot): ?>
    <th>FG</th>
    <th>NG</th>
    <th>Ket.</th>
<?php endforeach ?>
</tr>
</thead>

<tbody class="shift-body">
<?php foreach ($shift['items'] as $item): ?>
<tr>

<td class="sticky-left">
    <?= esc($item['line_position']) ?>
</td>

<td class="sticky-left-2 text-start">
    <?= esc($item['part_no'].' - '.$item['part_name']) ?>
</td>

<td class="sticky-left-3 fw-bold">
    <?= esc($item['target_per_shift']) ?>
</td>

<?php foreach ($shift['slots'] as $slot):

$exist = $shift['hourly_map']
    [$item['machine_id']]
    [$item['product_id']]
    [$slot['id']] ?? null;
?>

<td>
<input type="number" class="form-control form-control-sm fg"
       name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][fg]"
       value="<?= $exist['qty_fg'] ?? 0 ?>">
</td>

<td>
<input type="number" class="form-control form-control-sm ng"
       name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][ng]"
       value="<?= $exist['qty_ng'] ?? 0 ?>">
</td>

<td>
<input type="text" class="form-control form-control-sm"
       name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][ng_remark]"
       value="<?= $exist['ng_category'] ?? '' ?>">
</td>

<input type="hidden" name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][shift_id]" value="<?= $shift['id'] ?>">
<input type="hidden" name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][machine_id]" value="<?= $item['machine_id'] ?>">
<input type="hidden" name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][product_id]" value="<?= $item['product_id'] ?>">
<input type="hidden" name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][time_slot_id]" value="<?= $slot['id'] ?>">
<input type="hidden" name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][date]" value="<?= $date ?>">

<?php endforeach ?>
</tr>
<?php endforeach ?>
</tbody>

</table>
</div>

<?php endforeach ?>

<button class="btn btn-success mt-3">
    <i class="bi bi-save"></i> Simpan
</button>

</form>

<?= $this->endSection() ?>
