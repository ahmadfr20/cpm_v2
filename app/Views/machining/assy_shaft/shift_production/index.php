<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<?php
// Force timezone supaya konsisten dengan jam shift
$tz = new DateTimeZone('Asia/Jakarta');
$nowDT = new DateTime('now', $tz);
$nowStr = $nowDT->format('Y-m-d H:i:s');
?>

<h4 class="mb-3">
    <i class="bi bi-cpu me-2"></i>
    MACHINING – ASSY SHAFT PRODUCTION PER SHIFT
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
    Target : <?= (int)$dailyTarget ?> |
    FG : <?= (int)$dailyFG ?> |
    NG : <?= (int)$dailyNG ?> |
    Downtime : <?= (int)$dailyDT ?> |
    Efficiency :
    <span class="badge bg-dark"><?= esc($dailyEfficiency) ?> %</span>
</div>

<?php foreach (($shifts ?? []) as $shiftData): ?>

<?php
    // =============================
    // HARD LOCK di VIEW (override)
    // =============================
    $deadlineStr = $shiftData['editDeadline'] ?? null;
    $canEdit = false;

    if (!empty($deadlineStr)) {
        try {
            $deadlineDT = new DateTime($deadlineStr, $tz);
            $canEdit = ($nowDT <= $deadlineDT);
        } catch (\Throwable $e) {
            $canEdit = false;
        }
    }

    // kalau controller salah set isEditable, view ini tetap jadi source of truth
    $badgeClass = $canEdit ? 'bg-warning text-dark' : 'bg-secondary';
    $badgeText  = $canEdit ? 'Koreksi Dibuka' : 'Terkunci';
?>

<div class="card mb-4">
    <div class="card-header bg-light fw-bold d-flex justify-content-between">
        <div>
            <?= esc($shiftData['shift_name']) ?>
            <?php if (!empty($deadlineStr)): ?>
                <small class="text-muted ms-2">
                    (Koreksi sampai: <?= esc($deadlineStr) ?> | Sekarang: <?= esc($nowStr) ?>)
                </small>
            <?php else: ?>
                <small class="text-muted ms-2">(Deadline koreksi tidak ditemukan → terkunci)</small>
            <?php endif; ?>
        </div>

        <span class="badge <?= $badgeClass ?>">
            <?= esc($badgeText) ?>
        </span>
    </div>

    <div class="card-body p-0">
        <form method="post" action="/machining/assy-shaft/production/shift">
            <?= csrf_field() ?>

            <table class="table table-bordered table-sm text-center align-middle mb-0">
                <thead class="table-secondary">
                <tr>
                    <th style="width:60px">No</th>
                    <th style="width:80px">Line</th>
                    <th style="width:120px">Machine</th>
                    <th class="text-start">Part</th>
                    <th style="width:110px">WIP In</th>
                    <th style="width:110px">WIP Status</th>
                    <th style="width:110px">Target</th>
                    <th style="width:90px">FG</th>
                    <th style="width:90px">NG</th>
                    <th style="width:140px">Next Process</th>
                    <th style="width:110px">Efficiency</th>
                </tr>
                </thead>

                <tbody>
                <?php foreach (($shiftData['items'] ?? []) as $r): ?>
                    <?php
                        $target = (int)($r['target'] ?? 0);
                        $fgVal  = (int)($r['fg_display'] ?? 0);
                        $ngVal  = (int)($r['ng_display'] ?? 0);

                        $eff = $target > 0 ? round(($fgVal / $target) * 100, 1) : 0;

                        $wipBadge = 'secondary';
                        if (($r['wip_status'] ?? '') === 'WAITING')   $wipBadge = 'warning';
                        if (($r['wip_status'] ?? '') === 'SCHEDULED') $wipBadge = 'info';
                        if (($r['wip_status'] ?? '') === 'DONE')      $wipBadge = 'success';

                        $key = $r['shift_id'].'_'.$r['machine_id'].'_'.$r['product_id'];
                    ?>
                    <tr>
                        <td><?= esc($r['no']) ?></td>
                        <td class="fw-bold">Line <?= esc($r['line_position']) ?></td>
                        <td><?= esc($r['machine_code']) ?></td>
                        <td class="text-start">
                            <div class="fw-bold"><?= esc($r['part_no']) ?></div>
                            <div class="small text-muted"><?= esc($r['part_name']) ?></div>
                        </td>

                        <td class="fw-bold"><?= (int)($r['wip_qty'] ?? 0) ?></td>
                        <td><span class="badge bg-<?= $wipBadge ?>"><?= esc($r['wip_status'] ?? '-') ?></span></td>

                        <td class="fw-bold"><?= $target ?></td>

                        <td>
                            <input type="number"
                                   class="form-control form-control-sm text-center"
                                   name="items[<?= esc($key) ?>][fg]"
                                   value="<?= $fgVal ?>"
                                   <?= !$canEdit ? 'readonly disabled' : '' ?>>
                        </td>

                        <td>
                            <input type="number"
                                   class="form-control form-control-sm text-center"
                                   name="items[<?= esc($key) ?>][ng]"
                                   value="<?= $ngVal ?>"
                                   <?= !$canEdit ? 'readonly disabled' : '' ?>>
                        </td>

                        <td><?= esc($r['next_process_name'] ?? '-') ?></td>

                        <td class="fw-bold"><?= $eff ?>%</td>

                        <input type="hidden" name="items[<?= esc($key) ?>][date]" value="<?= esc($date) ?>">
                        <input type="hidden" name="items[<?= esc($key) ?>][shift_id]" value="<?= (int)$r['shift_id'] ?>">
                        <input type="hidden" name="items[<?= esc($key) ?>][machine_id]" value="<?= (int)$r['machine_id'] ?>">
                        <input type="hidden" name="items[<?= esc($key) ?>][product_id]" value="<?= (int)$r['product_id'] ?>">
                    </tr>
                <?php endforeach ?>
                </tbody>

                <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="6" class="text-end">TOTAL SHIFT</td>
                    <td><?= (int)($shiftData['totalTarget'] ?? 0) ?></td>
                    <td><?= (int)($shiftData['totalFG'] ?? 0) ?></td>
                    <td><?= (int)($shiftData['totalNG'] ?? 0) ?></td>
                    <td></td>
                    <td>
                        <?php
                            $tTarget = (int)($shiftData['totalTarget'] ?? 0);
                            $tFG     = (int)($shiftData['totalFG'] ?? 0);
                            $effShift = $tTarget > 0 ? round(($tFG / $tTarget) * 100, 1) : 0;
                            echo $effShift.'%';
                        ?>
                    </td>
                </tr>
                </tfoot>
            </table>

            <div class="p-2 d-flex justify-content-end">
                <button class="btn btn-success btn-sm" <?= !$canEdit ? 'disabled' : '' ?>>
                    <i class="bi bi-save"></i> Simpan Koreksi
                </button>
            </div>
        </form>
    </div>
</div>

<?php endforeach ?>

<?= $this->endSection() ?>
