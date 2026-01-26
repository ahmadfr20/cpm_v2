<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">Production Flow Product</h4>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<!-- ================= FILTER ================= -->
<form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="keyword" class="form-control"
               placeholder="Search part no / name"
               value="<?= esc($keyword ?? '') ?>">
    </div>

    <div class="col-md-2">
        <select name="per_page" class="form-control" onchange="this.form.submit()">
            <?php foreach ([10,25,50,100] as $n): ?>
                <option value="<?= $n ?>" <?= ((int)$perPage === (int)$n) ? 'selected' : '' ?>>
                    <?= $n ?> data
                </option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="col-md-2">
        <button class="btn btn-primary">Filter</button>
    </div>
</form>

<!-- ================= BULK SAVE FORM ================= -->
<form method="post" action="<?= site_url('master/production-flow/bulk-update') ?>">
<?= csrf_field() ?>

<div class="table-responsive">
<table class="table table-bordered table-sm align-middle">
    <thead class="table-secondary text-center">
        <tr>
            <th class="text-start">
                <a class="text-dark text-decoration-none"
                   href="?<?= http_build_query(array_merge($_GET, [
                       'sort' => 'part_no',
                       'dir'  => ($sort === 'part_no' && $direction === 'ASC') ? 'DESC' : 'ASC'
                   ])) ?>">
                    Product <?= $sort === 'part_no' ? ($direction === 'ASC' ? '▲' : '▼') : '' ?>
                </a>
            </th>

            <?php foreach ($processes as $pr): ?>
                <th><?= esc($pr['process_name']) ?></th>
            <?php endforeach ?>

            <th width="120">Mode</th>
        </tr>
    </thead>

    <tbody>
    <?php foreach ($products as $p): ?>
        <tr>
            <!-- penting: kirim daftar produk yang tampil di halaman ini -->
            <input type="hidden" name="product_ids[]" value="<?= (int)$p['id'] ?>">

            <td>
                <strong><?= esc($p['part_no']) ?></strong><br>
                <small class="text-muted"><?= esc($p['part_name']) ?></small>
            </td>

            <?php foreach ($processes as $pr): ?>
                <td class="text-center">
                    <!-- penting: hidden agar key flows[productId][] tetap terkirim walau semua checkbox kosong -->
                    <input type="hidden" name="flows[<?= (int)$p['id'] ?>][]" value="">

                    <input type="checkbox"
                           name="flows[<?= (int)$p['id'] ?>][]"
                           value="<?= (int)$pr['id'] ?>"
                           <?= isset($flowMap[$p['id']][$pr['id']]) ? 'checked' : '' ?>>
                </td>
            <?php endforeach ?>
            <!-- <td class="text-center">
                <span class="badge bg-success">Bulk</span>
            </td> -->
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
</div>

<div class="d-flex justify-content-between mt-3 align-items-center">
    <div>
        <?= $pager->links('products', 'bootstrap_pagination') ?>
    </div>
    <div>
        <button class="btn btn-success" type="submit">
            💾 Save All Changes
        </button>
    </div>
</div>

</form>

<?= $this->endSection() ?>
