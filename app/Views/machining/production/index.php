<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">
    <i class="bi bi-cpu me-2"></i>
    MACHINING – DAILY PRODUCTION ACHIEVEMENT
</h4>

<form method="get" class="row g-2 mb-3 align-items-end">
    <div class="col-md-3">
        <label>Tanggal Produksi</label>
        <input type="date" name="date" value="<?= esc($date) ?>" class="form-control">
    </div>
    <div class="col-md-2">
        <button class="btn btn-primary">Load Data</button>
    </div>
</form>

<div class="alert alert-success fw-bold">
    DAILY SUMMARY<br>
    Target : <?= $dailyTarget ?> |
    FG : <?= $dailyFG ?> |
    NG : <?= $dailyNG ?> |
    Downtime : <?= $dailyDT ?> |
    Efficiency :
    <span class="badge bg-dark"><?= $dailyEfficiency ?> %</span>
</div>

<?php foreach ($data as $shiftData): ?>

<div class="card mb-4">
    <div class="card-header bg-light fw-bold d-flex justify-content-between">
        <div>
            <?= esc($shiftData['shift']['shift_name']) ?>
            (<?= esc($shiftData['start_time']) ?> - <?= esc($shiftData['end_time']) ?>)
        </div>
        <span class="badge <?= $shiftData['canEdit'] ? 'bg-warning text-dark' : 'bg-secondary' ?>">
            <?= $shiftData['canEdit'] ? 'Koreksi Dibuka' : 'Terkunci' ?>
        </span>
    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-sm text-center align-middle mb-0">
            <thead class="table-secondary">
            <tr>
                <th>Line</th>
                <th>Machine</th>
                <th>Part</th>
                <th>Target</th>
                <th>FG</th>
                <th>NG</th>
                <th>Downtime</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($shiftData['rows'] as $r): ?>
                <tr>
                    <td>Line <?= esc($r['line_position']) ?></td>
                    <td><?= esc($r['machine_code']) ?></td>
                    <td><?= esc($r['part_no'].' - '.$r['part_name']) ?></td>
                    <td><?= esc($r['target_per_shift']) ?></td>
                    <td><?= esc($r['fg']) ?></td>
                    <td><?= esc($r['ng']) ?></td>
                    <td><?= esc($r['downtime']) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>

            <tfoot class="table-light fw-bold">
            <tr>
                <td colspan="3" class="text-end">TOTAL SHIFT</td>
                <td><?= $shiftData['totalTarget'] ?></td>
                <td><?= $shiftData['totalFG'] ?></td>
                <td><?= $shiftData['totalNG'] ?></td>
                <td><?= $shiftData['totalDT'] ?></td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php endforeach ?>

<?= $this->endSection() ?>
