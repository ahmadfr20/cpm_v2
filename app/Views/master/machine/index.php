<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Master Machine</h4>

<!-- ================= FILTER ================= -->
<form method="get" class="row g-2 mb-3">

    <div class="col-md-3">
        <input type="text"
               name="keyword"
               class="form-control"
               placeholder="Search code / name"
               value="<?= esc($keyword ?? '') ?>">
    </div>

    <div class="col-md-3">
        <select name="process_id" class="form-control">
            <option value="">-- Process --</option>
            <?php foreach ($processes ?? [] as $p): ?>
                <option value="<?= $p['id'] ?>"
                    <?= ($processId ?? '') == $p['id'] ? 'selected' : '' ?>>
                    <?= esc($p['process_name']) ?>
                </option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="col-md-4">
        <button class="btn btn-primary">Filter</button>
        <a href="/master/machine" class="btn btn-secondary">Reset</a>
    </div>

</form>

<!-- ================= ADD ================= -->
<a href="/master/machine/create" class="btn btn-success mb-3">
    Tambah Machine
</a>

<!-- ================= TABLE ================= -->
<table class="table table-bordered table-striped">
<thead class="table-secondary">
<tr>
    <th>Alamat Mesin</th>
    <th>Tipe Mesin</th>
    <th>Process</th>
    <th>Line Position</th>
    <th>Produk</th>
    <th width="220">Aksi</th>
</tr>
</thead>

<tbody>
<?php if (!empty($machines)): ?>
    <?php foreach ($machines as $m): ?>
    <tr>
        <td><?= esc($m['machine_code']) ?></td>
        <td><?= esc($m['machine_name']) ?></td>
        <td><?= esc($m['process_name'] ?? '-') ?></td>
        <td>Line <?= esc($m['line_position']) ?></td>
        <td><?= esc($m['products'] ?: '-') ?></td>
        <td>
            <!-- MANAGE PRODUCT -->
            <a href="/master/machine/products/<?= $m['id'] ?>"
               class="btn btn-sm btn-info mb-1">
                Produk
            </a>

            <!-- EDIT -->
            <a href="/master/machine/edit/<?= $m['id'] ?>"
               class="btn btn-sm btn-warning mb-1">
                Edit
            </a>

            <!-- DELETE -->
            <form action="/master/machine/<?= $m['id'] ?>/delete"
                method="post"
                class="d-inline"
                onsubmit="return confirm('Hapus machine?')">

                <?= csrf_field() ?>

                <button type="submit"
                        class="btn btn-sm btn-danger mb-1">
                    Hapus
                </button>
            </form>

        </td>
    </tr>
    <?php endforeach ?>
<?php else: ?>
    <tr>
        <td colspan="6" class="text-center text-muted">
            Data machine belum tersedia
        </td>
    </tr>
<?php endif ?>
</tbody>
</table>

<?= $this->endSection() ?>
