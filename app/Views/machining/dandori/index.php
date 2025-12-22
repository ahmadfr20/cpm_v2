<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">
    <i class="bi bi-arrow-repeat me-2"></i>
    Jadwal Harian Dandori Machining
</h4>

<form method="post" action="/machining/dandori/store" class="card p-3">
<?= csrf_field() ?>

<div class="row g-2">

    <!-- DATE -->
    <div class="col-md-2">
        <label class="form-label">Tanggal</label>
        <input class="form-control" value="<?= $date ?>" readonly>
    </div>

    <!-- SHIFT -->
    <div class="col-md-2">
        <label class="form-label">Shift</label>
        <select name="shift_id" class="form-select" required>
            <?php foreach ($shifts as $s): ?>
                <option value="<?= $s['id'] ?>">
                    <?= $s['shift_name'] ?>
                </option>
            <?php endforeach ?>
        </select>
    </div>

    <!-- MACHINE -->
    <div class="col-md-3">
        <label class="form-label">Machine</label>
        <select name="machine_id" class="form-select" required>
            <?php foreach ($machines as $m): ?>
                <option value="<?= $m['id'] ?>">
                    <?= $m['machine_name'] ?>
                </option>
            <?php endforeach ?>
        </select>
    </div>

    <!-- PRODUCT -->
    <div class="col-md-3">
        <label class="form-label">Product</label>
        <select name="product_id" class="form-select" required>
            <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>">
                    <?= $p['part_no'] ?> - <?= $p['part_name'] ?>
                </option>
            <?php endforeach ?>
        </select>
    </div>

    <!-- TARGET SHIFT -->
    <div class="col-md-2">
        <label class="form-label">Target / Shift</label>
        <input type="number" name="target_shift" class="form-control" required>
    </div>

    <!-- TARGET HOUR -->
    <div class="col-md-2">
        <label class="form-label">Target / Hour</label>
        <input type="number" name="target_hour" class="form-control">
    </div>

    <!-- ACTION -->
    <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-warning w-100">
            <i class="bi bi-save"></i> Simpan
        </button>
    </div>

</div>
</form>

<?= $this->endSection() ?>
