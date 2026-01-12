<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>DIE CASTING – DAILY PRODUCTION SCHEDULE</h4>

<form method="get" class="mb-3">
    <input type="date" name="date" value="<?= $date ?>" class="form-control w-25">
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
    <th>P</th>
    <th>A</th>
    <th>NG</th>
    <th>Weight (kg)</th>
    <th>Status</th>
</tr>
</thead>
<tbody>

<?php foreach ($machines as $m):
    $p = $map[$shift['id']][$m['id']] ?? null;
?>
<tr>
<td><?= esc($m['machine_code']) ?></td>

<td>
<select class="form-select form-select-sm product"
        data-machine="<?= $m['id'] ?>"
        data-shift="<?= $shift['id'] ?>"
        name="items[<?= $shift['id'].$m['id'] ?>][product_id]">
</select>
</td>

<td>
<input type="number" max="1200"
       class="form-control form-control-sm qty-p text-end"
       name="items[<?= $shift['id'].$m['id'] ?>][qty_p]"
       value="<?= $p['qty_p'] ?? 0 ?>">
</td>

<td><?= $p['qty_a'] ?? 0 ?></td>
<td><?= $p['qty_ng'] ?? 0 ?></td>
<td class="weight">0</td>

<td>
<select class="form-select form-select-sm status"
        name="items[<?= $shift['id'].$m['id'] ?>][status]">
<?php foreach(['Normal','Recovery','Trial','OFF'] as $s): ?>
<option <?= ($p['status']??'Normal')==$s?'selected':'' ?>><?= $s ?></option>
<?php endforeach ?>
</select>
</td>

<input type="hidden" name="items[<?= $shift['id'].$m['id'] ?>][machine_id]" value="<?= $m['id'] ?>">
<input type="hidden" name="items[<?= $shift['id'].$m['id'] ?>][shift_id]" value="<?= $shift['id'] ?>">
<input type="hidden" name="items[<?= $shift['id'].$m['id'] ?>][date]" value="<?= $date ?>">
<input type="hidden" name="items[<?= $shift['id'].$m['id'] ?>][qty_a]" value="<?= $p['qty_a'] ?? 0 ?>">
<input type="hidden" name="items[<?= $shift['id'].$m['id'] ?>][qty_ng]" value="<?= $p['qty_ng'] ?? 0 ?>">
<input type="hidden" class="weight-input" name="items[<?= $shift['id'].$m['id'] ?>][weight]" value="0">

</tr>
<?php endforeach ?>

</tbody>
</table>
<?php endforeach ?>

    <a href="/die-casting/daily-schedule/view?date=<?= $date ?>"
       class="btn btn-outline-primary">
        <i class="bi bi-eye"></i> View Result
    </a>

<button class="btn btn-success">Simpan Schedule</button>
</form>





<script>
document.querySelectorAll('.product').forEach(sel=>{
    fetch(`/die-casting/daily-schedule/product-target?machine_id=${sel.dataset.machine}&shift_id=${sel.dataset.shift}`)
    .then(r=>r.json()).then(res=>{
        sel.innerHTML='<option value="">-- pilih --</option>';
        res.forEach(p=>{
            sel.innerHTML+=`<option data-weight="${p.weight}" data-target="${p.target}" value="${p.id}">
                ${p.part_no} - ${p.part_name}
            </option>`;
        });
    });

    sel.addEventListener('change',e=>{
        const opt = e.target.selectedOptions[0];
        const tr  = e.target.closest('tr');
        const qty = tr.querySelector('.qty-p');
        const w   = tr.querySelector('.weight');
        const wi  = tr.querySelector('.weight-input');

        qty.value = opt.dataset.target || 0;
        w.innerText = (qty.value * opt.dataset.weight).toFixed(2);
        wi.value = opt.dataset.weight;
    });
});

document.querySelectorAll('.qty-p').forEach(inp=>{
    inp.addEventListener('input',()=>{
        if (inp.value > 1200) inp.value = 1200;
        const tr = inp.closest('tr');
        const w  = tr.querySelector('.weight');
        const wi = tr.querySelector('.weight-input');
        w.innerText = (inp.value * wi.value).toFixed(2);
    });
});
</script>

<?= $this->endSection() ?>
