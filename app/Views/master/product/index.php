<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Master Product</h4>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="keyword" class="form-control"
               placeholder="Search Part No / Name"
               value="<?= esc($keyword) ?>">
    </div>

    <div class="col-md-3">
        <select name="customer_id" class="form-control">
            <option value="">-- Customer --</option>
            <?php foreach ($customers as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $customerId==$c['id']?'selected':'' ?>>
                <?= esc($c['customer_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-3">
        <button class="btn btn-primary">Filter</button>
        <a href="/master/product" class="btn btn-secondary">Reset</a>
    </div>
</form>

<a href="/master/product/create" class="btn btn-success mb-3">
    <i class="bi bi-plus"></i> Tambah Product
</a>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success">
    <?= session()->getFlashdata('success') ?>
</div>
<?php endif; ?>

<table class="table table-bordered table-striped">
    <thead>
        <tr>
            <th>Part No</th>
            <th>Part Name</th>
            <th>Customer</th>
            <th>Weight</th>
            <th>Notes</th>
            <th width="120">Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($products as $p): ?>
        <tr>
            <td><?= esc($p['part_no']) ?></td>
            <td><?= esc($p['part_name']) ?></td>
            <td><?= esc($p['customer_name']) ?></td>
            <td><?= esc($p['weight']) ?></td>
            <td><?= esc($p['notes']) ?></td>
            <td>
                <a href="/master/product/edit/<?= $p['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                <a href="/master/product/delete/<?= $p['id'] ?>"
                   class="btn btn-sm btn-danger"
                   onclick="return confirm('Hapus product ini?')">Hapus</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?= $this->endSection() ?>
