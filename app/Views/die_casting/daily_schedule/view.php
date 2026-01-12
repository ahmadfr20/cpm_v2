<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">
    <i class="bi bi-eye me-2"></i>
    DIE CASTING – DAILY PRODUCTION RESULT
</h4>

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
            <i class="bi bi-search"></i> Load
        </button>
    </div>
    <div class="col-md-3 ms-auto text-end">
        <a href="/die-casting/daily-schedule?date=<?= esc($date) ?>"
           class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Input
        </a>
    </div>
</form>

<?php
$currentShift = null;

$grandP = 0;
$grandA = 0;
$grandNG = 0;
$grandW = 0;
?>

<?php if (empty($rows)): ?>
<div class="alert alert-warning text-center">
    <i class="bi bi-exclamation-circle"></i>
    Tidak ada data produksi pada tanggal ini
</div>
<?php endif; ?>

<?php foreach ($rows as $i => $r): ?>

<?php if ($currentShift !== $r['shift_name']): ?>

    <?php if ($currentShift !== null): ?>
        </tbody>
        <tfoot class="table-light fw-bold">
        <tr>
            <td colspan="3" class="text-end">TOTAL SHIFT</td>
            <td><?= number_format($shiftP) ?></td>
            <td><?= number_format($shiftA) ?></td>
            <td><?= number_format($shiftNG) ?></td>
            <td><?= number_format($shiftW, 2) ?></td>
            <td></td>
        </tr>
        </tfoot>
        </table>
    <?php endif; ?>

    <?php
    $currentShift = $r['shift_name'];
    $shiftP = $shiftA = $shiftNG = $shiftW = 0;
    ?>

    <h5 class="mt-4"><?= esc($currentShift) ?></h5>

    <table class="table table-bordered table-sm text-center align-middle">
        <thead class="table-secondary">
        <tr>
            <th>Machine</th>
            <th>Part</th>
            <th>P</th>
            <th>A</th>
            <th>NG</th>
            <th>Weight (kg)</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>

<?php endif; ?>

<tr>
    <td><?= esc($r['machine_code']) ?></td>
    <td><?= esc($r['part_no'] ?? '-') ?></td>
    <td><?= number_format($r['qty_p']) ?></td>
    <td><?= number_format($r['qty_a']) ?></td>
    <td><?= number_format($r['qty_ng']) ?></td>
    <td><?= number_format($r['weight_kg'], 2) ?></td>
    <td>
        <span class="badge
            <?= $r['status'] === 'OFF' ? 'bg-secondary'
               : ($r['status'] === 'Trial' ? 'bg-warning text-dark'
               : ($r['status'] === 'Recovery' ? 'bg-info text-dark'
               : 'bg-success')) ?>">
            <?= esc($r['status']) ?>
        </span>
    </td>
</tr>

<?php
$shiftP += $r['qty_p'];
$shiftA += $r['qty_a'];
$shiftNG += $r['qty_ng'];
$shiftW += $r['weight_kg'];

$grandP += $r['qty_p'];
$grandA += $r['qty_a'];
$grandNG += $r['qty_ng'];
$grandW += $r['weight_kg'];
?>

<?php endforeach; ?>

<?php if ($currentShift !== null): ?>
</tbody>
<tfoot class="table-light fw-bold">
<tr>
    <td colspan="3" class="text-end">TOTAL SHIFT</td>
    <td><?= number_format($shiftP) ?></td>
    <td><?= number_format($shiftA) ?></td>
    <td><?= number_format($shiftNG) ?></td>
    <td><?= number_format($shiftW, 2) ?></td>
    <td></td>
</tr>
</tfoot>
</table>
<?php endif; ?>

<?php if (!empty($rows)): ?>
<!-- ================= GRAND TOTAL ================= -->
<div class="card border-info mt-4">
    <div class="card-header bg-info text-white fw-bold">
        DAILY GRAND TOTAL – <?= esc($date) ?>
    </div>
    <div class="card-body">
        <div class="row text-center fw-bold">
            <div class="col-md-3">
                P<br><?= number_format($grandP) ?>
            </div>
            <div class="col-md-3">
                A<br><?= number_format($grandA) ?>
            </div>
            <div class="col-md-3">
                NG<br><?= number_format($grandNG) ?>
            </div>
            <div class="col-md-3">
                Weight<br><?= number_format($grandW, 2) ?> kg
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
