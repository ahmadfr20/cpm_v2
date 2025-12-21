<?= $this->extend('layout/layout') ?>

<?= $this->section('content') ?>

<h4>Master Shift</h4>

<a href="/master/shift/create" class="btn btn-primary mb-3">
    <i class="bi bi-plus"></i> Tambah Shift
</a>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success">
    <?= session()->getFlashdata('success') ?>
</div>
<?php endif; ?>

<table class="table table-bordered table-striped">
    <thead>
        <tr>
            <th>Kode</th>
            <th>Nama Shift</th>
            <th>Jam Kerja</th>
            <th width="120">Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($shifts as $s): ?>
        <tr>
            <td><?= esc($s['shift_code']) ?></td>
            <td><?= esc($s['shift_name']) ?></td>
            <td><?= $s['start_time'] ?> - <?= $s['end_time'] ?></td>
            <td>
                <a href="/master/shift/edit/<?= $s['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                <a href="/master/shift/delete/<?= $s['id'] ?>"
                   class="btn btn-sm btn-danger"
                   onclick="return confirm('Hapus shift ini?')">
                   Hapus
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?= $this->endSection() ?>
