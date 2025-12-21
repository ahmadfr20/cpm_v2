<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-4">
    <i class="bi bi-tools me-2"></i>
    Jadwal Harian Dandori Die Casting
</h4>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success">
    <?= session()->getFlashdata('success') ?>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">

        <form method="post" action="/die-casting/dandori/store">

            <!-- HEADER -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal</label>
                    <input class="form-control" value="<?= date('Y-m-d') ?>" readonly>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Shift</label>
                    <select name="shift_id" class="form-select" required>
                        <?php foreach ($shifts as $s): ?>
                            <option value="<?= $s['id'] ?>">
                                <?= $s['shift_name'] ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>

            <!-- MACHINE & PRODUCT -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Machine</label>
                    <select name="machine_id" class="form-select" required>
                        <?php foreach ($machines as $m): ?>
                            <option value="<?= $m['id'] ?>">
                                <?= $m['machine_name'] ?>
                            </option>
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
            </div>

            <!-- ACTIVITY -->
            <div class="mb-3">
                <label class="form-label">Dandori Activity</label>
                <input name="activity"
                       class="form-control"
                       placeholder="Mold change / Setup / Trial"
                       required>
            </div>

            <button class="btn btn-primary">
                <i class="bi bi-save me-1"></i> Save Dandori
            </button>

        </form>

    </div>
</div>

<?= $this->endSection() ?>
