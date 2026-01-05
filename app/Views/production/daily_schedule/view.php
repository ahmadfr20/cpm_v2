<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Daily Production Schedule Detail</h4>

<table class="table table-bordered table-sm mb-3">
<tr>
    <th width="150">Date</th>
    <td><?= esc($header['schedule_date']) ?></td>
</tr>
<tr>
    <th>Shift</th>
    <td><?= esc($header['shift_name']) ?></td>
</tr>
<tr>
    <th>Section</th>
    <td><?= esc($header['section']) ?></td>
</tr>
</table>

<table class="table table-bordered table-sm">
<thead class="table-light">
<tr>
    <th>Line</th>
    <th>Machine</th>
    <th>Product</th>
    <th>Target / Hour</th>
    <th>Target / Shift</th>
</tr>
</thead>
<tbody>

<?php if (empty($items)): ?>
<tr>
    <td colspan="5" class="text-center text-muted">
        No items
    </td>
</tr>
<?php endif; ?>

<?php foreach ($items as $i): ?>
<tr>
    <td>Line <?= esc($i['line_position']) ?></td>
    <td><?= esc($i['machine_code']) ?></td>
    <td><?= esc($i['part_no']) ?> - <?= esc($i['part_name']) ?></td>
    <td><?= esc($i['target_per_hour']) ?></td>
    <td><?= esc($i['target_per_shift']) ?></td>
</tr>
<?php endforeach ?>

</tbody>
</table>

<a href="/production/daily-schedule/list" class="btn btn-secondary">
    Back
</a>

<?= $this->endSection() ?>
