<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">WIP FROM DAILY SCHEDULE</h4>

<form method="get" class="row g-2 mb-3 align-items-end" style="max-width:900px">
    <div class="col-md-3">
        <label class="form-label fw-bold">Tanggal</label>
        <input type="date" name="date" value="<?= esc($date) ?>" class="form-control">
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold">Section</label>
        <select name="section" class="form-select">
            <option value="">-- All Section --</option>
            <?php foreach (($sectionList ?? []) as $sec): ?>
                <option value="<?= esc($sec) ?>" <?= ($section === $sec) ? 'selected' : '' ?>>
                    <?= esc($sec) ?>
                </option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="col-md-2">
        <button class="btn btn-primary w-100">Filter</button>
    </div>
</form>

<?php if (!$grouped || count($grouped) === 0): ?>
    <div class="alert alert-warning">Tidak ada WIP untuk tanggal ini.</div>
<?php else: ?>

    <?php foreach ($grouped as $shift): ?>
        <hr>
        <h5 class="mt-3 mb-2">
            <?= esc($shift['shift_name']) ?>
            <?php if (!empty($section)): ?>
                <small class="text-muted"> | <?= esc($section) ?></small>
            <?php endif; ?>
        </h5>

        <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle text-center">
            <thead class="table-secondary">
                <tr>
                    <th style="width:70px">Line</th>
                    <th style="width:120px">Mesin</th>
                    <th class="text-start">Tipe Mesin</th>
                    <th class="text-start" style="width:280px">Part</th>
                    <th style="width:110px">Qty (WIP)</th>
                    <th style="width:140px">From</th>
                    <th style="width:140px">To</th>
                    <th style="width:120px">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($shift['items'] as $r): ?>
                <tr>
                    <td><?= esc($r['line_position'] ?? '-') ?></td>
                    <td class="fw-bold text-primary"><?= esc($r['machine_code'] ?? '-') ?></td>
                    <td class="text-start"><?= esc($r['machine_name'] ?? '-') ?></td>
                    <td class="text-start">
                        <div class="fw-bold"><?= esc($r['part_no'] ?? '-') ?></div>
                        <div class="small text-muted"><?= esc($r['part_name'] ?? '-') ?></div>
                    </td>

                    <td class="fw-bold"><?= number_format((int)($r['qty'] ?? 0)) ?></td>

                    <td><?= esc($r['from_process_name'] ?? '-') ?></td>
                    <td><?= esc($r['to_process_name'] ?? '-') ?></td>

                    <td>
                        <?php
                            $st = $r['status'] ?? '';
                            $badge = 'secondary';
                            if ($st === 'WAITING')   $badge = 'warning';
                            if ($st === 'SCHEDULED') $badge = 'info';
                            if ($st === 'DONE')      $badge = 'success';
                        ?>
                        <span class="badge bg-<?= $badge ?>"><?= esc($st ?: '-') ?></span>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

<?= $this->endSection() ?>
