<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>DAILY PRODUCTION SCHEDULE – MACHINING</h4>

<form method="get" class="mb-3 w-25">
    <label>Tanggal</label>
    <input type="date"
           name="date"
           value="<?= esc($date) ?>"
           class="form-control"
           onchange="this.form.submit()">
</form>

<div class="d-flex gap-2 mb-3">
    <a href="/machining/daily-schedule/result?date=<?= esc($date) ?>"
       class="btn btn-outline-primary">
        <i class="bi bi-graph-up"></i>
        Lihat Hasil & Efektivitas
    </a>
</div>

<?php foreach ($shifts as $shift): ?>

<h5 class="mt-4"><?= esc($shift['shift_name']) ?></h5>

<form method="post" action="/machining/daily-schedule/store">
<?= csrf_field() ?>

<input type="hidden" name="date" value="<?= $date ?>">
<input type="hidden" name="shift_id" value="<?= $shift['id'] ?>">

<table class="table table-bordered table-sm text-center align-middle">
<thead class="table-secondary">
<tr>
    <th>LINE</th>
    <th>MESIN</th>
    <th>PART</th>
    <th>CT</th>
    <th>Planning</th>
    <th>A</th>
</tr>
</thead>

<tbody>
<?php foreach ($machines as $i => $m):

    $plan = $planMap[$shift['id'].'_'.$m['id']] ?? null;

    $act  = $actualMap[
        $shift['id'].'_'.$m['id'].'_'.($plan['product_id'] ?? 0)
    ]['act'] ?? 0;
?>
<tr>
<td><?= esc($m['line_position']) ?></td>
<td><?= esc($m['machine_name']) ?></td>

<td>
<select class="form-select product"
        data-machine="<?= $m['id'] ?>"
        data-shift="<?= $shift['id'] ?>"
        name="items[<?= $i ?>][product_id]">
    <option value="">-- pilih --</option>
</select>
</td>

<td>
<input class="form-control ct" readonly
       value="<?= $plan['cycle_time'] ?? '' ?>">
<input type="hidden" class="ct-hidden"
       name="items[<?= $i ?>][cycle_time]"
       value="<?= $plan['cycle_time'] ?? '' ?>">
</td>

<td>
<input type="number"
       class="form-control plan"
       name="items[<?= $i ?>][plan]"
       max="1200"
       value="<?= $plan['target_per_shift'] ?? '' ?>">
</td>

<td>
<input class="form-control text-center"
       value="<?= $act ?>" readonly>
</td>

<input type="hidden"
       name="items[<?= $i ?>][machine_id]"
       value="<?= $m['id'] ?>">

</tr>
<?php endforeach ?>
</tbody>
</table>

<button class="btn btn-success mb-4">
    <i class="bi bi-save"></i> Simpan <?= esc($shift['shift_name']) ?>
</button>

</form>
<?php endforeach ?>

<!-- ================= JS ================= -->
<script>
async function loadProducts(sel) {
    const m = sel.dataset.machine;
    const s = sel.dataset.shift;

    const res = await fetch(
        `/machining/daily-schedule/product-target?machine_id=${m}&shift_id=${s}`
    );
    const data = await res.json();

    sel.innerHTML = '<option value="">-- pilih --</option>';
    data.forEach(p => {
        sel.innerHTML += `
            <option value="${p.id}"
                    data-ct="${p.cycle_time_sec ?? ''}"
                    data-target="${p.target ?? 0}">
                ${p.part_no} - ${p.part_name}
            </option>`;
    });
}

document.querySelectorAll('.product').forEach(sel => {
    loadProducts(sel);

    sel.addEventListener('change', () => {
        const opt = sel.selectedOptions[0];
        if (!opt) return;

        const tr = sel.closest('tr');
        tr.querySelector('.ct').value = opt.dataset.ct || '';
        tr.querySelector('.ct-hidden').value = opt.dataset.ct || '';
        tr.querySelector('.plan').value = opt.dataset.target || 0;
    });
});
</script>

<?= $this->endSection() ?>
