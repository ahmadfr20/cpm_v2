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

<table class="table table-bordered table-sm text-center align-middle dc-table">
<thead class="table-secondary">
<tr>
    <th>Mesin</th>
    <th>Part</th>
    <th>Plan</th>
    <th>As-Cast (kg)</th>
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
<tr>
<td><?= esc($m['machine_code']) ?></td>

<td>
<select class="form-select form-select-sm product"
        data-machine="<?= $m['id'] ?>"
        data-shift="<?= $shift['id'] ?>"
        data-selected="<?= $p['product_id'] ?? '' ?>" <!-- 🔥 -->
        name="items[<?= $key ?>][product_id]">
    <option value="">-- pilih --</option>
</select>
</td>

<!-- PLAN -->
<td>
<input type="number"
       min="0"
       max="1200"
       class="form-control form-control-sm qty-p text-end"
       name="items[<?= $key ?>][qty_p]"
       value="<?= $p['qty_p'] ?? 0 ?>">
</td>

<!-- ASCAS -->
<td class="ascas text-end">0.00</td>

<!-- A (READ ONLY) -->
<td>
<input type="number"
       class="form-control form-control-sm text-end qty-a"
       value="<?= $p['qty_a'] ?? 0 ?>"
       readonly>
</td>

<!-- RUNNER -->
<td class="runner text-end">0.00</td>

<!-- NG (READ ONLY) -->
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

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-success">
        <i class="bi bi-save"></i> Simpan Schedule
    </button>

    <a href="/die-casting/daily-schedule/view?date=<?= esc($date) ?>"
       class="btn btn-outline-primary">
        <i class="bi bi-eye"></i> View Result
    </a>
</div>
</form>

<!-- ================= JS ================= -->
<script>
document.querySelectorAll('.product').forEach(sel => {

    const selected = sel.dataset.selected; // 🔥 ambil existing product

    fetch(`/die-casting/daily-schedule/getProductAndTarget?machine_id=${sel.dataset.machine}&shift_id=${sel.dataset.shift}`)
        .then(r => r.json())
        .then(res => {

            res.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = `${p.part_no} - ${p.part_name}`;
                opt.dataset.ascas  = p.weight_ascas || 0;
                opt.dataset.runner = p.weight_runner || 0;
                opt.dataset.target = p.target || 0;

                if (selected && selected == p.id) {
                    opt.selected = true; // 🔥 auto select
                }

                sel.appendChild(opt);
            });

            // 🔥 trigger ulang kalkulasi setelah load
            if (selected) {
                sel.dispatchEvent(new Event('change'));
            }
        });

    sel.addEventListener('change', () => {
        const opt = sel.selectedOptions[0];
        if (!opt) return;

        const tr = sel.closest('tr');
        tr.querySelector('.wa').value = opt.dataset.ascas;
        tr.querySelector('.wr').value = opt.dataset.runner;

        // auto isi plan hanya jika kosong
        const qtyP = tr.querySelector('.qty-p');
        if (!qtyP.value || qtyP.value == 0) {
            qtyP.value = opt.dataset.target || 0;
        }

        calculate(tr);
    });
});

/* =========================
 * VALIDASI P ≤ 1200
 * ========================= */
document.querySelectorAll('.qty-p').forEach(inp => {
    inp.addEventListener('input', () => {
        let val = parseInt(inp.value || 0);
        if (val > 1200) {
            alert('Qty P tidak boleh lebih dari 1200');
            inp.value = 1200;
        }
        calculate(inp.closest('tr'));
    });
});

function calculate(tr) {
    const qtyP = +tr.querySelector('.qty-p').value;
    const qtyA = +tr.querySelector('.qty-a').value;
    const wa   = +tr.querySelector('.wa').value;
    const wr   = +tr.querySelector('.wr').value;

    tr.querySelector('.ascas').innerText  = ((qtyP * wa) / 1000).toFixed(2);
    tr.querySelector('.runner').innerText = ((qtyA * wr) / 1000).toFixed(2);
}
</script>

<?= $this->endSection() ?>
