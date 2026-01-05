<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Master Production Standards</h4>

<a href="/master/production-standard/create" class="btn btn-success mb-3">
    Tambah Standard
</a>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success"><?= session()->getFlashdata('success') ?></div>
<?php endif ?>

<?php if (session()->getFlashdata('error')): ?>
<div class="alert alert-danger"><?= session()->getFlashdata('error') ?></div>
<?php endif ?>

<table class="table table-bordered table-sm">
<thead class="table-light">
<tr>
    <th>Machine</th>
    <th>Product</th>
    <th>Cycle Time (sec)</th>
    <th>Cavity</th>
    <th>Eff Rate</th>
    <th width="120">Aksi</th>
</tr>
</thead>
<tbody>
<?php foreach ($standards as $s): ?>
<tr>
    <td><?= esc($s['machine_code']) ?></td>
    <td><?= esc($s['part_no']) ?> - <?= esc($s['part_name']) ?></td>
    <td><?= esc($s['cycle_time_sec']) ?></td>
    <td><?= esc($s['cavity']) ?></td>
    <td><?= esc($s['effective_rate']) ?></td>
    <td>
        <a href="/master/production-standard/edit/<?= $s['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
        <a href="/master/production-standard/delete/<?= $s['id'] ?>"
           class="btn btn-sm btn-danger"
           onclick="return confirm('Hapus standard ini?')">
           Hapus
        </a>
    </td>
</tr>
<?php endforeach ?>
</tbody>
</table>

<?= $this->endSection() ?>
