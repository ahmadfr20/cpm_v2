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

<div class="mb-3">
    <label>Notes</label>
    <textarea name="notes" class="form-control" rows="3"><?= esc($product['notes']) ?></textarea>
</div>

<button class="btn btn-primary">Update</button>
<a href="/master/product" class="btn btn-secondary">Kembali</a>

</form>

<?= $this->endSection() ?>
