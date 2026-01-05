<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">
    <i class="bi bi-gear-wide-connected me-2"></i>
    Machining Production – Per Shift
</h4>

<!-- FILTER DATE -->
<form method="get" class="row g-2 mb-3">
    <div class="col-auto">
        <input type="date" name="date" value="<?= esc($date) ?>" class="form-control">
    </div>
    <div class="col-auto">
        <button class="btn btn-primary">
            <i class="bi bi-search"></i> Filter
        </button>
    </div>
</form>

<?php foreach ($shifts as $shift): ?>
<div class="card mb-4">

    <div class="card-header bg-light fw-bold">
        Shift : <?= esc($shift['shift_name']) ?>
    </div>

    <div class="card-body p-0">

    <?php if (empty($data[$shift['id']] ?? [])): ?>
        <div class="p-3 text-center text-muted">
            Tidak ada schedule machining pada shift ini
        </div>
    <?php else: ?>

    <div class="table-responsive">
    <table class="table table-bordered table-sm align-middle text-center mb-0">

        <thead class="table-light">
        <tr>
            <th>Machine</th>
            <th>Part No</th>
            <th>Part Name</th>
            <th>Target / Shift</th>
            <th>FG</th>
            <th>NG</th>
            <th>Efficiency</th>
            <th>Downtime (min)</th>
            <th>Status</th>
        </tr>
        </thead>

        <tbody>
        <?php foreach ($data[$shift['id']] as $row): ?>
        <?php
            $target = (int) $row['target_per_shift'];
            $fg     = (int) $row['total_fg'];

            $efficiency = $target > 0
                ? round(($fg / $target) * 100, 1)
                : 0;

            if ($fg == 0) {
                $status = 'Planned';
                $badge  = 'secondary';
            } elseif ($efficiency >= 95) {
                $status = 'Excellent';
                $badge  = 'success';
            } elseif ($efficiency >= 80) {
                $status = 'Normal';
                $badge  = 'primary';
            } elseif ($efficiency > 0) {
                $status = 'Below Target';
                $badge  = 'warning';
            } else {
                $status = 'NG';
                $badge  = 'danger';
            }
        ?>
        <tr>
            <td><?= esc($row['machine_code']) ?></td>
            <td><?= esc($row['part_no']) ?></td>
            <td><?= esc($row['part_name']) ?></td>
            <td><?= esc($target) ?></td>
            <td><?= esc($fg) ?></td>
            <td><?= esc($row['total_ng']) ?></td>

            <!-- EFFICIENCY -->
            <td>
                <span class="fw-bold text-<?= 
                    $efficiency >= 95 ? 'success' :
                    ($efficiency >= 80 ? 'primary' : 'danger')
                ?>">
                    <?= $efficiency ?> %
                </span>
            </td>

            <td><?= esc($row['total_downtime']) ?></td>

            <td>
                <span class="badge bg-<?= $badge ?>">
                    <?= $status ?>
                </span>
            </td>
        </tr>
        <?php endforeach ?>
        </tbody>

    </table>
    </div>

    <?php endif; ?>

    </div>
</div>
<?php endforeach ?>

<?= $this->endSection() ?>
