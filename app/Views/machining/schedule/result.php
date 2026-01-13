<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>HASIL DAILY SCHEDULE – MACHINING</h4>

<form method="get" class="mb-3 w-25">
    <label>Tanggal</label>
    <input type="date"
           name="date"
           value="<?= esc($date) ?>"
           class="form-control"
           onchange="this.form.submit()">
</form>

<table class="table table-bordered table-sm text-center align-middle">
<thead class="table-secondary">
<tr>
    <th>Shift</th>
    <th>Line</th>
    <th>Mesin</th>
    <th>Part</th>
    <th>Plan</th>
    <th>Actual</th>
    <th class="text-danger">NG</th>
    <th>Efisiensi</th>
</tr>
</thead>
<tbody>

<?php foreach ($rows as $r):
    $eff = $r['plan'] > 0
        ? round(($r['act'] / $r['plan']) * 100, 1)
        : 0;
?>
<tr>
    <td><?= esc($r['shift_name']) ?></td>
    <td><?= esc($r['line_position']) ?></td>
    <td><?= esc($r['machine_name']) ?></td>
    <td><?= esc($r['part_no']) ?></td>
    <td><?= $r['plan'] ?></td>
    <td><?= $r['act'] ?></td>
    <td class="text-danger"><?= $r['ng'] ?></td>
    <td>
        <span class="<?= $eff >= 95 ? 'text-success' : 'text-warning' ?>">
            <?= $eff ?>%
        </span>
    </td>
</tr>
<?php endforeach ?>

</tbody>

<tfoot class="table-light">
<?php foreach ($shiftSummary as $s):
    $eff = $s['plan'] > 0
        ? round(($s['act'] / $s['plan']) * 100, 1)
        : 0;
?>
<tr class="fw-bold">
    <td colspan="4"><?= esc($s['shift_name']) ?> TOTAL</td>
    <td><?= $s['plan'] ?></td>
    <td><?= $s['act'] ?></td>
    <td class="text-danger"><?= $s['ng'] ?></td>
    <td><?= $eff ?>%</td>
</tr>
<?php endforeach ?>
</tfoot>
</table>

<a href="/machining/daily-schedule"
   class="btn btn-secondary mt-3">
   <i class="bi bi-arrow-left"></i> Kembali ke Schedule
</a>

<?= $this->endSection() ?>
