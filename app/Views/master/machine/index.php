<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Master Machine</h4>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
        <input type="text" name="keyword" class="form-control"
               placeholder="Search code / name"
               value="<?= esc($keyword) ?>">
    </div>

    <div class="col-md-3">
        <select name="production_line" class="form-control">
            <option value="">-- Production Line --</option>
            <?php foreach ($lines as $l): ?>
                <option value="<?= esc($l['production_line']) ?>"
                    <?= $line==$l['production_line']?'selected':'' ?>>
                    <?= esc($l['production_line']) ?>
                </option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="col-md-4">
        <button class="btn btn-primary">Filter</button>
        <a href="/master/machine" class="btn btn-secondary">Reset</a>
    </div>
</form>

<a href="/master/machine/create" class="btn btn-success mb-3">
    Tambah Machine
</a>

<table class="table table-bordered table-striped">
<thead>
<tr>
    <th>Code</th>
    <th>Name</th>
    <th>Line</th>
    <th>Line Position</th>
    <th>Produk</th>
    <th width="140">Aksi</th>
</tr>
</thead>
<tbody>
<?php foreach ($machines as $m): ?>
<tr>
    <td><?= esc($m['machine_code']) ?></td>
    <td><?= esc($m['machine_name']) ?></td>
    <td><?= esc($m['production_line']) ?></td>
    <td>Line <?= esc($m['line_position']) ?></td>
    <td><?= esc($m['products'] ?? '-') ?></td>
    <td>
        <a href="/master/machine/edit/<?= $m['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
        <a href="/master/machine/delete/<?= $m['id'] ?>"
           class="btn btn-sm btn-danger"
           onclick="return confirm('Hapus machine?')">Hapus</a>
    </td>
</tr>
<?php endforeach ?>
</tbody>
</table>

<?= $this->endSection() ?>
