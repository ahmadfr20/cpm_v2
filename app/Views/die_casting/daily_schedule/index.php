<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>DIE CASTING – DAILY PRODUCTION SCHEDULE</h4>

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

<table class="table table-bordered table-sm text-center align-middle">
<thead class="table-secondary">
<tr>
    <th>Mesin</th>
    <th>Part</th>
    <th>Plan</th>
    <th>Ascas (kg)</th>
    <th>A</th>
    <th>Runner (kg)</th>
    <th>NG</th>
    <th>Status</th>
</tr>
</thead>

<tbody>
<?php foreach ($machines as $m):
    $p   = $map[$shift['id']][$m['id']] ?? null;
    $key = $shift['id'].'_'.$m['id'];
?>
<tr data-has-product="<?= $p ? 1 : 0 ?>">
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
       class="form-control form-control-sm qty-p text-end"
       name="items[<?= $key ?>][qty_p]"
       value="<?= $p['qty_p'] ?? 0 ?>"
       min="0"
       max="1200">
</td>

<td class="ascas text-end">0.00</td>

<td>
<input type="number"
       class="form-control form-control-sm text-end"
       value="<?= $p['qty_a'] ?? 0 ?>"
       readonly>
</td>

<td class="runner text-end">0.00</td>

<td>
<input type="number"
       class="form-control form-control-sm text-end"
       value="<?= $p['qty_ng'] ?? 0 ?>"
       readonly>
</td>

<td>
<select class="form-select form-select-sm status"
        name="items[<?= $key ?>][status]">
<?php foreach (['Normal','Recovery','Trial','OFF'] as $s): ?>
<option value="<?= $s ?>"
    <?= ($p['status'] ?? 'Normal') === $s ? 'selected' : '' ?>>
    <?= $s ?>
</option>
<?php endforeach ?>
</select>
</td>

<!-- HIDDEN -->
<input type="hidden" name="items[<?= $key ?>][machine_id]" value="<?= $m['id'] ?>">
<input type="hidden" name="items[<?= $key ?>][shift_id]" value="<?= $shift['id'] ?>">
<input type="hidden" name="items[<?= $key ?>][date]" value="<?= $date ?>">
<input type="hidden" class="wa" name="items[<?= $key ?>][weight_ascas]" value="0">
<input type="hidden" class="wr" name="items[<?= $key ?>][weight_runner]" value="0">
<input type="hidden" name="items[<?= $key ?>][qty_a]" value="<?= $p['qty_a'] ?? 0 ?>">
<input type="hidden" name="items[<?= $key ?>][qty_ng]" value="<?= $p['qty_ng'] ?? 0 ?>">

</tr>
<?php endforeach ?>
</tbody>
</table>
<?php endforeach ?>

<input type="hidden" name="mode" id="formMode" value="save">

<div class="d-flex gap-2 mt-4">
    <button type="submit" class="btn btn-success"
            onclick="document.getElementById('formMode').value='save'">
        💾 Simpan Baru
    </button>

    <button type="submit" class="btn btn-warning"
            onclick="document.getElementById('formMode').value='update'">
        🔄 Update Jadwal
    </button>

    <a href="/die-casting/daily-schedule/view?date=<?= esc($date) ?>"
       class="btn btn-outline-primary">
        👁 View Result
    </a>
</div>

</form>

<!-- ================= JS ================= -->
<script>
const productUrl = "<?= site_url('die-casting/daily-schedule/getProductAndTarget') ?>";

document.querySelectorAll('.product').forEach(sel => {
    const selected = sel.dataset.selected;
    const tr = sel.closest('tr');

    fetch(`${productUrl}?machine_id=${sel.dataset.machine}&shift_id=${sel.dataset.shift}`)
        .then(r => r.json())
        .then(res => {

            sel.innerHTML = '<option value="">-- pilih --</option>';

            res.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = `${p.part_no} - ${p.part_name}`;
                opt.dataset.ascas  = p.weight_ascas || 0;
                opt.dataset.runner = p.weight_runner || 0;
                opt.dataset.target = p.target || 0;

                if (selected == p.id) {
                    opt.selected = true;

                    // 🔥 SET WA & WR SAAT LOAD
                    tr.querySelector('.wa').value = opt.dataset.ascas;
                    tr.querySelector('.wr').value = opt.dataset.runner;
                }

                sel.appendChild(opt);
            });

            // 🔥 AUTO HITUNG SETELAH REFRESH
            if (selected) {
                calculate(tr);
            }
        });
});

/* =========================
 * EVENT PILIH PRODUCT
 * ========================= */
document.addEventListener('change', e => {
    if (!e.target.classList.contains('product')) return;

    const sel = e.target;
    const tr  = sel.closest('tr');
    const opt = sel.selectedOptions[0];
    if (!opt || !opt.value) return;

    tr.querySelector('.wa').value = opt.dataset.ascas || 0;
    tr.querySelector('.wr').value = opt.dataset.runner || 0;

    const qtyP = tr.querySelector('.qty-p');
    if (!qtyP.value || qtyP.value == 0) {
        qtyP.value = opt.dataset.target || 0;
    }

    calculate(tr);
});

/* =========================
 * EVENT UBAH QTY P
 * ========================= */
document.addEventListener('input', e => {
    if (!e.target.classList.contains('qty-p')) return;

    let val = parseInt(e.target.value || 0);
    if (val > 1200) {
        alert('Qty P tidak boleh lebih dari 1200');
        e.target.value = 1200;
    }

    calculate(e.target.closest('tr'));
});

/* =========================
 * KALKULASI
 * ========================= */
function calculate(tr) {
    const qtyP = +tr.querySelector('.qty-p').value || 0;
    const qtyA = +tr.querySelector('[name$="[qty_a]"]').value || 0;
    const wa   = +tr.querySelector('.wa').value || 0;
    const wr   = +tr.querySelector('.wr').value || 0;

    const ascas  = (qtyP * wa) / 1000;
    const runner = (qtyA * wr) / 1000;

    tr.querySelector('.ascas').innerText  = ascas.toFixed(2);
    tr.querySelector('.runner').innerText = runner.toFixed(2);
}
</script>



<?= $this->endSection() ?>
