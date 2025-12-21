<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Edit Machine</h4>

<form method="post" action="/master/machine/update/<?= $machine['id'] ?>">
    <div class="mb-3">
        <label>Machine Code</label>
        <input type="text" name="machine_code" class="form-control"
               value="<?= esc($machine['machine_code']) ?>" required>
    </div>

    <div class="mb-3">
        <label>Machine Name</label>
        <input type="text" name="machine_name" class="form-control"
               value="<?= esc($machine['machine_name']) ?>" required>
    </div>

    <div class="mb-3">
        <label>Production Line</label>
        <input type="text" name="production_line" class="form-control"
               value="<?= esc($machine['production_line']) ?>" required>
    </div>

    <button class="btn btn-primary">Update</button>
    <a href="/master/machine" class="btn btn-secondary">Kembali</a>
</form>

<?= $this->endSection() ?>
