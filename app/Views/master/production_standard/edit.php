<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Edit Production Standard</h4>

<form method="post" action="/master/production-standard/update/<?= $standard['id'] ?>">

<div class="mb-3">
    <label>Machine</label>
    <input type="text" class="form-control" disabled
           value="<?= $machines[array_search($standard['machine_id'], array_column($machines, 'id'))]['machine_code'] ?>">
</div>

<div class="mb-3">
    <label>Product</label>
    <input type="text" class="form-control" disabled
           value="<?= $products[array_search($standard['product_id'], array_column($products, 'id'))]['part_no'] ?>">
</div>

<div class="mb-3">
    <label>Cycle Time (detik)</label>
    <input type="number" name="cycle_time_sec"
           value="<?= esc($standard['cycle_time_sec']) ?>"
           class="form-control" required>
</div>

<div class="mb-3">
    <label>Cavity</label>
    <input type="number" name="cavity"
           value="<?= esc($standard['cavity']) ?>"
           class="form-control" required>
</div>

<div class="mb-3">
    <label>Effective Rate</label>
    <input type="number" step="0.01" name="effective_rate"
           value="<?= esc($standard['effective_rate']) ?>"
           class="form-control">
</div>

<button class="btn btn-primary">Update</button>
<a href="/master/production-standard" class="btn btn-secondary">Kembali</a>
</form>

<?= $this->endSection() ?>
