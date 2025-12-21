<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-4">
    <i class="bi bi-cpu me-2"></i>
    Jadwal Harian Produksi Die Casting
</h4>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success">
    <?= session()->getFlashdata('success') ?>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">

        <form method="post" action="/die-casting/production/store">

            <!-- HEADER -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal</label>
                    <input class="form-control" value="<?= $date ?>" readonly>
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

            <!-- TABLE -->
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead class="table-dark">
                    <tr>
                        <th>No</th>
                        <th>Part No</th>
                        <th>Part Name</th>
                        <th width="120">OK</th>
                        <th width="120">NG</th>
                    </tr>
                    </thead>
                    <tbody>

                    <?php if (!empty($schedules)): ?>
                        <?php foreach ($schedules as $i => $sc): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= esc($sc['part_no']) ?></td>
                            <td><?= esc($sc['part_name']) ?></td>
                            <td>
                                <input type="number"
                                       name="items[<?= $i ?>][qty_ok]"
                                       class="form-control form-control-sm"
                                       min="0">
                            </td>
                            <td>
                                <input type="number"
                                       name="items[<?= $i ?>][qty_ng]"
                                       class="form-control form-control-sm"
                                       min="0">
                            </td>
                            <input type="hidden"
                                   name="items[<?= $i ?>][product_id]"
                                   value="<?= $sc['product_id'] ?>">
                        </tr>
                        <?php endforeach ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                Tidak ada jadwal die casting hari ini
                            </td>
                        </tr>
                    <?php endif; ?>

                    </tbody>
                </table>
            </div>

            <button class="btn btn-success mt-3">
                <i class="bi bi-save me-1"></i> Save Production
            </button>

        </form>

    </div>
</div>

<?= $this->endSection() ?>
