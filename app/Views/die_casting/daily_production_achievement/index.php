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

<form method="post" action="/die-casting/daily-production-achievement/store">
<?= csrf_field() ?>

<?php foreach ($shifts as $shift): ?>

<h5 class="mt-4 mb-2">
    <?= esc($shift['shift_name']) ?>
</h5>

<?php if (!$shift['isEditable']): ?>
<div class="alert alert-warning py-2 small">
    <i class="bi bi-lock-fill"></i>
    Koreksi hanya dapat dilakukan <strong>setelah shift berakhir</strong>.
</div>
<?php endif; ?>

<div class="table-responsive mb-4">
<table class="table table-bordered table-sm align-middle">

<thead class="table-light">
<tr class="text-center">
    <th style="width:40px">No</th>
    <th>Part</th>
    <th style="width:90px">Target</th>
    <th style="width:90px">FG</th>
    <th style="width:90px">NG</th>
    <th style="width:160px">NG Category</th>
    <th style="width:120px">Downtime (min)</th>
    <th>Keterangan</th>
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
    <td colspan="8" class="text-center text-muted">
        Tidak ada data schedule
    </td>
</tr>
<?php endif; ?>

<?php foreach ($shift['items'] as $row): ?>
<?php
    $totalTarget += $row['target'];
    $totalFG += $row['total_fg'];
    $totalNG += $row['total_ng'];
?>
<tr>
    <td class="text-center"><?= $no++ ?></td>

    <td>
        <strong><?= esc($row['part_no']) ?></strong><br>
        <small class="text-muted"><?= esc($row['part_name']) ?></small>
    </td>

    <td class="text-end fw-bold">
        <?= number_format($row['target']) ?>
    </td>

    <td>
        <input type="number"
               class="form-control form-control-sm text-end"
               name="items[<?= $shift['id'].'_'.$row['machine_id'].'_'.$row['product_id'] ?>][fg]"
               value="<?= $row['total_fg'] ?>"
               <?= !$shift['isEditable'] ? 'disabled' : '' ?>>
    </td>

    <td>
        <input type="number"
               class="form-control form-control-sm text-end"
               name="items[<?= $shift['id'].'_'.$row['machine_id'].'_'.$row['product_id'] ?>][ng]"
               value="<?= $row['total_ng'] ?>"
               <?= !$shift['isEditable'] ? 'disabled' : '' ?>>
    </td>

    <td>
        <select class="form-select form-select-sm"
                name="items[<?= $shift['id'].'_'.$row['machine_id'].'_'.$row['product_id'] ?>][ng_category]"
                <?= !$shift['isEditable'] ? 'disabled' : '' ?>>
            <option value="">-</option>
            <?php foreach (['Flow Line','Crack','Porosity','Short Shot','Others'] as $cat): ?>
                <option value="<?= $cat ?>"
                    <?= ($row['ng_category'] ?? '') === $cat ? 'selected' : '' ?>>
                    <?= $cat ?>
                </option>
            <?php endforeach ?>
        </select>
    </td>

    <td>
        <input type="number"
               class="form-control form-control-sm text-end"
               name="items[<?= $shift['id'].'_'.$row['machine_id'].'_'.$row['product_id'] ?>][downtime]"
               value="<?= $row['downtime'] ?>"
               <?= !$shift['isEditable'] ? 'disabled' : '' ?>>
    </td>

    <td>
        <input type="text"
               class="form-control form-control-sm"
               name="items[<?= $shift['id'].'_'.$row['machine_id'].'_'.$row['product_id'] ?>][remark]"
               <?= !$shift['isEditable'] ? 'disabled' : '' ?>>
    </td>

    <!-- HIDDEN -->
    <input type="hidden"
           name="items[<?= $shift['id'].'_'.$row['machine_id'].'_'.$row['product_id'] ?>][machine_id]"
           value="<?= $row['machine_id'] ?>">
    <input type="hidden"
           name="items[<?= $shift['id'].'_'.$row['machine_id'].'_'.$row['product_id'] ?>][product_id]"
           value="<?= $row['product_id'] ?>">
    <input type="hidden"
           name="items[<?= $shift['id'].'_'.$row['machine_id'].'_'.$row['product_id'] ?>][shift_id]"
           value="<?= $shift['id'] ?>">
    <input type="hidden"
           name="items[<?= $shift['id'].'_'.$row['machine_id'].'_'.$row['product_id'] ?>][date]"
           value="<?= $date ?>">
</tr>
<?php endforeach ?>
</tbody>

<tfoot class="table-secondary fw-bold">
<tr>
    <td colspan="2" class="text-end">TOTAL</td>
    <td class="text-end"><?= number_format($totalTarget) ?></td>
    <td class="text-end"><?= number_format($totalFG) ?></td>
    <td class="text-end"><?= number_format($totalNG) ?></td>
    <td colspan="3"></td>
</tr>

<tr>
    <td colspan="2" class="text-end">EFFICIENCY</td>
    <td colspan="6">
        <?= $totalTarget > 0
            ? round(($totalFG / $totalTarget) * 100, 1)
            : 0 ?> %
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
