<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">Daily Production Schedule</h4>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success"><?= session()->getFlashdata('success') ?></div>
<?php endif; ?>

<form method="post" action="/production/daily-schedule/store">

<div class="row mb-3">
    <div class="col-md-3">
        <label>Date</label>
        <input type="date" name="schedule_date" class="form-control"
               value="<?= date('Y-m-d') ?>" readonly>
    </div>

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
        <select name="section" class="form-control" required>
            <option value="">-- Select --</option>
            <option value="Die Casting">Die Casting</option>
            <option value="Machining">Machining</option>
        </select>
    </div>
</div>

<table class="table table-bordered table-sm align-middle">
<thead class="table-light">
<tr>
    <th>Select</th>
    <th>Part</th>
    <th>Machine</th>
    <th width="120">Target / Shift</th>
    <th width="120">Target / Hour</th>
    <th width="150">Cycle Time (sec)</th>
</tr>
</thead>
<tbody>

<?php foreach ($products as $i => $p): ?>
<tr>
    <td class="text-center">
        <input type="checkbox" class="form-check-input select-item"
               data-index="<?= $i ?>">
        <input type="hidden" name="items[<?= $i ?>][selected]" disabled>
    </td>

    <td><?= esc($p['part_no'].' - '.$p['part_name']) ?></td>

    <td>
        <select name="items[<?= $i ?>][machine_id]" class="form-control form-control-sm">
            <?php foreach ($machines as $m): ?>
            <option value="<?= $m['id'] ?>"><?= $m['machine_code'] ?></option>
            <?php endforeach ?>
        </select>
    </td>

    <td class="text-center">
        <span id="shiftTarget<?= $i ?>">-</span>
        <input type="hidden" name="items[<?= $i ?>][target_per_shift]">
    </td>

    <td class="text-center">
        <span id="hourTarget<?= $i ?>">-</span>
        <input type="hidden" name="items[<?= $i ?>][target_per_hour]">
    </td>

    <td>
        <input type="number"
               class="form-control form-control-sm cycle-input"
               placeholder="Isi cycle time"
               data-index="<?= $i ?>"
               disabled>
        <input type="hidden" name="items[<?= $i ?>][cycle_time]">
    </td>

    <input type="hidden" name="items[<?= $i ?>][product_id]" value="<?= $p['id'] ?>">
</tr>
<?php endforeach ?>

</tbody>
</table>

<button class="btn btn-primary">Save Schedule</button>
</form>

<?= $this->endSection() ?>

<script>
const SHIFT_HOUR = 8;   // Jam kerja per shift
const CAVITY = 2;      // Cavity default (bisa dikembangkan per part)

document.querySelectorAll('.select-item').forEach(cb => {
    cb.addEventListener('change', function () {

        const i = this.dataset.index;
        const row = this.closest('tr');

        const cycleInput  = row.querySelector('.cycle-input');
        const cycleHidden = row.querySelector(`input[name="items[${i}][cycle_time]"]`);
        const hourHidden  = row.querySelector(`input[name="items[${i}][target_per_hour]"]`);
        const shiftHidden = row.querySelector(`input[name="items[${i}][target_per_shift]"]`);
        const selectHidden= row.querySelector(`input[name="items[${i}][selected]"]`);

        // Enable / Disable input
        cycleInput.disabled = !this.checked;
        selectHidden.disabled = !this.checked;

        if (!this.checked) {
            cycleInput.value = '';
            cycleHidden.value = '';
            hourHidden.value = '';
            shiftHidden.value = '';
            document.getElementById(`hourTarget${i}`).innerText = '-';
            document.getElementById(`shiftTarget${i}`).innerText = '-';
            return;
        }

        cycleInput.addEventListener('input', function () {
            const cycle = parseInt(this.value);

            // VALIDASI
            if (isNaN(cycle) || cycle <= 0) {
                document.getElementById(`hourTarget${i}`).innerText = '-';
                document.getElementById(`shiftTarget${i}`).innerText = '-';
                cycleHidden.value = '';
                hourHidden.value = '';
                shiftHidden.value = '';
                return;
            }

            // HITUNG TARGET
            const targetHour  = Math.floor(3600 / (cycle * CAVITY));
            const targetShift = targetHour * SHIFT_HOUR;

            // TAMPILKAN
            document.getElementById(`hourTarget${i}`).innerText = targetHour;
            document.getElementById(`shiftTarget${i}`).innerText = targetShift;

            // SIMPAN KE HIDDEN INPUT
            cycleHidden.value = cycle;
            hourHidden.value  = targetHour;
            shiftHidden.value = targetShift;
        });
    });
});
</script>
