<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Tambah Machine</h4>

<form method="post" action="/master/machine/store">

<div class="mb-3">
    <label>Machine Code</label>
    <input type="text" name="machine_code" class="form-control" required>
</div>

<div class="mb-3">
    <label>Machine Name</label>
    <input type="text" name="machine_name" class="form-control" required>
</div>

<div class="mb-3">
    <label>Production Line</label>
    <input type="text" name="production_line" class="form-control" required>
</div>

<div class="mb-3">
    <label>Line Position</label>
    <input type="number" name="line_position"
           class="form-control"
           min="1" value="1" required>
</div>

<button class="btn btn-primary">Simpan</button>
<a href="/master/machine" class="btn btn-secondary">Kembali</a>

</form>

<?= $this->endSection() ?>
