<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Daily Production Schedule</h4>

<!-- FILTER -->
<form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
        <input type="date"
               name="date"
               value="<?= esc($date) ?>"
               class="form-control">
    </div>
    <div class="col-md-2">
        <button class="btn btn-primary w-100">
            <i class="bi bi-search"></i> Filter
        </button>
    </div>
    <div class="col-md-3">
        <a href="/production/daily-schedule"
           class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Input Schedule
        </a>
    </div>
</form>

<table class="table table-bordered table-striped">
<thead class="table-light">
<tr>
    <th width="120">Date</th>
    <th>Shift</th>
    <th>Section</th>
    <th width="120">Status</th>
    <th width="120">Aksi</th>
</tr>
</thead>
<tbody>

<?php if (empty($schedules)): ?>
<tr>
    <td colspan="5" class="text-center text-muted">
        Tidak ada data
    </td>
</tr>
<?php endif ?>

<?php foreach ($schedules as $s): ?>
<tr>
    <td><?= esc($s['schedule_date']) ?></td>
    <td><?= esc($s['shift_name']) ?></td>
    <td><?= esc($s['section']) ?></td>
    <td class="text-center">
        <?php if ($s['is_completed']): ?>
            <span class="badge bg-success">Completed</span>
        <?php else: ?>
            <span class="badge bg-warning text-dark">Open</span>
        <?php endif ?>
    </td>
    <td class="text-center">
        <a href="/production/daily-schedule/view/<?= $s['id'] ?>"
           class="btn btn-sm btn-primary">
            <i class="bi bi-eye"></i> View
        </a>
    </td>
</tr>
<?php endforeach ?>

</tbody>
</table>

<?= $this->endSection() ?>
