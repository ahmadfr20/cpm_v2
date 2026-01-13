<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<div class="d-flex align-items-center mb-3">
    <h4 class="mb-0">
        <i class="bi bi-clipboard-data me-2"></i>
        DIE CASTING – DAILY PRODUCTION RESULT
    </h4>

    <div class="ms-auto">
        <button onclick="exportExcel()" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel"></i> Export Excel
        </button>
    </div>
</div>

<form method="get" class="row g-2 mb-4 align-items-end">
    <div class="col-md-3">
        <label class="form-label small">Tanggal Produksi</label>
        <input type="date" name="date" value="<?= esc($date) ?>" class="form-control">
    </div>

    <div class="col-md-2">
        <button class="btn btn-primary w-100">
            <i class="bi bi-search"></i> Load
        </button>
    </div>

    <div class="col-md-3 ms-auto text-end">
        <a href="/die-casting/daily-schedule?date=<?= esc($date) ?>"
           class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</form>

<?php if (empty($rows)): ?>
<div class="alert alert-warning text-center">
    <i class="bi bi-exclamation-circle"></i>
    Tidak ada data produksi
</div>
<?php endif; ?>

<div id="export-area">

<?php
$currentShift = null;
$grandP = $grandA = $grandNG = $grandW = 0;
?>

<?php foreach ($rows as $r): ?>

<?php if ($currentShift !== $r['shift_name']): ?>

    <?php if ($currentShift !== null): ?>
        <?php
            $shiftEff = $shiftP > 0 ? round(($shiftA / $shiftP) * 100, 1) : 0;
        ?>
        </tbody>
        <tfoot class="fw-bold bg-light">
            <tr>
                <td colspan="2" class="text-end">TOTAL SHIFT</td>
                <td class="text-end"><?= number_format($shiftP) ?></td>
                <td class="text-end"><?= number_format($shiftA) ?></td>
                <td class="text-end"><?= number_format($shiftNG) ?></td>
                <td class="text-end"><?= number_format($shiftW,2) ?></td>
                <td></td>
            </tr>
            <tr class="table-info">
                <td colspan="2" class="text-end">EFFICIENCY</td>
                <td colspan="5" class="text-center">
                    <?= $shiftEff ?> %
                </td>
            </tr>
        </tfoot>
        </table>
    <?php endif; ?>

    <?php
        $currentShift = $r['shift_name'];
        $shiftP = $shiftA = $shiftNG = $shiftW = 0;
    ?>

    <h5 class="mt-4 mb-2"><?= esc($currentShift) ?></h5>

    <div class="table-responsive">
    <table class="table table-bordered table-sm align-middle text-center export-table">
        <thead class="table-secondary">
        <tr>
            <th>Machine</th>
            <th class="text-start">Part</th>
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
    <td class="text-start"><?= esc($r['part_no'] ?? '-') ?></td>
    <td class="text-end"><?= number_format($r['qty_p']) ?></td>
    <td class="text-end"><?= number_format($r['qty_a']) ?></td>
    <td class="text-end"><?= number_format($r['qty_ng']) ?></td>
    <td class="text-end"><?= number_format($r['weight_kg'],2) ?></td>
    <td>
        <span class="badge
            <?= match($r['status']) {
                'OFF'      => 'bg-secondary',
                'Trial'    => 'bg-warning text-dark',
                'Recovery' => 'bg-info text-dark',
                default    => 'bg-success'
            } ?>">
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
<?php
    $shiftEff = $shiftP > 0 ? round(($shiftA / $shiftP) * 100, 1) : 0;
?>
</tbody>
<tfoot class="fw-bold bg-light">
<tr>
    <td colspan="2" class="text-end">TOTAL SHIFT</td>
    <td class="text-end"><?= number_format($shiftP) ?></td>
    <td class="text-end"><?= number_format($shiftA) ?></td>
    <td class="text-end"><?= number_format($shiftNG) ?></td>
    <td class="text-end"><?= number_format($shiftW,2) ?></td>
    <td></td>
</tr>
<tr class="table-info">
    <td colspan="2" class="text-end">EFFICIENCY</td>
    <td colspan="5" class="text-center"><?= $shiftEff ?> %</td>
</tr>
</tfoot>
</table>
</div>
<?php endif; ?>

<?php if (!empty($rows)): ?>
<?php
$grandEff = $grandP > 0 ? round(($grandA / $grandP) * 100, 1) : 0;
?>
<div class="card border-primary mt-4">
    <div class="card-header bg-primary text-white fw-bold">
        GRAND TOTAL – <?= esc($date) ?>
    </div>
    <div class="card-body">
        <div class="row text-center fw-bold">
            <div class="col">P<br><?= number_format($grandP) ?></div>
            <div class="col">A<br><?= number_format($grandA) ?></div>
            <div class="col">NG<br><?= number_format($grandNG) ?></div>
            <div class="col">Weight<br><?= number_format($grandW,2) ?> kg</div>
            <div class="col text-success">
                Eff<br><?= $grandEff ?> %
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

</div>

<?= $this->endSection() ?>

<!-- ================= EXCEL EXPORT ================= -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
function exportExcel() {
    const wb = XLSX.utils.book_new();

    document.querySelectorAll('.export-table').forEach((table, i) => {
        const ws = XLSX.utils.table_to_sheet(table);
        XLSX.utils.book_append_sheet(wb, ws, 'Shift ' + (i + 1));
    });

    XLSX.writeFile(
        wb,
        'Die_Casting_Daily_Production_<?= $date ?>.xlsx'
    );
}
</script>
