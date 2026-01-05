<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">DIE CASTING – DAILY PRODUCTION ACHIEVEMENT</h4>

<!-- ================= FILTER TANGGAL ================= -->
<form method="get" class="row g-2 mb-4 align-items-end">

    <div class="col-md-3">
        <label class="form-label">Tanggal Produksi</label>
        <input type="date"
               name="date"
               value="<?= esc($date) ?>"
               class="form-control">
    </div>

    <div class="col-md-2">
        <button class="btn btn-primary">
            <i class="bi bi-search"></i> Load Data
        </button>
    </div>

</form>

<?php if (empty($data)): ?>
    <div class="alert alert-warning">
        Tidak ada Daily Production Schedule untuk tanggal ini.
    </div>
<?php endif; ?>

<!-- ================= LOOP PER SHIFT ================= -->
<?php foreach ($data as $shiftData): ?>

<div class="card mb-4">

    <!-- ===== SHIFT HEADER ===== -->
    <div class="card-header bg-light fw-bold">
        <?= esc($shiftData['shift']['shift_name']) ?>
        (<?= esc($shiftData['start_time']) ?> - <?= esc($shiftData['end_time']) ?>)
    </div>

    <!-- ===== TABLE ===== -->
    <div class="card-body p-0">
        <table class="table table-bordered table-sm text-center mb-0 align-middle">

            <thead class="table-secondary">
            <tr>
                <th>Line</th>
                <th>Machine</th>
                <th>Part</th>
                <th>Target</th>
                <th>FG</th>
                <th>NG</th>
                <th>Downtime (min)</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($shiftData['rows'] as $r): ?>
                <tr>
                    <td>Line <?= esc($r['line_position']) ?></td>
                    <td><?= esc($r['machine_code']) ?></td>
                    <td><?= esc($r['part_no']) ?> - <?= esc($r['part_name']) ?></td>
                    <td><?= esc($r['target_per_shift']) ?></td>

                    <td>
                        <input type="number"
                               class="form-control form-control-sm text-end"
                               value="<?= esc($r['fg']) ?>"
                               <?= !$shiftData['canEdit'] ? 'readonly' : '' ?>>
                    </td>

                    <td>
                        <input type="number"
                               class="form-control form-control-sm text-end"
                               value="<?= esc($r['ng']) ?>"
                               <?= !$shiftData['canEdit'] ? 'readonly' : '' ?>>
                    </td>

                    <td>
                        <input type="number"
                               class="form-control form-control-sm text-end"
                               value="<?= esc($r['downtime']) ?>"
                               <?= !$shiftData['canEdit'] ? 'readonly' : '' ?>>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>

            <!-- ===== TOTAL PER SHIFT ===== -->
            <tfoot class="table-light fw-bold">
            <tr>
                <td colspan="3" class="text-end">TOTAL</td>
                <td><?= esc($shiftData['totalTarget']) ?></td>
                <td><?= esc($shiftData['totalFG']) ?></td>
                <td colspan="2">
                    Efficiency :
                    <span class="badge
                        <?= $shiftData['efficiency'] >= 95 ? 'bg-success'
                           : ($shiftData['efficiency'] >= 80 ? 'bg-primary'
                           : 'bg-warning') ?>">
                        <?= esc($shiftData['efficiency']) ?> %
                    </span>
                </td>
            </tr>
            </tfoot>

        </table>
    </div>

    <!-- ===== INFO LOCK ===== -->
    <?php if (!$shiftData['canEdit']): ?>
        <div class="alert alert-warning m-2">
            ⛔ Koreksi hanya diperbolehkan pada waktu akhir shift
        </div>
    <?php endif ?>

</div>

<?php endforeach ?>

<?= $this->endSection() ?>
