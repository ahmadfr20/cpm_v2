<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>HOURLY PRODUCTION – DIE CASTING</h4>

<!-- ================= FILTER ================= -->
<form method="get" class="row g-2 mb-3 align-items-end">

    <div class="col-md-3">
        <label class="form-label">Tanggal</label>
        <input type="date"
               name="date"
               class="form-control"
               value="<?= esc($date) ?>">
    </div>

    <div class="col-md-3">
        <button class="btn btn-primary">
            <i class="bi bi-search"></i> Load Data
        </button>
    </div>

</form>

<!-- ================= INFO ================= -->
<div class="mb-3 p-3 bg-light border">
    <b>Date:</b> <?= date('d-m-Y', strtotime($date)) ?><br>
    <b>Shift:</b> <?= esc($shift['shift_name']) ?>
    (<?= $shift['time_start'] ?> - <?= $shift['time_end'] ?>)
</div>

<form method="post" action="/die-casting/hourly/store">

<input type="hidden" name="date" value="<?= esc($date) ?>">
<input type="hidden" name="shift_id" value="<?= esc($shift['shift_id']) ?>">
<input type="hidden" name="time_slot_id" value="<?= esc($shift['time_slot_id']) ?>">

<table class="table table-bordered table-sm text-center align-middle">
<thead class="table-secondary">
<tr>
    <th>Line</th>
    <th>Machine</th>
    <th>Part</th>
    <th>Cycle</th>
    <th>Target/Hr</th>
    <th>FG</th>
    <th>NG</th>
    <th>NG Category</th>
    <th>Downtime</th>
</tr>
</thead>

<tbody>
<?php foreach ($rows as $i => $r): ?>
<tr>
    <td>Line <?= esc($r['line_position']) ?></td>
    <td><?= esc($r['machine_code']) ?></td>

    <td>
        <select name="items[<?= $i ?>][product_id]"
                class="form-select form-select-sm productSelect">
            <?php foreach ($products[$r['machine_id']] as $p): ?>
                <option value="<?= $p['id'] ?>"
                    <?= $p['id']==$r['product_id']?'selected':'' ?>>
                    <?= esc($p['part_no']) ?>
                </option>
            <?php endforeach ?>
        </select>
    </td>

    <td><?= esc($r['cycle_time_sec']) ?></td>
    <td><?= esc($r['target_per_hour']) ?></td>

    <input type="hidden" name="items[<?= $i ?>][machine_id]" value="<?= $r['machine_id'] ?>">

    <td>
        <input type="number"
               name="items[<?= $i ?>][qty_fg]"
               class="form-control form-control-sm fg"
               value="<?= $r['qty_fg'] ?>">
    </td>

    <td>
        <input type="number"
               name="items[<?= $i ?>][qty_ng]"
               class="form-control form-control-sm ng"
               value="<?= $r['qty_ng'] ?>">
    </td>

    <td>
        <select name="items[<?= $i ?>][ng_category]"
                class="form-select form-select-sm">
            <option value="">-</option>
            <option <?= $r['ng_category']=='Flow Line'?'selected':'' ?>>Flow Line</option>
            <option <?= $r['ng_category']=='Crack'?'selected':'' ?>>Crack</option>
            <option <?= $r['ng_category']=='Porosity'?'selected':'' ?>>Porosity</option>
        </select>
    </td>

    <td>
        <input type="number"
               name="items[<?= $i ?>][downtime]"
               class="form-control form-control-sm downtime"
               value="<?= $r['downtime'] ?>">
    </td>
</tr>
<?php endforeach ?>
</tbody>
</table>

<button class="btn btn-success mt-3">
    <i class="bi bi-save"></i> Save Hourly
</button>

</form>

<script>
document.querySelectorAll('.productSelect').forEach(sel => {
    sel.addEventListener('change', function () {
        const row = this.closest('tr');
        row.querySelector('.fg').value = 0;
        row.querySelector('.ng').value = 0;
        row.querySelector('.downtime').value = 0;
    });
});
</script>

<?= $this->endSection() ?>
