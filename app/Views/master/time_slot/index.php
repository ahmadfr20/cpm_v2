<?= $this->extend('layout/layout') ?>

<?= $this->section('content') ?>

<h4>Master Time Slot</h4>

<a href="/master/time-slot/create" class="btn btn-primary mb-3">
    <i class="bi bi-plus"></i> Tambah Time Slot
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
            <th>Jam</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($timeSlots as $t): ?>
        <tr>
            <td><?= esc($t['time_code']) ?></td>
            <td><?= $t['time_start'] ?> - <?= $t['time_end'] ?></td>
            <td>
                <a href="/master/time-slot/edit/<?= $t['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                <a href="/master/time-slot/delete/<?= $t['id'] ?>"
                   class="btn btn-sm btn-danger"
                   onclick="return confirm('Hapus time slot ini?')">
                   Hapus
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?= $this->endSection() ?>
