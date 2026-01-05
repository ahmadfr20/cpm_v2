<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Manage Product - <?= esc($machine['machine_name']) ?></h4>

<form method="post" action="/master/machine/save-products/<?= $machine['id'] ?>">
    <?php foreach ($products as $p): ?>
        <div class="form-check">
            <input type="checkbox"
                   class="form-check-input"
                   name="products[]"
                   value="<?= $p['id'] ?>"
                   <?= in_array($p['id'], $assigned) ? 'checked' : '' ?>>
            <label class="form-check-label">
                <?= esc($p['part_no']) ?> - <?= esc($p['part_name']) ?>
            </label>
        </div>
    <?php endforeach; ?>

    <button class="btn btn-primary mt-3">Simpan</button>
    <a href="/master/machine" class="btn btn-secondary mt-3">Kembali</a>
</form>

<?= $this->endSection() ?>
