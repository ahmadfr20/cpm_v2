<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-2">DIE CASTING – DAILY PRODUCTION ACHIEVEMENT PER SHIFT</h4>

<div class="mb-3 p-3 bg-light border rounded">
    <b>Tanggal:</b> <?= date('d-m-Y', strtotime($date)) ?><br>
    <b>Shift:</b> <?= esc($shift['shift_name']) ?>
    (<?= $shift['start_time'] ?> - <?= $shift['end_time'] ?>)
</div>


<table class="table table-bordered table-sm text-center">
<thead class="table-light">
<tr>
    <th rowspan="2">Machine</th>
    <th rowspan="2">Part</th>
    <th rowspan="2">Target</th>
    <th colspan="2">Production</th>
    <th rowspan="2">NG Category</th>
    <th rowspan="2">Downtime (min)</th>
</tr>
<tr>
    <th>FG</th>
    <th>NG</th>
</tr>
</thead>

<tbody>

<?php foreach ($data as $machine => $rows): ?>
    <?php foreach ($rows as $i => $r): ?>
    <tr>
        <?php if ($i === 0): ?>
            <td rowspan="<?= count($rows) ?>"><b><?= $machine ?></b></td>
        <?php endif ?>

        <td><?= $r['part_no'] ?> - <?= $r['part_name'] ?></td>
        <td><?= $r['target_per_shift'] ?></td>

        <!-- FG -->
        <td>
            <input type="number"
                   class="form-control form-control-sm"
                   value="<?= $r['fg'] ?>"
                   <?= !$canEdit ? 'readonly' : '' ?>>
        </td>

        <!-- NG -->
        <td>
            <input type="number"
                   class="form-control form-control-sm"
                   value="<?= $r['ng'] ?>"
                   <?= !$canEdit ? 'readonly' : '' ?>>
        </td>

        <!-- NG CATEGORY -->
        <td>
            <select class="form-select form-select-sm" <?= !$canEdit ? 'disabled' : '' ?>>
                <option>Flow Line</option>
                <option>Crack</option>
                <option>Short</option>
                <option>Porosity</option>
            </select>
        </td>

        <!-- DOWNTIME -->
        <td>
            <input type="number"
                   class="form-control form-control-sm"
                   value="<?= $r['downtime'] ?>"
                   <?= !$canEdit ? 'readonly' : '' ?>>
        </td>
    </tr>
    <?php endforeach ?>
<?php endforeach ?>

</tbody>
</table>

<?php if (!$canEdit): ?>
<div class="alert alert-warning mt-3">
    ⛔ Data hanya bisa dikoreksi pada akhir shift
</div>
<?php endif ?>

<?= $this->endSection() ?>
