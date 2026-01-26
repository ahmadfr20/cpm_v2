<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">DIE CASTING – DAILY PRODUCTION PER SHIFT</h4>

<form method="get" class="mb-3 d-flex gap-2 align-items-end">
    <div>
        <label class="form-label small mb-1">Tanggal</label>
        <input type="date" name="date" value="<?= esc($date) ?>" class="form-control">
    </div>

    <div>
        <button class="btn btn-primary btn-sm">
            <i class="bi bi-search"></i> Filter
        </button>
    </div>
</form>

<form method="post" action="/die-casting/daily-production-achievement/store" id="mainFormShift">
    <?= csrf_field() ?>

    <?php foreach ($shifts as $shift): ?>

        <h5 class="mt-4 mb-2">
            <?= esc($shift['shift_name']) ?>
        </h5>

        <?php if (!$shift['isEditable']): ?>
            <div class="alert alert-warning py-2 small">
                <i class="bi bi-lock-fill"></i>
                Koreksi hanya dapat dilakukan <strong>maksimal 1 jam setelah shift berakhir</strong>.
                <?php if (!empty($shift['editDeadline'])): ?>
                    <div class="mt-1">
                        Batas koreksi: <strong><?= esc($shift['editDeadline']) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-success py-2 small">
                <i class="bi bi-unlock-fill"></i>
                Koreksi aktif.
                <?php if (!empty($shift['editDeadline'])): ?>
                    Batas koreksi: <strong><?= esc($shift['editDeadline']) ?></strong>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="table-responsive mb-4">
            <table class="table table-bordered table-sm align-middle">

                <thead class="table-light">
                <tr class="text-center">
                    <th style="width:40px">No</th>
                    <th>Part</th>
                    <th style="width:90px">Target</th>
                    <th style="width:90px">FG (Actual)</th>
                    <th style="width:90px">NG</th>
                    <th style="width:160px">Next Process</th>
                    <th style="width:90px">WIP Qty</th>
                    <th style="width:120px">WIP Status</th>
                    <th style="width:160px">NG Category</th>
                    <th style="width:120px">Downtime (min)</th>
                </tr>
                </thead>

                <tbody>
                <?php
                $no = 1;
                $totalTarget = 0;
                $totalFG = 0;
                $totalNG = 0;
                ?>

                <?php if (empty($shift['items'])): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted">
                            Tidak ada data schedule
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($shift['items'] as $row): ?>
                    <?php
                    $totalTarget += (int)$row['target'];
                    $totalFG     += (int)$row['fg_display'];
                    $totalNG     += (int)$row['ng_display'];

                    $wipStatus = $row['wip_status'] ?? 'WAITING';
                    $badge = 'secondary';
                    if ($wipStatus === 'WAITING') $badge = 'warning';
                    if ($wipStatus === 'SCHEDULED') $badge = 'info';
                    if ($wipStatus === 'DONE') $badge = 'success';
                    ?>

                    <tr>
                        <td class="text-center"><?= $no++ ?></td>

                        <td>
                            <strong><?= esc($row['part_no']) ?></strong><br>
                            <small class="text-muted"><?= esc($row['part_name']) ?></small>
                            <div class="small text-muted mt-1">
                                <span class="me-2">Mesin: <strong><?= esc($row['machine_code'] ?? '-') ?></strong></span>
                            </div>
                        </td>

                        <td class="text-end fw-bold">
                            <?= number_format((int)$row['target']) ?>
                        </td>

                        <td>
                            <input type="number"
                                   class="form-control form-control-sm text-end"
                                   name="items[<?= $shift['id'].'_'.$row['machine_id'].'_'.$row['product_id'] ?>][fg]"
                                   value="<?= (int)$row['fg_display'] ?>"
                                   <?= !$shift['isEditable'] ? 'disabled' : '' ?>>
                        </td>

                        <td>
                            <input type="number"
                                   class="form-control form-control-sm text-end"
                                   name="items[<?= $shift['id'].'_'.$row['machine_id'].'_'.$row['product_id'] ?>][ng]"
                                   value="<?= (int)$row['ng_display'] ?>"
                                   <?= !$shift['isEditable'] ? 'disabled' : '' ?>>
                        </td>

                        <td class="text-center">
                            <?= esc($row['next_process_name'] ?? '-') ?>
                        </td>

                        <td class="text-end">
                            <?= number_format((int)($row['wip_qty'] ?? 0)) ?>
                        </td>

                        <td class="text-center">
                            <span class="badge bg-<?= $badge ?>">
                                <?= esc($wipStatus) ?>
                            </span>
                        </td>

                        <td>
                            <select class="form-select form-select-sm"
                                    name="items[<?= $shift['id'].'_'.$row['machine_id'].'_'.$row['product_id'] ?>][ng_category_id]"
                                    <?= !$shift['isEditable'] ? 'disabled' : '' ?>>
                                <option value="">-- NG --</option>
                                <?php foreach ($ngCategories as $ng): ?>
                                    <option value="<?= $ng['id'] ?>"
                                        <?= ((string)($row['ng_category_id'] ?? '') === (string)$ng['id']) ? 'selected' : '' ?>>
                                        <?= esc($ng['ng_code'].' - '.$ng['ng_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>

                        <td>
                            <input type="number"
                                   class="form-control form-control-sm text-end"
                                   name="items[<?= $shift['id'].'_'.$row['machine_id'].'_'.$row['product_id'] ?>][downtime]"
                                   value="<?= (int)($row['downtime'] ?? 0) ?>"
                                   <?= !$shift['isEditable'] ? 'disabled' : '' ?>>
                        </td>

                        <!-- HIDDEN -->
                        <input type="hidden"
                               name="items[<?= $shift['id'].'_'.$row['machine_id'].'_'.$row['product_id'] ?>][production_id]"
                               value="<?= (int)$row['production_id'] ?>">

                        <input type="hidden"
                               name="items[<?= $shift['id'].'_'.$row['machine_id'].'_'.$row['product_id'] ?>][machine_id]"
                               value="<?= (int)$row['machine_id'] ?>">

                        <input type="hidden"
                               name="items[<?= $shift['id'].'_'.$row['machine_id'].'_'.$row['product_id'] ?>][product_id]"
                               value="<?= (int)$row['product_id'] ?>">

                        <input type="hidden"
                               name="items[<?= $shift['id'].'_'.$row['machine_id'].'_'.$row['product_id'] ?>][shift_id]"
                               value="<?= (int)$shift['id'] ?>">

                        <input type="hidden"
                               name="items[<?= $shift['id'].'_'.$row['machine_id'].'_'.$row['product_id'] ?>][date]"
                               value="<?= esc($date) ?>">

                    </tr>
                <?php endforeach; ?>
                </tbody>

                <tfoot class="table-secondary fw-bold">
                <tr>
                    <td colspan="2" class="text-end">TOTAL</td>
                    <td class="text-end"><?= number_format($totalTarget) ?></td>
                    <td class="text-end"><?= number_format($totalFG) ?></td>
                    <td class="text-end"><?= number_format($totalNG) ?></td>
                    <td colspan="5"></td>
                </tr>

                <tr>
                    <td colspan="2" class="text-end">EFFICIENCY</td>
                    <td colspan="8">
                        <?= $totalTarget > 0 ? round(($totalFG / $totalTarget) * 100, 1) : 0 ?> %
                    </td>
                </tr>
                </tfoot>

            </table>
        </div>

    <?php endforeach; ?>

    <button class="btn btn-success">
        <i class="bi bi-save"></i> Simpan Koreksi
    </button>
</form>

<?= $this->endSection() ?>
