<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Master Product</h4>

<!-- FILTER -->
<form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text"
               name="keyword"
               class="form-control"
               placeholder="Search Part No / Name"
               value="<?= esc($keyword ?? '') ?>">
    </div>

    <div class="col-md-3">
        <select name="customer_id" class="form-control">
            <option value="">-- Customer --</option>
            <?php foreach ($customers as $c): ?>
                <option value="<?= $c['id'] ?>"
                    <?= ($customerId ?? '') == $c['id'] ? 'selected' : '' ?>>
                    <?= esc($c['customer_name']) ?>
                </option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="col-md-3">
        <button class="btn btn-primary">Filter</button>
        <a href="/master/product" class="btn btn-secondary">Reset</a>
    </div>
</form>

<!-- ACTION -->
<a href="/master/product/create" class="btn btn-success mb-3">
    <i class="bi bi-plus"></i> Tambah Product
</a>

<!-- FLASH -->
<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success">
    <?= session()->getFlashdata('success') ?>
</div>
<?php endif; ?>

<!-- TABLE -->
<table class="table table-bordered table-striped">
<thead>
<tr>
    <th width="50">No</th>
    <th>Part No</th>
    <th>Part Name</th>
    <th>Customer</th>
    <th>As-Cast (gr)</th>
    <th>Runner (gr)</th>
    <th>Cycle Time (sec)</th>
    <th>Cavity</th>
    <th>Efficiency (%)</th>

    <th>Notes</th>
    <th width="120">Aksi</th>
</tr>
</thead>
<tbody>
<?php
$page    = $pager->getCurrentPage('products') ?? 1;
$perPage = $pager->getPerPage('products') ?? count($products);
$no = 1 + ($page - 1) * $perPage;
?>

<?php if (count($products) > 0): ?>
    <?php foreach ($products as $p): ?>
    <tr>
        <td class="text-center"><?= $no++ ?></td>
        <td><?= esc($p['part_no']) ?></td>
        <td><?= esc($p['part_name']) ?></td>
        <td><?= esc($p['customer_name']) ?></td>
        <td class="text-end"><?= number_format($p['weight_ascas'] ?? 0) ?></td>
        <td class="text-end"><?= number_format($p['weight_runner'] ?? 0) ?></td>
        <td class="text-center"><?= esc($p['cycle_time']) ?></td>
        <td class="text-center"><?= esc($p['cavity']) ?></td>
        <td class="text-center"><?= esc($p['efficiency_rate']) ?></td>

        <td><?= esc($p['notes']) ?></td>
        <td>
            <a href="/master/product/edit/<?= $p['id'] ?>"
               class="btn btn-sm btn-warning">Edit</a>
            <a href="/master/product/delete/<?= $p['id'] ?>"
               class="btn btn-sm btn-danger"
               onclick="return confirm('Hapus product ini?')">Hapus</a>
        </td>
    </tr>
    <?php endforeach ?>
<?php else: ?>
    <tr>
        <td colspan="8" class="text-center text-muted">
            Tidak ada data
        </td>
    </tr>
<?php endif ?>
</tbody>
</table>

<!-- PAGINATION (FORCED SHOW) -->
<div class="d-flex justify-content-end">
    <?= $pager->links('products', 'bootstrap_pagination') ?>
</div>

<?= $this->endSection() ?>
