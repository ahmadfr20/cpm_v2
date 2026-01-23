<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">DAILY PRODUCTION SCHEDULE – ASSY BUSHING</h4>

<form method="get" class="mb-3" style="max-width:220px">
    <label class="form-label fw-bold">Tanggal</label>
    <input type="date"
           name="date"
           value="<?= esc($date) ?>"
           class="form-control"
           onchange="this.form.submit()">
</form>

<?php foreach ($shifts as $shift): ?>

<hr>
<h5 class="mt-4 mb-3"><?= esc($shift['shift_name']) ?> – Assy Bushing</h5>

<form method="post" action="/machining/assy-bushing/schedule/store">
<?= csrf_field() ?>
<input type="hidden" name="date" value="<?= esc($date) ?>">

<table class="table table-bordered table-sm align-middle text-center">
<thead class="table-secondary">
<tr>
    <th style="width:60px">Line</th>
    <th style="width:120px">Kode Mesin</th>
    <th>Mesin</th>
    <th style="width:260px">Part</th>
    <th style="width:80px">CT</th>
    <th style="width:120px">Planning</th>
    <th style="width:80px">Actual</th>
</tr>
</thead>

<tbody>
<?php foreach ($machines as $idx => $machine):

    $keyPlan = $shift['id'].'_'.$machine['id'];
    $plan    = $planMap[$keyPlan] ?? null;

    $actKey  = $shift['id'].'_'.$machine['id'].'_'.($plan['product_id'] ?? 0);
    $actual  = $actualMap[$actKey]['act'] ?? 0;
?>
<tr>
<td><?= esc($machine['line_position']) ?></td>
<td class="fw-bold text-primary"><?= esc($machine['machine_code']) ?></td>
<td class="text-start"><?= esc($machine['machine_name']) ?></td>

<td>
<select class="form-select form-select-sm product-select"
        data-machine="<?= $machine['id'] ?>"
        data-shift="<?= $shift['id'] ?>"
        data-selected="<?= $plan['product_id'] ?? '' ?>"
        name="items[<?= $idx ?>][product_id]">
    <option value="">-- pilih part --</option>
</select>
</td>

<td>
<input type="text"
       class="form-control form-control-sm text-center cycle-time"
       value="<?= esc($plan['cycle_time'] ?? '') ?>"
       readonly>
</td>

<td>
<input type="number"
       class="form-control form-control-sm text-center plan-input"
       max="1200"
       value="<?= esc($plan['target_per_shift'] ?? '') ?>">
</td>

<td>
<input type="text"
       class="form-control form-control-sm text-center"
       value="<?= esc($actual) ?>"
       readonly>
</td>

<input type="hidden" name="items[<?= $idx ?>][machine_id]" value="<?= $machine['id'] ?>">
<input type="hidden" name="items[<?= $idx ?>][shift_id]" value="<?= $shift['id'] ?>">

</tr>
<?php endforeach ?>
</tbody>
</table>

<button class="btn btn-success btn-sm mb-4">
    <i class="bi bi-save"></i> Simpan <?= esc($shift['shift_name']) ?>
</button>
</form>

<?php endforeach ?>

<script>
async function loadProducts(selectEl) {
    const machineId  = selectEl.dataset.machine;
    const shiftId    = selectEl.dataset.shift;
    const selectedId = selectEl.dataset.selected;

    const res = await fetch(
        `/machining/assy-bushing/schedule/product-target?machine_id=${machineId}&shift_id=${shiftId}`
    );
    const data = await res.json();

    selectEl.innerHTML = '<option value="">-- pilih part --</option>';

    data.forEach(p => {
        const selected = (p.id == selectedId) ? 'selected' : '';
        selectEl.insertAdjacentHTML('beforeend', `
            <option value="${p.id}"
                    data-ct="${p.cycle_time}"
                    data-target="${p.target}"
                    ${selected}>
                ${p.part_no} - ${p.part_name}
            </option>
        `);
    });

    if (selectedId) selectEl.dispatchEvent(new Event('change'));
}

document.querySelectorAll('.product-select').forEach(selectEl => {
    loadProducts(selectEl);

    selectEl.addEventListener('change', () => {
        const opt = selectEl.selectedOptions[0];
        if (!opt) return;

        const row = selectEl.closest('tr');
        row.querySelector('.cycle-time').value = opt.dataset.ct || '';
        row.querySelector('.plan-input').value = opt.dataset.target || 0;
    });
});
</script>

<?= $this->endSection() ?>
