<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<div class="d-flex align-items-center mb-3">
    <h4 class="mb-0">
        <i class="bi bi-box-seam me-2"></i>
        DIE CASTING – INVENTORY (WIP)
    </h4>

    <div class="ms-auto">
        <a href="/die-casting/daily-schedule?date=<?= esc($date) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<form method="get" class="row g-2 mb-3 align-items-end">
    <div class="col-md-3">
        <label class="form-label small">Tanggal</label>
        <input type="date" name="date" value="<?= esc($date) ?>" class="form-control">
    </div>

    <div class="col-md-2">
        <button class="btn btn-primary w-100">
            <i class="bi bi-search"></i> Load
        </button>
    </div>
</form>

<?php if (empty($rows)): ?>
    <div class="alert alert-warning text-center">
        <i class="bi bi-exclamation-circle"></i>
        Tidak ada data inventory untuk tanggal ini.
    </div>
<?php else: ?>

<?php
$currentShift = null;

// grand total
$grandP = $grandA = $grandNG = 0;
$grandIn = $grandOut = $grandStock = 0;
?>

<?php foreach ($rows as $r): ?>

    <?php
    $shiftName = $r['shift_name'] ?? '-';
    if ($currentShift !== $shiftName):
        // tutup tabel shift sebelumnya
        if ($currentShift !== null):
            ?>
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="4" class="text-end">TOTAL SHIFT</td>
                    <td class="text-end"><?= number_format($shiftP) ?></td>
                    <td class="text-end"><?= number_format($shiftA) ?></td>
                    <td class="text-end"><?= number_format($shiftNG) ?></td>
                    <td class="text-end"><?= number_format($shiftIn) ?></td>
                    <td class="text-end"><?= number_format($shiftOut) ?></td>
                    <td class="text-end"><?= number_format($shiftStock) ?></td>
                    <td></td>
                </tr>
            </tfoot>
            </table>
            </div>
            <?php
        endif;

        // reset counter shift baru
        $currentShift = $shiftName;
        $shiftP = $shiftA = $shiftNG = 0;
        $shiftIn = $shiftOut = $shiftStock = 0;
        ?>

        <h5 class="mt-4 mb-2"><?= esc($currentShift) ?></h5>

        <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle text-center">
            <thead class="table-secondary">
            <tr>
                <th style="width:110px">Machine</th>
                <th class="text-start" style="width:320px">Part</th>
                <th style="width:90px">Plan (P)</th>
                <th style="width:90px">Actual (A)</th>
                <th style="width:90px">NG</th>

                <th style="width:110px">Qty In</th>
                <th style="width:110px">Qty Out</th>
                <th style="width:110px">Stock</th>

                <th style="width:160px">Next Process</th>
                <th style="width:120px">WIP Status</th>
            </tr>
            </thead>
            <tbody>

    <?php endif; ?>

    <?php
        $qtyP = (int)($r['qty_p'] ?? 0);
        $qtyA = (int)($r['qty_a'] ?? 0);
        $qtyNG = (int)($r['qty_ng'] ?? 0);

        $qtyIn  = (int)($r['qty_in'] ?? 0);
        $qtyOut = (int)($r['qty_out'] ?? 0);
        $stock  = (int)($r['stock'] ?? 0);

        $wipStatus = (string)($r['wip_status'] ?? '-');
        $nextName  = (string)($r['next_process_name'] ?? '-');

        // badge wip
        $badge = 'secondary';
        if ($wipStatus === 'WAITING')   $badge = 'warning';
        if ($wipStatus === 'SCHEDULED') $badge = 'info';
        if ($wipStatus === 'DONE')      $badge = 'success';

        // totals shift + grand
        $shiftP += $qtyP;   $grandP += $qtyP;
        $shiftA += $qtyA;   $grandA += $qtyA;
        $shiftNG += $qtyNG; $grandNG += $qtyNG;

        $shiftIn += $qtyIn;     $grandIn += $qtyIn;
        $shiftOut += $qtyOut;   $grandOut += $qtyOut;
        $shiftStock += $stock;  $grandStock += $stock;
    ?>

    <tr>
        <td class="fw-bold text-primary"><?= esc($r['machine_code'] ?? '-') ?></td>
        <td class="text-start">
            <div class="fw-bold"><?= esc($r['part_no'] ?? '-') ?></div>
            <div class="small text-muted"><?= esc($r['part_name'] ?? '-') ?></div>
        </td>

        <td class="text-end"><?= number_format($qtyP) ?></td>
        <td class="text-end"><?= number_format($qtyA) ?></td>
        <td class="text-end"><?= number_format($qtyNG) ?></td>

        <td class="text-end fw-bold"><?= number_format($qtyIn) ?></td>
        <td class="text-end fw-bold"><?= number_format($qtyOut) ?></td>
        <td class="text-end fw-bold"><?= number_format($stock) ?></td>

        <td><?= esc($nextName) ?></td>

        <td>
            <span class="badge bg-<?= $badge ?>">
                <?= esc($wipStatus) ?>
            </span>
        </td>
    </tr>

<?php endforeach; ?>

<?php if ($currentShift !== null): ?>
    </tbody>
    <tfoot class="table-light fw-bold">
        <tr>
            <td colspan="2" class="text-end">TOTAL SHIFT</td>
            <td class="text-end"><?= number_format($shiftP) ?></td>
            <td class="text-end"><?= number_format($shiftA) ?></td>
            <td class="text-end"><?= number_format($shiftNG) ?></td>
            <td class="text-end"><?= number_format($shiftIn) ?></td>
            <td class="text-end"><?= number_format($shiftOut) ?></td>
            <td class="text-end"><?= number_format($shiftStock) ?></td>
            <td></td>
            <td></td>
        </tr>
    </tfoot>
    </table>
    </div>
<?php endif; ?>

<div class="card border-primary mt-4">
    <div class="card-header bg-primary text-white fw-bold">
        GRAND TOTAL – <?= esc($date) ?>
    </div>
    <div class="card-body">
        <div class="row text-center fw-bold">
            <div class="col">Plan (P)<br><?= number_format($grandP) ?></div>
            <div class="col">Actual (A)<br><?= number_format($grandA) ?></div>
            <div class="col">NG<br><?= number_format($grandNG) ?></div>
            <div class="col">Qty In<br><?= number_format($grandIn) ?></div>
            <div class="col">Qty Out<br><?= number_format($grandOut) ?></div>
            <div class="col">Stock<br><?= number_format($grandStock) ?></div>
        </div>
    </div>
</div>

<?php endif; ?>

<?= $this->endSection() ?>
