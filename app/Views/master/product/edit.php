<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Edit Product</h4>

<form method="post" action="/master/product/update/<?= $product['id'] ?>">

<div class="mb-3">
    <label>Part No</label>
    <input type="text" name="part_no" class="form-control"
           value="<?= esc($product['part_no']) ?>" required>
</div>

<div class="mb-3">
    <label>Part Name</label>
    <input type="text" name="part_name" class="form-control"
           value="<?= esc($product['part_name']) ?>" required>
</div>

<div class="mb-3">
    <label>Customer</label>
    <select name="customer_id" class="form-control">
        <option value="">-- Pilih Customer --</option>
        <?php foreach ($customers as $c): ?>
        <option value="<?= $c['id'] ?>"
            <?= $product['customer_id']==$c['id']?'selected':'' ?>>
            <?= esc($c['customer_name']) ?>
        </option>
        <?php endforeach ?>
    </select>
</div>

<div class="mb-3">
    <label>Weight As-Cast (gr)</label>
    <input type="number" name="weight_ascas" class="form-control"
           value="<?= esc($product['weight_ascas']) ?>">
</div>

<div class="mb-3">
    <label>Weight Runner (gr)</label>
    <input type="number" name="weight_runner" class="form-control"
           value="<?= esc($product['weight_runner']) ?>">
</div>

    <div class="col-md-4">
        <label>Cycle Time (sec)</label>
        <input type="number" name="cycle_time" class="form-control" required>
    </div>

    <div class="col-md-4">
        <label>Cavity</label>
        <input type="number" name="cavity" class="form-control" required>
    </div>

    <div class="col-md-4">
        <label>Efficiency (%)</label>
        <input type="number" step="0.01" name="efficiency_rate"
               class="form-control" value="100">
    </div>

<div class="mb-3">
    <label>Notes</label>
    <textarea name="notes" class="form-control" rows="3"><?= esc($product['notes']) ?></textarea>
</div>

<button class="btn btn-primary">Update</button>
<a href="/master/product" class="btn btn-secondary">Kembali</a>

</form>

<?= $this->endSection() ?>
