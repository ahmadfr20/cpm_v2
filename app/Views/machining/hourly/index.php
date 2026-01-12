<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">
    <i class="bi bi-tools me-2"></i>
    HOURLY PRODUCTION – MACHINING
</h4>

<!-- ================= INFO HEADER ================= -->
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
            <b>Time Slot</b><br>
            <?= esc($timeLabel) ?>
            <?php
                [$start, $end] = explode(' - ', $timeLabel);
                if ($end < $start):
            ?>
                <small class="text-muted">(next day)</small>
            <?php endif ?>
        </div>
        <div class="col-md-3">
            <b>Operator</b><br>
            <?= esc(session()->get('fullname') ?? '-') ?>
        </div>
    </div>
</div>

<form method="post" action="/machining/hourly/store">
<?= csrf_field() ?>

<input type="hidden" name="date" value="<?= esc($date) ?>">
<input type="hidden" name="shift_id" value="<?= esc($shiftId) ?>">
<input type="hidden" name="time_slot_id" value="<?= esc($timeSlotId) ?>">

<!-- ================= TABLE ================= -->
<div class="table-responsive">
<table class="table table-bordered table-sm align-middle text-center">
<thead class="table-secondary">
<tr>
    <th>Line</th>
    <th>Machine</th>
    <th>Part No</th>
    <th>Part Name</th>
    <th>Cycle (sec)</th>
    <th>Target / Hour</th>
    <th width="90">FG</th>
    <th width="90">NG</th>
    <th>NG Category</th>
    <th width="110">Downtime (min)</th>
    <th>Remark</th>
</tr>
</thead>

<tbody>
<?php if (empty($items)): ?>
<tr>
    <td colspan="11" class="text-center text-muted">
        Tidak ada Daily Schedule Machining
    </td>
</tr>
<?php else: ?>

<?php foreach ($items as $i => $row): ?>
<tr>
    <td><?= $i + 1 ?></td>
    <td><?= esc($row['machine_code']) ?></td>
    <td><?= esc($row['part_no']) ?></td>
    <td><?= esc($row['part_name']) ?></td>
    <td><?= esc($row['cycle_time_sec'] ?? '-') ?></td>
    <td><?= esc($row['target_per_hour']) ?></td>

    <td>
        <input type="number"
               name="items[<?= $i ?>][qty_fg]"
               class="form-control form-control-sm text-end"
               min="0">
    </td>

    <td>
        <input type="number"
               name="items[<?= $i ?>][qty_ng]"
               class="form-control form-control-sm text-end"
               min="0">
    </td>

    <td>
        <select name="items[<?= $i ?>][ng_category]"
                class="form-select form-select-sm">
            <option value="">-</option>
            <option value="Tool">Tool</option>
            <option value="Machine">Machine</option>
            <option value="Setting">Setting</option>
            <option value="Operator">Operator</option>
            <option value="Material">Material</option>
        </select>
    </td>

    <td>
        <input type="number"
               name="items[<?= $i ?>][downtime]"
               class="form-control form-control-sm text-end"
               min="0">
    </td>

    <td>
        <input type="text"
               name="items[<?= $i ?>][remark]"
               class="form-control form-control-sm">
    </td>

    <!-- hidden keys -->
    <input type="hidden" name="items[<?= $i ?>][machine_id]" value="<?= $row['machine_id'] ?>">
    <input type="hidden" name="items[<?= $i ?>][product_id]" value="<?= $row['product_id'] ?>">
</tr>
<?php endforeach ?>
<?php endif ?>
</tbody>
</table>
</div>

<button class="btn btn-success mt-3">
    <i class="bi bi-save"></i> Save Hourly Production
</button>

</form>

<?= $this->endSection() ?>
