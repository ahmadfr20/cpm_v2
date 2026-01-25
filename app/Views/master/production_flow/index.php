<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">Production Flow Product</h4>

<!-- =====================================================
     FILTER FORM (GET)
===================================================== -->
<form method="get" class="row g-2 mb-3">

    <div class="col-md-3">
        <label class="form-label fw-bold">Effective Date</label>
        <input type="date"
               name="date"
               class="form-control"
               value="<?= esc($effective_date) ?>">
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold">Search Product</label>
        <input type="text"
               name="keyword"
               class="form-control"
               placeholder="Part no / name"
               value="<?= esc($keyword ?? '') ?>">
    </div>

    <div class="col-md-2">
        <label class="form-label fw-bold">Jumlah Data</label>
        <select name="per_page"
                class="form-control"
                onchange="this.form.submit()">
            <?php foreach ([10,25,50,100] as $n): ?>
                <option value="<?= $n ?>"
                    <?= ($perPage ?? 10) == $n ? 'selected' : '' ?>>
                    <?= $n ?> data
                </option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="col-md-2 align-self-end">
        <button class="btn btn-primary">
            Filter
        </button>
    </div>

</form>

<!-- =====================================================
     SAVE FORM (POST)
===================================================== -->
<form method="post" action="/master/production-flow/save">
<?= csrf_field() ?>

<input type="hidden"
       name="effective_date"
       value="<?= esc($effective_date) ?>">

<!-- ================= TABLE ================= -->
<div class="table-responsive">
<table class="table table-bordered table-sm align-middle">

<thead class="table-secondary text-center">
<tr>
    <th style="width:220px" class="text-start">
        <a href="?<?= http_build_query(array_merge($_GET, [
            'sort' => 'part_no',
            'dir'  => ($sort === 'part_no' && $direction === 'ASC') ? 'DESC' : 'ASC'
        ])) ?>" class="text-decoration-none text-dark">
            Product
            <?php if ($sort === 'part_no'): ?>
                <?= $direction === 'ASC' ? '▲' : '▼' ?>
            <?php endif ?>
        </a>
    </th>

    <?php foreach ($processes as $pr): ?>
        <th><?= esc($pr['process_name']) ?></th>
    <?php endforeach ?>
</tr>
</thead>

<tbody>
<?php if (!empty($products)): ?>
<?php foreach ($products as $p): ?>
<tr>

    <td>
        <strong><?= esc($p['part_no']) ?></strong><br>
        <small class="text-muted"><?= esc($p['part_name']) ?></small>
    </td>

    <?php foreach ($processes as $pr): 
        $checked = isset($flowMap[$p['id']][$pr['id']]);
    ?>
        <td class="text-center">
            <input type="checkbox"
                   name="flows[<?= $p['id'] ?>][]"
                   value="<?= $pr['id'] ?>"
                   <?= $checked ? 'checked' : '' ?>>
        </td>
    <?php endforeach ?>

</tr>
<?php endforeach ?>
<?php else: ?>
<tr>
    <td colspan="<?= count($processes) + 1 ?>" class="text-center text-muted">
        Data produk tidak ditemukan
    </td>
</tr>
<?php endif ?>
</tbody>

</table>
</div>

<!-- ================= PAGINATION ================= -->
<?php if ($pager): ?>
<div class="mt-3 d-flex justify-content-end">
    <?= $pager->links('products', 'bootstrap_pagination') ?>
</div>
<?php endif ?>

<!-- ================= SAVE BUTTON ================= -->
<div class="mt-4">
    <button class="btn btn-success">
        <i class="bi bi-save"></i> Simpan Production Flow
    </button>
</div>

</form>

<?= $this->endSection() ?>
