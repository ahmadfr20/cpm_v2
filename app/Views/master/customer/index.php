<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Master Customer</h4>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="keyword" class="form-control"
               placeholder="Search code / name"
               value="<?= esc($keyword) ?>">
    </div>

    <div class="col-md-3">
        <button class="btn btn-primary">Filter</button>
        <a href="/master/customer" class="btn btn-secondary">Reset</a>
    </div>
</form>

<a href="/master/customer/create" class="btn btn-success mb-3">
    <i class="bi bi-plus"></i> Tambah Customer
</a>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success">
    <?= session()->getFlashdata('success') ?>
</div>
<?php endif; ?>

<table class="table table-bordered table-striped">
<thead>
<tr>
    <th>Code</th>
    <th>Name</th>
    <th>Address</th>
    <th>Phone</th>
    <th>Email</th>
    <th width="120">Aksi</th>
</tr>
</thead>
<tbody>
<?php if (empty($customers)): ?>
<tr>
    <td colspan="6" class="text-center">No data</td>
</tr>
<?php endif; ?>

<?php foreach ($customers as $c): ?>
<tr>
    <td><?= esc($c['customer_code']) ?></td>
    <td><?= esc($c['customer_name']) ?></td>
    <td><?= esc($c['address']) ?></td>
    <td><?= esc($c['phone']) ?></td>
    <td><?= esc($c['email']) ?></td>
    <td>
        <a href="/master/customer/edit/<?= $c['id'] ?>"
           class="btn btn-sm btn-warning">Edit</a>
        <a href="/master/customer/delete/<?= $c['id'] ?>"
           class="btn btn-sm btn-danger"
           onclick="return confirm('Hapus customer ini?')">Hapus</a>
    </td>
</tr>
<?php endforeach ?>
</tbody>
</table>

<?= $this->endSection() ?>
