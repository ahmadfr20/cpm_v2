<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Tambah Machine</h4>

<form method="post" action="/master/machine/store">

<div class="mb-3">
    <label class="form-label">Machine Code</label>
    <input type="text" name="machine_code" class="form-control" required>
</div>

<div class="mb-3">
    <label class="form-label">Machine Name</label>
    <input type="text" name="machine_name" class="form-control" required>
</div>

<div class="mb-3">
    <label class="form-label">Process</label>
    <select name="process_id" class="form-select" required>
        <option value="">-- Pilih Proses --</option>
        <?php foreach ($processes as $p): ?>
            <option value="<?= $p['id'] ?>">
                <?= esc($p['process_name']) ?>
            </option>
        <?php endforeach ?>
    </select>
</div>

<div class="mb-3">
    <label class="form-label">Line Position</label>
    <input type="number" name="line_position" class="form-control" value="1">
</div>

<button class="btn btn-success">
    <i class="bi bi-save"></i> Simpan
</button>

<a href="/master/machine" class="btn btn-secondary">Kembali</a>

</form>

<?= $this->endSection() ?>
