<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Daily Production Schedule</h4>



<form method="post" action="/production/daily-schedule/store">

<b>Date:</b> <?= date('Y-m-d') ?>

<div class="row mt-2 mb-3">
    <div class="col-md-3">
        <label>Shift</label>
        <select name="shift_id" class="form-control" required>
            <?php foreach ($shifts as $s): ?>
                <option value="<?= $s['id'] ?>"><?= $s['shift_name'] ?></option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="col-md-3">
        <label>Section</label>
        <select name="section" id="sectionSelect" class="form-control" required>
            <option value="">-- Select --</option>
            <option value="Die Casting">Die Casting</option>
            <option value="Machining">Machining</option>
        </select>
    </div>
</div>

<table class="table table-bordered table-sm">
<thead class="table-light">
<tr>
    <th>✓</th>
    <th>Part</th>
    <th>Machine</th>
    <th>Target / Hour</th>
    <th>Target / Shift</th>
    <th>Cycle</th>
</tr>
</thead>
<tbody>

<?php foreach ($products as $i => $p): ?>
<tr>
    <td>
        <input type="checkbox" name="items[<?= $i ?>][is_selected]" class="selectItem" data-index="<?= $i ?>">
    </td>
    <td><?= $p['part_no'] ?> - <?= $p['part_name'] ?></td>

    <td>
        <select name="items[<?= $i ?>][machine_id]" class="form-control machineSelect"></select>
    </td>

    <td>
        <span id="hour<?= $i ?>">-</span>
        <input type="hidden" name="items[<?= $i ?>][target_per_hour]">
    </td>

    <td>
        <span id="shift<?= $i ?>">-</span>
        <input type="hidden" name="items[<?= $i ?>][target_per_shift]">
    </td>

    <td>40</td>

    <input type="hidden" name="items[<?= $i ?>][product_id]" value="<?= $p['id'] ?>">
</tr>
<?php endforeach ?>

</tbody>
</table>

<button class="btn btn-primary">Save Schedule</button>
</form>

<script>
const SHIFT_HOUR = 8;
const CYCLE = 40;
const CAVITY = 2;

document.getElementById('sectionSelect').addEventListener('change', function () {
    fetch('/production/get-machines?section=' + this.value)
        .then(r => r.json())
        .then(data => {
            document.querySelectorAll('.machineSelect').forEach(sel => {
                sel.innerHTML = '';
                data.forEach(m => {
                    sel.innerHTML += `<option value="${m.id}">${m.machine_code}</option>`;
                });
            });
        });
});

document.querySelectorAll('.selectItem').forEach(cb => {
    cb.addEventListener('change', function () {
        const i = this.dataset.index;
        if (!this.checked) return;

        const perHour = Math.floor(3600 / (CYCLE * CAVITY));
        const perShift = perHour * SHIFT_HOUR;

        document.getElementById('hour'+i).innerText = perHour;
        document.getElementById('shift'+i).innerText = perShift;

        document.querySelector(`input[name="items[${i}][target_per_hour]"]`).value = perHour;
        document.querySelector(`input[name="items[${i}][target_per_shift]"]`).value = perShift;
    });
});
</script>

<?= $this->endSection() ?>
