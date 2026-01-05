<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Daily Production Schedule</h4>

<form method="post" action="/production/daily-schedule/store">

<div class="row mb-3">
    <div class="col-md-3">
        <label>Tanggal Schedule</label>
        <input type="date" name="schedule_date"
               class="form-control"
               value="<?= date('Y-m-d') ?>" required>
    </div>

    <div class="col-md-3">
        <label>Shift</label>
        <select name="shift_id" id="shiftSelect" class="form-control" required>
            <option value="">-- Select Shift --</option>
            <?php foreach ($shifts as $s): ?>
                <option value="<?= $s['id'] ?>"><?= $s['shift_name'] ?></option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="col-md-3">
        <label>Section</label>
        <select name="section" id="sectionSelect" class="form-control" required>
            <option value="">-- Select Section --</option>
            <option value="Die Casting">Die Casting</option>
            <option value="Machining">Machining</option>
        </select>
    </div>
</div>

<table class="table table-bordered table-sm">
<thead class="table-light">
<tr>
    <th width="40">✓</th>
    <th>Line</th>
    <th>Machine</th>
    <th>Product</th>
    <th>Target / Hour</th>
    <th>Target / Shift</th>
</tr>
</thead>
<tbody id="scheduleBody"></tbody>
</table>

<button class="btn btn-primary">Save Schedule</button>
</form>

<script>
const tbody = document.getElementById('scheduleBody');
const sectionSelect = document.getElementById('sectionSelect');
const shiftSelect = document.getElementById('shiftSelect');

/* LOAD MACHINE */
sectionSelect.addEventListener('change', () => {
    tbody.innerHTML = '';
    if (!sectionSelect.value) return;

    fetch(`/production/get-machines?section=${sectionSelect.value}`)
        .then(r => r.json())
        .then(machines => {
            machines.forEach((m, i) => {
                tbody.innerHTML += `
                <tr data-index="${i}">
                    <td class="text-center">
                        <input type="checkbox" class="rowCheck">
                        <input type="hidden" name="items[${i}][is_selected]" value="0">
                    </td>
                    <td>Line ${m.line_position}</td>
                    <td>${m.machine_code}
                        <input type="hidden" name="items[${i}][machine_id]" value="${m.id}">
                    </td>
                    <td>
                        <select name="items[${i}][product_id]"
                                class="form-control productSelect" disabled>
                            <option value="">-- Select Product --</option>
                        </select>
                    </td>
                    <td>
                        <span id="hour${i}">-</span>
                        <input type="hidden" name="items[${i}][target_per_hour]">
                    </td>
                    <td>
                        <span id="shift${i}">-</span>
                        <input type="hidden" name="items[${i}][target_per_shift]">
                    </td>
                </tr>`;
                loadProducts(m.id, i);
            });
        });
});

function loadProducts(machineId, i) {
    fetch(`/production/get-products?machine_id=${machineId}`)
        .then(r => r.json())
        .then(products => {
            const select = tbody.querySelectorAll('.productSelect')[i];
            products.forEach(p => {
                select.innerHTML += `<option value="${p.id}">${p.part_no} - ${p.part_name}</option>`;
            });
        });
}

/* EVENT */
tbody.addEventListener('change', e => {
    const row = e.target.closest('tr');
    if (!row) return;
    const i = row.dataset.index;

    const checkbox = row.querySelector('.rowCheck');
    const product  = row.querySelector('.productSelect');
    const sel      = row.querySelector(`[name="items[${i}][is_selected]"]`);

    if (checkbox && e.target === checkbox) {
        product.disabled = !checkbox.checked;
        sel.value = checkbox.checked ? 1 : 0;
        return;
    }

    if (e.target.classList.contains('productSelect')) {
        if (!shiftSelect.value) {
            alert('Pilih shift terlebih dahulu');
            e.target.value = '';
            return;
        }

        fetch(`/production/calculate-target?machine_id=${row.querySelector('[name$="[machine_id]"]').value}&product_id=${e.target.value}&shift_id=${shiftSelect.value}`)
            .then(r => r.json())
            .then(res => {
                if (res.error) return alert(res.error);
                document.getElementById('hour'+i).innerText  = res.target_per_hour;
                document.getElementById('shift'+i).innerText = res.target_per_shift;
                row.querySelector('[name$="[target_per_hour]"]').value  = res.target_per_hour;
                row.querySelector('[name$="[target_per_shift]"]').value = res.target_per_shift;
            });
    }
});
</script>

<?= $this->endSection() ?>
