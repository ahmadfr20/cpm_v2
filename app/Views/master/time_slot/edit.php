<?= $this->extend('layout/layout') ?>

<?= $this->section('content') ?>

<h4>Edit Time Slot</h4>

<form method="post" action="/master/time-slot/update/<?= $timeSlot['id'] ?>">
    <div class="mb-3">
        <label>Kode Time Slot</label>
        <input type="text" name="time_code" class="form-control"
               value="<?= esc($timeSlot['time_code']) ?>" required>
    </div>


    <div class="row">
        <div class="col-md-6 mb-3">
            <label>Jam Mulai</label>
            <input type="time" name="time_start" class="form-control"
                   value="<?= $timeSlot['time_start'] ?>" required>
        </div>
        <div class="col-md-6 mb-3">
            <label>Jam Selesai</label>
            <input type="time" name="time_end" class="form-control"
                   value="<?= $timeSlot['time_end'] ?>" required>
        </div>
    </div>

    <button class="btn btn-primary">Update</button>
    <a href="/master/time-slot" class="btn btn-secondary">Kembali</a>
</form>

<?= $this->endSection() ?>
