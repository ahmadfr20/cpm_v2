<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">
    <i class="bi bi-calendar-check me-2"></i>
    Jadwal Harian Pengiriman Baritori ke External
</h4>

<form method="post" action="/baritori/schedule/store" class="card p-3">
    <?= csrf_field() ?>

    <div class="row g-2">

        <div class="col-md-2">
            <label class="form-label">Tanggal</label>
            <input class="form-control" name="date" value="<?= $date ?>" readonly>
        </div>

        <div class="col-md-2">
            <label class="form-label">Shift</label>
            <select name="shift_id" class="form-select" required>
                <?php foreach ($shifts as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= $s['shift_name'] ?></option>
                <?php endforeach ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Product</label>
            <select name="product_id" class="form-select" required>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>">
                        <?= $p['part_no'] ?> - <?= $p['part_name'] ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label">Target / Shift</label>
            <input name="target_shift" class="form-control" required>
        </div>

        <div class="col-md-2">
            <label class="form-label">Target / Hour</label>
            <input name="target_hour" class="form-control">
        </div>

        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-primary w-100">
                <i class="bi bi-plus-circle"></i> Tambah
            </button>
        </div>

    </div>
</form>

<?= $this->endSection() ?>
