<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Daily Production Schedule</h4>

<b>Date:</b> <?= $header['schedule_date'] ?><br>
<b>Shift:</b> <?= $header['shift_name'] ?><br>
<b>Section:</b> <?= $header['section'] ?>

<table class="table table-bordered mt-3">
<thead class="table-light">
<tr>
    <th>Part</th>
    <th>Machine</th>
    <th>Cycle</th>
    <th>Cavity</th>
    <th>Target / Hour</th>
    <th>Target / Shift</th>
</tr>
</thead>
<tbody>
<?php foreach ($items as $r): ?>
<tr>
    <td><?= $r['part_no'] ?> - <?= $r['part_name'] ?></td>
    <td><?= $r['machine_code'] ?></td>
    <td><?= $r['cycle_time'] ?></td>
    <td><?= $r['cavity'] ?></td>
    <td><?= $r['target_per_hour'] ?></td>
    <td><?= $r['target_per_shift'] ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table>

<?= $this->endSection() ?>
