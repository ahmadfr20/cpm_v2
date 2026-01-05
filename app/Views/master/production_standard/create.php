<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Tambah Production Standard</h4>

<form method="post" action="/master/production-standard/store">
<div class="mb-3">
    <label>Machine</label>
    <select name="machine_id" class="form-control" required>
        <?php foreach ($machines as $m): ?>
            <option value="<?= $m['id'] ?>"><?= $m['machine_code'] ?></option>
        <?php endforeach ?>
    </select>
</div>

<div class="mb-3">
    <label>Product</label>
    <select name="product_id" class="form-control" required>
        <?php foreach ($products as $p): ?>
            <option value="<?= $p['id'] ?>">
                <?= $p['part_no'] ?> - <?= $p['part_name'] ?>
            </option>
        <?php endforeach ?>
    </select>
</div>

<div class="mb-3">
    <label>Cycle Time (detik)</label>
    <input type="number" name="cycle_time_sec" class="form-control" required>
</div>

<div class="mb-3">
    <label>Cavity</label>
    <input type="number" name="cavity" class="form-control" value="2" required>
</div>

<div class="mb-3">
    <label>Effective Rate</label>
    <input type="number" step="0.01" name="effective_rate" class="form-control" value="1">
</div>

<button class="btn btn-primary">Simpan</button>
<a href="/master/production-standard" class="btn btn-secondary">Kembali</a>
</form>

<?= $this->endSection() ?>
