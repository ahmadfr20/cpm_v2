<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Master Shift</h4>

<a href="/master/shift/create" class="btn btn-primary mb-3">
    + Tambah Shift
</a>

<table class="table table-bordered">
    <thead>
        <tr>
            <th>Code</th>
            <th>Shift</th>
            <th>Jam Kerja</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($shifts as $s): ?>
        <tr>
            <td><?= $s['shift_code'] ?></td>
            <td><?= $s['shift_name'] ?></td>
            <td><?= $s['shift_start'] ?> - <?= $s['shift_end'] ?></td>
            <td>
                <a href="/master/shift/edit/<?= $s['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
            </td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>

<?= $this->endSection() ?>
