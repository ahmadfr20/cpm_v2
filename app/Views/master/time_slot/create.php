<?= $this->extend('layout/layout') ?>

<?= $this->section('content') ?>

<h4>Tambah Time Slot</h4>

<form method="post" action="/master/time-slot/store">
    <div class="mb-3">
        <label>Kode Time Slot</label>
        <input type="text" name="time_code" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>Nama Time Slot</label>
        <input type="text" name="time_name" class="form-control" required>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label>Jam Mulai</label>
            <input type="time" name="start_time" class="form-control" required>
        </div>
        <div class="col-md-6 mb-3">
            <label>Jam Selesai</label>
            <input type="time" name="end_time" class="form-control" required>
        </div>
    </div>

    <button class="btn btn-primary">Simpan</button>
    <a href="/master/time-slot" class="btn btn-secondary">Kembali</a>
</form>

<?= $this->endSection() ?>
