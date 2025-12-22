<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">
    <i class="bi bi-box-arrow-in-down me-2"></i>
    Penerimaan Shot Blast dari External
</h4>

<form method="post" action="/shotblast/receive/store" class="card p-3">
    <?= csrf_field() ?>

    <div class="row g-2">

        <!-- DATE -->
        <div class="col-md-2">
            <label class="form-label">Tanggal</label>
            <input type="text" class="form-control" name="date" value="<?= $date ?>" readonly>
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

        <!-- PRODUCT -->
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

        <!-- TARGET / SHIFT -->
        <div class="col-md-2">
            <label class="form-label">Target / Shift</label>
            <input type="number" class="form-control" readonly>
        </div>

        <!-- TARGET / HOUR -->
        <div class="col-md-2">
            <label class="form-label">Target / Hour</label>
            <input type="number" class="form-control" readonly>
        </div>

        <!-- QTY OK -->
        <div class="col-md-2">
            <label class="form-label">Qty OK</label>
            <input type="number" name="qty_ok" class="form-control" required>
        </div>

        <!-- QTY NG -->
        <div class="col-md-2">
            <label class="form-label">Qty NG</label>
            <input type="number" name="qty_ng" class="form-control" value="0">
        </div>

        <!-- ACTION -->
        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-success w-100">
                <i class="bi bi-check-circle me-1"></i> Simpan
            </button>
        </div>

    </div>
</form>

<?= $this->endSection() ?>
