<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Edit Machine</h4>

<form method="post" action="/master/machine/update/<?= $machine['id'] ?>">
<?= csrf_field() ?>

<div class="row">
    <div class="col-md-4 mb-3">
        <label>Machine Code</label>
        <input name="machine_code" class="form-control"
               value="<?= esc($machine['machine_code']) ?>" required>
    </div>

    <div class="col-md-4 mb-3">
        <label>Machine Name</label>
        <input name="machine_name" class="form-control"
               value="<?= esc($machine['machine_name']) ?>" required>
    </div>

    <div class="col-md-4 mb-3">
        <label>Production Line</label>
        <select name="production_line" class="form-select" required>
            <?php foreach ($lines as $line): ?>
                <option value="<?= $line ?>"
                    <?= $machine['production_line'] === $line ? 'selected' : '' ?>>
                    <?= $line ?>
                </option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="col-md-4 mb-3">
        <label>Process</label>
        <select name="process_id" class="form-select">
            <?php foreach ($processes as $p): ?>
                <option value="<?= $p['id'] ?>"
                    <?= $machine['process_id'] == $p['id'] ? 'selected' : '' ?>>
                    <?= $p['process_name'] ?>
                </option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="col-md-4 mb-3">
        <label>Line Position</label>
        <input type="number" name="line_position"
               value="<?= $machine['line_position'] ?>"
               class="form-control">
    </div>
</div>

<button class="btn btn-primary">
    <i class="bi bi-save"></i> Update
</button>
<a href="/master/machine" class="btn btn-secondary">Batal</a>

</form>

<?= $this->endSection() ?>
