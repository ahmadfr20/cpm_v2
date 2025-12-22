<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">
    <i class="bi bi-gear-wide-connected me-2"></i>
    Jadwal Harian Produksi Machining
</h4>

<form method="post" action="/machining/production/store" class="card p-3">
<?= csrf_field() ?>

<div class="row g-2">

    <div class="col-md-2">
        <label>Tanggal</label>
        <input class="form-control" value="<?= $date ?>" readonly>
    </div>

    <div class="col-md-2">
        <label>Shift</label>
        <select name="shift_id" class="form-select">
            <?php foreach ($shifts as $s): ?>
                <option value="<?= $s['id'] ?>"><?= $s['shift_name'] ?></option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="col-md-3">
        <label>Machine</label>
        <select name="machine_id" class="form-select">
            <?php foreach ($machines as $m): ?>
                <option value="<?= $m['id'] ?>"><?= $m['machine_name'] ?></option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="col-md-3">
        <label>Product</label>
        <select name="product_id" class="form-select">
            <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>">
                    <?= $p['part_no'] ?> - <?= $p['part_name'] ?>
                </option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="col-md-2">
        <label>Qty OK</label>
        <input name="qty_ok" class="form-control">
    </div>

    <div class="col-md-2">
        <label>Qty NG</label>
        <input name="qty_ng" class="form-control" value="0">
    </div>

    <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-primary w-100">
            <i class="bi bi-plus-circle"></i> Simpan
        </button>
    </div>

</div>
</form>

<?= $this->endSection() ?>
