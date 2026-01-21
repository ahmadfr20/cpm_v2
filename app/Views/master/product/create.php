<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Tambah Product</h4>

<form method="post" action="/master/product/store">

<div class="mb-3">
    <label>Part No</label>
    <input type="text" name="part_no" class="form-control" required>
</div>

<div class="mb-3">
    <label>Part Name</label>
    <input type="text" name="part_name" class="form-control" required>
</div>

<select name="customer_id" class="form-control" required>
    <option value="">-- Pilih Customer --</option>
    <?php foreach ($customers as $c): ?>
        <option value="<?= $c['id'] ?>">
            <?= esc($c['customer_name']) ?>
        </option>
    <?php endforeach ?>
</select>


<div class="mb-3">
    <label>Weight As-Cast (gr)</label>
    <input type="number" name="weight_ascas" class="form-control">
</div>

<div class="mb-3">
    <label>Weight Runner (gr)</label>
    <input type="number" name="weight_runner" class="form-control">
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
    <textarea name="notes" class="form-control" rows="3"></textarea>
</div>

<button class="btn btn-primary">Simpan</button>
<a href="/master/product" class="btn btn-secondary">Kembali</a>

</form>

<?= $this->endSection() ?>
