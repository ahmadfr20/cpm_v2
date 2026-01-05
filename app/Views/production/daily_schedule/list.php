<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Daily Production Schedule List</h4>

<form method="get" class="row mb-3">
    <div class="col-md-3">
        <input type="date"
               name="date"
               value="<?= esc($date) ?>"
               class="form-control">
    </div>
    <div class="col-md-4">
        <button class="btn btn-primary">Filter</button>
        <a href="/production/daily-schedule/list" class="btn btn-secondary">
            Today
        </a>
    </div>
</form>

<table class="table table-bordered table-sm">
<thead class="table-light">
<tr>
    <th>Date</th>
    <th>Shift</th>
    <th>Section</th>
    <th>Status</th>
    <th width="120">Action</th>
</tr>
</thead>
<tbody>

<?php if (empty($schedules)): ?>
<tr>
    <td colspan="5" class="text-center text-muted">
        No schedule found
    </td>
</tr>
<?php endif; ?>

<?php foreach ($schedules as $s): ?>
<tr>
    <td><?= esc($s['schedule_date']) ?></td>
    <td><?= esc($s['shift_name']) ?></td>
    <td><?= esc($s['section']) ?></td>
    <td>
        <?= $s['is_completed']
            ? '<span class="badge bg-success">Completed</span>'
            : '<span class="badge bg-warning">Open</span>' ?>
    </td>
    <td>
        <a href="/production/daily-schedule/view/<?= $s['id'] ?>"
           class="btn btn-sm btn-info">
            View
        </a>
    </td>
</tr>
<?php endforeach ?>

</tbody>
</table>

<?= $this->endSection() ?>
