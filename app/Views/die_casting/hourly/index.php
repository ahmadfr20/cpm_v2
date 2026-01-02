<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">
    <i class="bi bi-clock-history me-2"></i>
    HOURLY PRODUCTION ACHIEVEMENT – DIE CASTING
</h4>

<!-- ================= SYSTEM INFO ================= -->
<div class="card mb-3">
    <div class="card-body row g-2">
        <div class="col-md-3">
            <b>Section</b><br>
            Die Casting
        </div>
        <div class="col-md-3">
            <b>Shift</b><br>
            <?= esc($shift['shift_name'] ?? $shiftId) ?>
        </div>
        <div class="col-md-3">
            <b>Date</b><br>
            <?= date('d-m-Y', strtotime($date)) ?>
        </div>
        <div class="col-md-3">
            <b>Time</b><br>
            <?= date('H:i') ?>
        </div>
        <div class="col-md-6 mt-2">
            <b>Operator Name</b><br>
            <?= esc(session()->get('fullname')) ?>
        </div>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success">
    <?= session()->getFlashdata('success') ?>
</div>
<?php endif; ?>

<?php if (empty($rows)): ?>
<div class="alert alert-warning">
    Tidak ada Daily Production Schedule untuk shift ini.
</div>
<?php else: ?>

<!-- ================= FORM HOURLY INPUT ================= -->
<form method="post" action="/die-casting/hourly/store">

<input type="hidden" name="date" value="<?= esc($date) ?>">
<input type="hidden" name="shift_id" value="<?= esc($shiftId) ?>">
<input type="hidden" name="time_slot_id" value="<?= esc($slotId) ?>">

<div class="table-responsive">
<table class="table table-bordered table-sm align-middle">

<thead class="table-light text-center">
<tr>
    <th width="60">Select</th>
    <th>Part No</th>
    <th>Part Name</th>
    <th width="90">Cycle Time</th>
    <th width="110">Target / Hour</th>
    <th width="90">FG</th>
    <th width="90">NG</th>
    <th width="150">NG Category</th>
    <th width="120">Downtime (min)</th>
    <th width="180">Keterangan</th>
</tr>
</thead>

<tbody>
<?php foreach ($rows as $i => $r): ?>

<tr>
    <td class="text-center">
        <input type="checkbox" class="form-check-input"
               checked <?= !$canEdit ? 'disabled' : '' ?>>
    </td>

    <td><?= esc($r['part_no']) ?></td>
    <td><?= esc($r['part_name']) ?></td>

    <!-- SYSTEM -->
    <td class="text-center"><?= $r['cycle_time'] ?></td>
    <td class="text-center"><?= $r['target_per_hour'] ?></td>

    <!-- IDENTITAS -->
    <input type="hidden" name="items[<?= $i ?>][machine_id]" value="<?= $r['machine_id'] ?>">
    <input type="hidden" name="items[<?= $i ?>][product_id]" value="<?= $r['product_id'] ?>">

    <!-- INPUT -->
    <td>
        <input type="number"
               name="items[<?= $i ?>][qty_fg]"
               value="<?= $r['qty_fg'] ?>"
               class="form-control form-control-sm"
               <?= !$canEdit ? 'readonly' : '' ?>>
    </td>

    <td>
        <input type="number"
               name="items[<?= $i ?>][qty_ng]"
               value="<?= $r['qty_ng'] ?>"
               class="form-control form-control-sm"
               <?= !$canEdit ? 'readonly' : '' ?>>
    </td>

    <td>
        <select name="items[<?= $i ?>][ng_category]"
                class="form-select form-select-sm"
                <?= !$canEdit ? 'disabled' : '' ?>>
            <option value="">-</option>
            <option <?= $r['ng_category']=='Flow Line'?'selected':'' ?>>Flow Line</option>
            <option <?= $r['ng_category']=='Gompal'?'selected':'' ?>>Gompal</option>
            <option <?= $r['ng_category']=='Crack'?'selected':'' ?>>Crack</option>
            <option <?= $r['ng_category']=='Porosity'?'selected':'' ?>>Porosity</option>
        </select>
    </td>

    <td>
        <input type="number"
               name="items[<?= $i ?>][downtime]"
               value="<?= $r['downtime_minute'] ?>"
               class="form-control form-control-sm"
               <?= !$canEdit ? 'readonly' : '' ?>>
    </td>

    <td>
        <input type="text"
               name="items[<?= $i ?>][remark]"
               class="form-control form-control-sm"
               <?= !$canEdit ? 'readonly' : '' ?>>
    </td>
</tr>

<?php endforeach ?>
</tbody>

</table>
</div>

<?php if ($canEdit): ?>
<button class="btn btn-success mt-3">
    <i class="bi bi-save"></i> Save Hourly Production
</button>
<?php else: ?>
<div class="alert alert-info mt-3">
    Data terkunci (di luar waktu input shift).
</div>
<?php endif; ?>

</form>
<?php endif; ?>

<?= $this->endSection() ?>
