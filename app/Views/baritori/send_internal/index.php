<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">
    <i class="bi bi-arrow-repeat me-2"></i>
    Pengiriman Baritori ke Internal
</h4>

<form method="post" action="/baritori/send-internal/store" class="card p-3">
    <?= csrf_field() ?>

    <div class="row g-2">

        <div class="col-md-2">
            <label class="form-label">Tanggal</label>
            <input class="form-control" name="date" value="<?= $date ?>" readonly>
        </div>

        <div class="col-md-2">
            <label class="form-label">Shift</label>
            <select name="shift_id" class="form-select">
                <?php foreach ($shifts as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= $s['shift_name'] ?></option>
                <?php endforeach ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Product</label>
            <select name="product_id" class="form-select">
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>">
                        <?= $p['part_no'] ?> - <?= $p['part_name'] ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label">Qty Kirim</label>
            <input type="number" name="qty_ok" class="form-control" required>
        </div>

        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-info w-100">
                <i class="bi bi-arrow-right-circle"></i> Transfer
            </button>
        </div>

    </div>
</form>

<?= $this->endSection() ?>
