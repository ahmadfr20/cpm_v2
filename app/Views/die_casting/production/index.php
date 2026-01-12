<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">
    <i class="bi bi-bar-chart-fill me-2"></i>
    DIE CASTING – DAILY PRODUCTION ACHIEVEMENT
</h4>

<!-- ================= FILTER ================= -->
<form method="get" class="row g-2 mb-3 align-items-end">
    <div class="col-md-3">
        <label class="form-label">Tanggal Produksi</label>
        <input type="date" name="date" value="<?= esc($date) ?>" class="form-control">
    </div>
    <div class="col-md-2">
        <button class="btn btn-primary">
            <i class="bi bi-search"></i> Load Data
        </button>
    </div>
</form>

<!-- ================= DAILY SUMMARY ================= -->
<div class="card mb-4 border-info">
    <div class="card-header bg-info text-white fw-bold">
        DAILY SUMMARY – <?= esc($date) ?>
    </div>
    <div class="card-body">
        <div class="row text-center fw-bold">
            <div class="col-md-3">
                Target<br><?= number_format($dailyTarget) ?> pcs
            </div>
            <div class="col-md-3">
                FG<br><?= number_format($dailyFG) ?> pcs
            </div>
            <div class="col-md-3">
                Total Weight<br><?= number_format($dailyWeight, 2) ?> kg
            </div>
            <div class="col-md-3">
                Efficiency<br>
                <span class="badge
                    <?= $dailyEfficiency >= 95 ? 'bg-success'
                       : ($dailyEfficiency >= 80 ? 'bg-primary'
                       : 'bg-warning') ?>">
                    <?= $dailyEfficiency ?> %
                </span>
            </div>
        </div>
    </div>
</div>

<!-- ================= PER SHIFT ================= -->
<?php foreach ($data as $shiftIndex => $shiftData): ?>

<form method="post" action="/die-casting/production/save-correction">
<?= csrf_field() ?>

<div class="card mb-4">

    <!-- ===== SHIFT HEADER ===== -->
    <div class="card-header bg-light fw-bold d-flex justify-content-between">
        <div>
            <?= esc($shiftData['shift']['shift_name']) ?>
            (<?= esc($shiftData['start_time']) ?> - <?= esc($shiftData['end_time']) ?>)
        </div>
        <?php if ($shiftData['canEdit']): ?>
            <span class="badge bg-warning text-dark">
                ⏱ Koreksi Diizinkan
            </span>
        <?php else: ?>
            <span class="badge bg-secondary">
                🔒 Terkunci
            </span>
        <?php endif ?>
    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-sm align-middle text-center mb-0">

            <thead class="table-secondary">
            <tr>
                <th>Line</th>
                <th>Machine</th>
                <th>Part</th>
                <th>Target</th>
                <th>FG</th>
                <th>NG</th>
                <th>Weight / pcs (kg)</th>
                <th>Total Weight (kg)</th>
                <th>Downtime (min)</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($shiftData['rows'] as $i => $r): ?>
                <tr>
                    <td>Line <?= esc($r['line_position']) ?></td>
                    <td><?= esc($r['machine_code']) ?></td>
                    <td><?= esc($r['part_no']) ?> - <?= esc($r['part_name']) ?></td>
                    <td><?= esc($r['target_per_shift']) ?></td>

                    <!-- FG -->
                    <td>
                        <?php if ($shiftData['canEdit']): ?>
                            <input type="number"
                                   name="items[<?= $i ?>][fg]"
                                   value="<?= esc($r['fg']) ?>"
                                   class="form-control form-control-sm text-end">
                        <?php else: ?>
                            <?= esc($r['fg']) ?>
                        <?php endif ?>
                    </td>

                    <!-- NG -->
                    <td>
                        <?php if ($shiftData['canEdit']): ?>
                            <input type="number"
                                   name="items[<?= $i ?>][ng]"
                                   value="<?= esc($r['ng']) ?>"
                                   class="form-control form-control-sm text-end">
                        <?php else: ?>
                            <?= esc($r['ng']) ?>
                        <?php endif ?>
                    </td>

                    <td><?= number_format($r['weight'], 2) ?></td>
                    <td><?= number_format($r['total_weight'], 2) ?></td>

                    <!-- Downtime -->
                    <td>
                        <?php if ($shiftData['canEdit']): ?>
                            <input type="number"
                                   name="items[<?= $i ?>][downtime]"
                                   value="<?= esc($r['downtime']) ?>"
                                   class="form-control form-control-sm text-end">
                        <?php else: ?>
                            <?= esc($r['downtime']) ?>
                        <?php endif ?>
                    </td>

                    <!-- hidden id -->
                    <input type="hidden"
                           name="items[<?= $i ?>][hourly_id]"
                           value="<?= esc($r['schedule_item_id']) ?>">
                </tr>
            <?php endforeach ?>
            </tbody>

            <!-- ===== TOTAL SHIFT ===== -->
            <tfoot class="table-light fw-bold">
            <tr>
                <td colspan="3" class="text-end">TOTAL SHIFT</td>
                <td><?= esc($shiftData['totalTarget']) ?></td>
                <td><?= esc($shiftData['totalFG']) ?></td>
                <td colspan="2">
                    Weight<br><?= number_format($shiftData['totalWeight'], 2) ?> kg
                </td>
                <td colspan="2">
                    Efficiency<br>
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

        <!-- ===== SAVE BUTTON ===== -->
        <?php if ($shiftData['canEdit']): ?>
            <div class="p-3 text-end">
                <button class="btn btn-warning">
                    <i class="bi bi-pencil-square"></i>
                    Simpan Koreksi Shift
                </button>
            </div>
        <?php else: ?>
            <div class="alert alert-secondary m-3 text-center">
                ⛔ Koreksi hanya diperbolehkan ±1 jam dari akhir shift
            </div>
        <?php endif ?>

    </div>
</div>

</form>

<?php endforeach ?>

<?= $this->endSection() ?>
