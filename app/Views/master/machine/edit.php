<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Edit Machine</h4>

<form method="post" action="/master/machine/update/<?= $machine['id'] ?>">

<div class="mb-3">
    <label class="form-label">Machine Code</label>
    <input type="text"
           name="machine_code"
           class="form-control"
           value="<?= esc($machine['machine_code']) ?>">
</div>

<div class="mb-3">
    <label class="form-label">Machine Name</label>
    <input type="text"
           name="machine_name"
           class="form-control"
           value="<?= esc($machine['machine_name']) ?>">
</div>

<div class="mb-3">
    <label class="form-label">Process</label>
    <select name="process_id" class="form-select">
        <?php foreach ($processes as $p): ?>
            <option value="<?= $p['id'] ?>"
                <?= $p['id'] == $machine['process_id'] ? 'selected' : '' ?>>
                <?= esc($p['process_name']) ?>
            </option>
        <?php endforeach ?>
    </select>
</div>

<div class="mb-3">
    <label class="form-label">Line Position</label>
    <input type="number"
           name="line_position"
           class="form-control"
           value="<?= esc($machine['line_position']) ?>">
</div>

<button class="btn btn-primary">
    <i class="bi bi-save"></i> Update
</button>

<a href="/master/machine" class="btn btn-secondary">Kembali</a>

</form>

<?= $this->endSection() ?>
