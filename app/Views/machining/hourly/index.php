<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">
    <i class="bi bi-tools me-2"></i>
    Hourly Production Achievement – Machining
</h4>

<!-- ================= INFO SYSTEM ================= -->
<div class="card mb-3">
    <div class="card-body row">
        <div class="col-md-3">
            <b>Date</b><br>
            <?= date('d-m-Y', strtotime($date)) ?>
        </div>
        <div class="col-md-3">
            <b>Shift</b><br>
            <?= esc($shiftName) ?>
        </div>
        <div class="col-md-3">
            <b>Time</b><br>
            <?= esc($timeLabel) ?>
        </div>
        <div class="col-md-3">
            <b>Operator</b><br>
            <?= esc(session()->get('fullname')) ?>
        </div>
    </div>
</div>

<form method="post" action="/machining/hourly/store">
<?= csrf_field() ?>

<input type="hidden" name="date" value="<?= esc($date) ?>">
<input type="hidden" name="shift_id" value="<?= esc($shiftId) ?>">
<input type="hidden" name="time_slot_id" value="<?= esc($timeSlotId) ?>">

<div class="table-responsive">
<table class="table table-bordered table-sm align-middle">

<thead class="table-light text-center">
<tr>
    <th>Select</th>
    <th>Part No</th>
    <th>Part Name</th>
    <th>Machine</th>
    <th>Cycle Time<br>(sec)</th>
    <th>Target / Hour</th>
    <th>FG</th>
    <th>NG</th>
    <th>NG Category</th>
    <th>Downtime<br>(min)</th>
    <th>Remark</th>
</tr>
</thead>

<tbody>
<?php if (empty($items)): ?>
<tr>
    <td colspan="11" class="text-center text-muted">
        Tidak ada schedule machining untuk shift & tanggal ini
    </td>
</tr>
<?php else: ?>

<?php foreach ($items as $i => $row): ?>
<tr>
    <td class="text-center">
        <input type="checkbox"
               name="items[<?= $i ?>][selected]"
               value="1"
               checked>
    </td>

    <td><?= esc($row['part_no']) ?></td>
    <td><?= esc($row['part_name']) ?></td>
    <td><?= esc($row['machine_code']) ?></td>

    <!-- SYSTEM -->
    <td class="text-center"><?= esc($row['cycle_time']) ?></td>
    <td class="text-center"><?= esc($row['target_per_hour']) ?></td>

    <!-- OPERATOR INPUT -->
    <td>
        <input type="number"
               name="items[<?= $i ?>][qty_fg]"
               class="form-control form-control-sm"
               min="0"
               required>
    </td>

    <td>
        <input type="number"
               name="items[<?= $i ?>][qty_ng]"
               class="form-control form-control-sm"
               min="0">
    </td>

    <td>
        <select name="items[<?= $i ?>][ng_category]"
                class="form-select form-select-sm">
            <option value="">-</option>
            <option value="Tool Problem">Tool Problem</option>
            <option value="Machine Trouble">Machine Trouble</option>
            <option value="Setting">Setting</option>
            <option value="Operator Error">Operator Error</option>
        </select>
    </td>

    <td>
        <input type="number"
               name="items[<?= $i ?>][downtime]"
               class="form-control form-control-sm"
               min="0">
    </td>

    <td>
        <input type="text"
               name="items[<?= $i ?>][remark]"
               class="form-control form-control-sm">
    </td>

    <!-- hidden identity -->
    <input type="hidden" name="items[<?= $i ?>][machine_id]" value="<?= $row['machine_id'] ?>">
    <input type="hidden" name="items[<?= $i ?>][product_id]" value="<?= $row['product_id'] ?>">
</tr>
<?php endforeach ?>
<?php endif ?>
</tbody>

</table>
</div>

<button class="btn btn-success mt-3">
    <i class="bi bi-save"></i> Simpan Hourly Production
</button>

</form>

<?= $this->endSection() ?>
