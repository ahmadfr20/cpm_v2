<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">
    <i class="bi bi-truck me-2"></i> Pengiriman Painting
</h4>

<form method="post" action="/painting/send/store" class="card p-3">
<?= csrf_field() ?>

<div class="row g-2">
    <div class="col-md-2"><input class="form-control" value="<?= $date ?>" readonly></div>

    <div class="col-md-2">
        <select name="shift_id" class="form-select">
            <?php foreach ($shifts as $s): ?>
                <option value="<?= $s['id'] ?>"><?= $s['shift_name'] ?></option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="col-md-4">
        <select name="product_id" class="form-select">
            <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>">
                    <?= $p['part_no'] ?> - <?= $p['part_name'] ?>
                </option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="col-md-2">
        <input name="qty_ok" class="form-control" placeholder="Qty OK">
    </div>

    <div class="col-md-2">
        <input name="qty_ng" class="form-control" placeholder="Qty NG">
    </div>

    <div class="col-md-2">
        <button class="btn btn-warning w-100">Kirim</button>
    </div>
</div>
</form>

<?= $this->endSection() ?>
