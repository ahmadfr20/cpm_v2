<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>DIE CASTING – DAILY PRODUCTION SCHEDULE</h4>

<!-- DATE PICKER -->
<form method="get" class="mb-3">
    <input type="date"
           name="date"
           value="<?= esc($date) ?>"
           class="form-control w-25"
           onchange="this.form.submit()">
</form>

<form method="post" action="/die-casting/daily-schedule/store">
<?= csrf_field() ?>

<?php foreach ($shifts as $shift): ?>
<h5 class="mt-4"><?= esc($shift['shift_name']) ?></h5>

<table class="table table-bordered table-sm text-center align-middle dc-table">
<thead class="table-secondary">
<tr>
    <th class="col-machine">Mesin</th>
    <th class="col-part">Part</th>
    <th class="col-p">P</th>
    <th class="col-a">A</th>
    <th class="col-ng">NG</th>
    <th class="col-weight">Weight (kg)</th>
    <th class="col-status">Status</th>
</tr>
</thead>

<tbody>
<?php foreach ($machines as $m):
    $p = $existing[$shift['id']][$m['id']] ?? null;
    $key = $shift['id'].'_'.$m['id'];
?>
<tr>
<td><?= esc($m['machine_code']) ?></td>

<td>
<select class="form-select form-select-sm product"
        data-machine="<?= $m['id'] ?>"
        data-shift="<?= $shift['id'] ?>"
        data-selected="<?= $p['product_id'] ?? '' ?>"
        name="items[<?= $key ?>][product_id]">
    <option value="">-- pilih --</option>
</select>
</td>

<td>
<input type="number"
       max="1200"
       class="form-control form-control-sm qty-p text-end"
       name="items[<?= $key ?>][qty_p]"
       value="<?= $p['qty_p'] ?? 0 ?>">
</td>

<td><?= $p['qty_a'] ?? 0 ?></td>
<td><?= $p['qty_ng'] ?? 0 ?></td>

<td class="weight text-end">
    <?= isset($p['weight_kg']) ? number_format($p['weight_kg'],2) : '0.00' ?>
</td>

<td>
<select class="form-select form-select-sm status"
        name="items[<?= $key ?>][status]">
<?php foreach(['Normal','Recovery','Trial','OFF'] as $s): ?>
<option value="<?= $s ?>"
    <?= ($p['status'] ?? 'Normal') === $s ? 'selected' : '' ?>>
    <?= $s ?>
</option>
<?php endforeach ?>
</select>
</td>

<input type="hidden" name="items[<?= $key ?>][machine_id]" value="<?= $m['id'] ?>">
<input type="hidden" name="items[<?= $key ?>][shift_id]" value="<?= $shift['id'] ?>">
<input type="hidden" name="items[<?= $key ?>][date]" value="<?= $date ?>">
<input type="hidden" name="items[<?= $key ?>][qty_a]" value="<?= $p['qty_a'] ?? 0 ?>">
<input type="hidden" name="items[<?= $key ?>][qty_ng]" value="<?= $p['qty_ng'] ?? 0 ?>">
<input type="hidden" class="weight-input"
       name="items[<?= $key ?>][weight]"
       value="<?= isset($p['weight_kg'], $p['qty_p']) && $p['qty_p'] > 0
                ? ($p['weight_kg'] / $p['qty_p'])
                : 0 ?>">
</tr>
<?php endforeach ?>
</tbody>
</table>
<?php endforeach ?>

<div class="d-flex gap-2 mt-3">
    <button class="btn btn-success">
        <i class="bi bi-save"></i> Simpan Schedule
    </button>

    <a href="/die-casting/daily-schedule/view?date=<?= esc($date) ?>"
       class="btn btn-outline-primary">
        <i class="bi bi-eye"></i> View Result
    </a>
</div>

</form>

<!-- ================= CSS ================= -->
<style>
/* ===== TABLE LAYOUT ===== */
.dc-table {
    table-layout: fixed;
}

/* ===== COLUMN WIDTHS ===== */
.col-machine { width: 90px; }
.col-part    { width: 280px; }
.col-p       { width: 80px; }   /* 🔥 DIPERSEMPIT */
.col-a       { width: 70px; }
.col-ng      { width: 70px; }
.col-weight  { width: 120px; }
.col-status  { width: 120px; }

/* ===== INPUT P ===== */
.qty-p {
    max-width: 70px;
    margin: auto;
    padding-right: 6px;
}
</style>

<!-- ================= JS ================= -->
<script>
/* =========================
 * LOAD PRODUCT & TARGET
 * ========================= */
document.querySelectorAll('.product').forEach(sel=>{
    fetch(`/die-casting/daily-schedule/getProductAndTarget?machine_id=${sel.dataset.machine}&shift_id=${sel.dataset.shift}`)
    .then(r=>r.json())
    .then(res=>{
        const selected = sel.dataset.selected;
        sel.innerHTML = '<option value="">-- pilih --</option>';

        res.forEach(p=>{
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = `${p.part_no} - ${p.part_name}`;
            opt.dataset.target = p.target;
            opt.dataset.weight = p.weight;

            if (selected && selected == p.id) opt.selected = true;
            sel.appendChild(opt);
        });

        if (selected) sel.dispatchEvent(new Event('change'));
    });

    sel.addEventListener('change',e=>{
        const opt = e.target.selectedOptions[0];
        if (!opt) return;

        const tr  = e.target.closest('tr');
        const qty = tr.querySelector('.qty-p');
        const w   = tr.querySelector('.weight');
        const wi  = tr.querySelector('.weight-input');

        qty.value = opt.dataset.target || 0;
        w.innerText = (qty.value * opt.dataset.weight).toFixed(2);
        wi.value = opt.dataset.weight;
    });
});

/* =========================
 * VALIDASI QTY P ≤ 1200
 * ========================= */
document.querySelectorAll('.qty-p').forEach(inp=>{
    inp.addEventListener('input',()=>{
        let val = parseInt(inp.value || 0);
        if (val > 1200) {
            alert('Qty P tidak boleh lebih dari 1200');
            val = 1200;
            inp.value = 1200;
        }

        const tr = inp.closest('tr');
        const w  = tr.querySelector('.weight');
        const wi = tr.querySelector('.weight-input');

        w.innerText = (val * (wi.value || 0)).toFixed(2);
    });
});

/* =========================
 * AUTO SET OFF → P = 0
 * ========================= */
document.querySelectorAll('.status').forEach(sel=>{
    sel.addEventListener('change',()=>{
        const tr  = sel.closest('tr');
        const qty = tr.querySelector('.qty-p');
        const w   = tr.querySelector('.weight');

        if (sel.value === 'OFF') {
            qty.value = 0;
            w.innerText = '0.00';
        }
    });
});
</script>

<?= $this->endSection() ?>
