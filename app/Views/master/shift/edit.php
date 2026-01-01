<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Edit Shift</h4>

<form method="post" action="/master/shift/update/<?= $shift['id'] ?>">
<?= csrf_field() ?>

<div class="mb-2">
    <label>Shift Code</label>
    <input name="shift_code" class="form-control"
           value="<?= $shift['shift_code'] ?>">
</div>

<div class="mb-2">
    <label>Shift Name</label>
    <input name="shift_name" class="form-control"
           value="<?= $shift['shift_name'] ?>">
</div>

<div class="mb-2">
    <label>Status</label>
    <select name="is_active" class="form-select">
        <option value="1" <?= $shift['is_active']?'selected':'' ?>>Active</option>
        <option value="0" <?= !$shift['is_active']?'selected':'' ?>>Inactive</option>
    </select>
</div>

<hr>
<label>Time Slot</label>
<?php foreach ($timeSlots as $ts): ?>
<div class="form-check">
    <input class="form-check-input"
           type="checkbox"
           name="time_slots[]"
           value="<?= $ts['id'] ?>"
           <?= in_array($ts['id'], $selected) ? 'checked' : '' ?>>
    <label class="form-check-label">
        <?= $ts['time_code'] ?> (<?= $ts['time_start'] ?> - <?= $ts['time_end'] ?>)
    </label>
</div>
<?php endforeach ?>

<button class="btn btn-primary mt-3">Update</button>
</form>

<?= $this->endSection() ?>
