<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Tambah Shift</h4>

<form method="post" action="/master/shift/store">
<?= csrf_field() ?>

<div class="mb-2">
    <label>Shift Code</label>
    <input name="shift_code" class="form-control" required>
</div>

<div class="mb-2">
    <label>Shift Name</label>
    <input name="shift_name" class="form-control" required>
</div>

<hr>
<label>Time Slot</label>
<?php foreach ($timeSlots as $ts): ?>
<div class="form-check">
    <input class="form-check-input"
           type="checkbox"
           name="time_slots[]"
           value="<?= $ts['id'] ?>">
    <label class="form-check-label">
        <?= $ts['time_code'] ?> (<?= $ts['time_start'] ?> - <?= $ts['time_end'] ?>)
    </label>
</div>
<?php endforeach ?>

<button class="btn btn-primary mt-3">Simpan</button>
</form>

<?= $this->endSection() ?>
