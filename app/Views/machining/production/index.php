<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">
    <i class="bi bi-gear-wide-connected me-2"></i>
    Machining Production – Per Shift
</h4>

<!-- FILTER DATE -->
<form method="get" class="row g-2 mb-3">
    <div class="col-auto">
        <input type="date"
               name="date"
               value="<?= esc($date) ?>"
               class="form-control">
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

    <?php if (empty($data[$shift['id']])): ?>
        <div class="p-3 text-center text-muted">
            Tidak ada produksi machining pada shift ini
        </div>
    <?php else: ?>

    <div class="table-responsive">
    <table class="table table-bordered table-sm align-middle text-center mb-0">

        <thead class="table-light">
        <tr>
            <th>Machine</th>
            <th>Part No</th>
            <th>Part Name</th>
            <th>FG</th>
            <th>NG</th>
            <th>Downtime (min)</th>
            <th>Status</th>
        </tr>
        </thead>

        <tbody>
        <?php foreach ($data[$shift['id']] as $row): ?>
        <?php
            $status = 'Normal';
            if ($row['total_fg'] == 0) {
                $status = 'No Output';
            } elseif ($row['total_ng'] > 0) {
                $status = 'NG';
            }
        ?>
        <tr>
            <td><?= esc($row['machine_code']) ?></td>
            <td><?= esc($row['part_no']) ?></td>
            <td><?= esc($row['part_name']) ?></td>
            <td><?= esc($row['total_fg']) ?></td>
            <td><?= esc($row['total_ng']) ?></td>
            <td><?= esc($row['total_downtime']) ?></td>
            <td>
                <span class="badge bg-<?= 
                    $status === 'Normal' ? 'success' :
                    ($status === 'NG' ? 'warning' : 'secondary')
                ?>">
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
