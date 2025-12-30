<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">Schedule Die Casting</h4>

<form method="post" action="/die-casting/schedule/store">

<div class="row mb-3">
    <div class="col-md-3">
        <label class="form-label">Tanggal</label>
        <input type="date" name="schedule_date" class="form-control"
               value="<?= date('Y-m-d') ?>">
    </div>

    <div class="col-md-3">
        <label class="form-label">Shift</label>
        <select name="shift_id" class="form-control">
            <?php foreach ($shifts as $s): ?>
                <option value="<?= $s['id'] ?>"><?= $s['shift_name'] ?></option>
            <?php endforeach ?>
        </select>
    </div>
</div>

<table class="table table-bordered table-sm">
<thead class="table-light">
<tr>
    <th>Select</th>
    <th>Part</th>
    <th>Machine</th>
    <th>Cycle Time</th>
    <th>Target / Hour</th>
    <th>Target / Shift</th>
</tr>
</thead>
<tbody>

<?php foreach ($products as $i => $p): ?>
<tr>
    <td>
        <input type="checkbox" name="items[<?= $i ?>][selected]">
    </td>
    <td><?= esc($p['part_name']) ?></td>
    <td>
        <select name="items[<?= $i ?>][machine_id]" class="form-control form-control-sm">
            <?php foreach ($machines as $m): ?>
                <option value="<?= $m['id'] ?>"><?= $m['machine_code'] ?></option>
            <?php endforeach ?>
        </select>
    </td>
    <td><input type="number" name="items[<?= $i ?>][cycle_time]" class="form-control form-control-sm"></td>
    <td><input type="number" name="items[<?= $i ?>][target_hour]" class="form-control form-control-sm"></td>
    <td><input type="number" name="items[<?= $i ?>][target_shift]" class="form-control form-control-sm"></td>
    <input type="hidden" name="items[<?= $i ?>][product_id]" value="<?= $p['id'] ?>">
</tr>
<?php endforeach ?>

</tbody>
</table>

<button class="btn btn-primary">Save Schedule</button>
</form>

<?= $this->endSection() ?>
