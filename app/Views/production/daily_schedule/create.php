<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>DAILY PRODUCTION SCHEDULE</h4>

<form method="post" action="/production/daily-schedule/store">

<div class="row mb-3">
    <div class="col-md-3">
        <label>Date</label>
        <input class="form-control" name="schedule_date" value="<?= $date ?>" readonly>
    </div>

    <div class="col-md-3">
        <label>Shift</label>
        <select name="shift_id" id="shiftSelect" class="form-control" required>
            <option value="">-- Select --</option>
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
<thead>
<tr>
    <th>Select</th>
    <th>Part Number</th>
    <th>Part Name</th>
    <th>Machine</th>
    <th>Target / Hour</th>
    <th>Target / Shift</th>
    <th>Cycle Time</th>
</tr>
</thead>
<tbody>

<?php foreach ($products as $i => $p): ?>
<tr>
    <td class="text-center">
        <input type="checkbox" class="select-item" data-index="<?= $i ?>">
        <input type="hidden" name="items[<?= $i ?>][is_selected]">
    </td>
    <td><?= $p['part_no'] ?></td>
    <td><?= $p['part_name'] ?></td>

    <td>
        <select name="items[<?= $i ?>][machine_id]" class="form-control machine-select"></select>
    </td>

    <td><span id="hour<?= $i ?>">-</span>
        <input type="hidden" name="items[<?= $i ?>][target_per_hour]">
    </td>

    <td><span id="shift<?= $i ?>">-</span>
        <input type="hidden" name="items[<?= $i ?>][target_per_shift]">
    </td>

    <td><b>&lt;System&gt;</b></td>

    <input type="hidden" name="items[<?= $i ?>][product_id]" value="<?= $p['id'] ?>">
</tr>
<?php endforeach ?>

</tbody>
</table>

<button class="btn btn-primary">Save Schedule</button>
</form>

<script>
const CYCLE_TIME = 40;
const CAVITY = 2;
const SHIFT_HOUR = 8;

function calculate() {
    return {
        hour: Math.floor(3600 / (CYCLE_TIME * CAVITY)),
        shift: Math.floor(3600 / (CYCLE_TIME * CAVITY)) * SHIFT_HOUR
    }
}

document.getElementById('sectionSelect').addEventListener('change', function(){
    fetch('/production/daily-schedule/machines?section='+this.value)
        .then(res=>res.json())
        .then(data=>{
            document.querySelectorAll('.machine-select').forEach(sel=>{
                sel.innerHTML='';
                data.forEach(m=>{
                    sel.innerHTML += `<option value="${m.id}">${m.machine_code}</option>`;
                });
            });
        });
});

document.querySelectorAll('.select-item').forEach(cb=>{
    cb.addEventListener('change',function(){
        const i = this.dataset.index;
        const t = calculate();

        if(this.checked){
            document.getElementById('hour'+i).innerText = t.hour;
            document.getElementById('shift'+i).innerText = t.shift;
            document.querySelector(`input[name="items[${i}][is_selected]"]`).value = 1;
            document.querySelector(`input[name="items[${i}][target_per_hour]"]`).value = t.hour;
            document.querySelector(`input[name="items[${i}][target_per_shift]"]`).value = t.shift;
        }
    });
});
</script>

<?= $this->endSection() ?>
