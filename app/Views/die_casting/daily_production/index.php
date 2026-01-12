<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">DIE CASTING – DAILY PRODUCTION</h4>

<div class="mb-3">
    <strong>Tanggal:</strong> <?= esc($date) ?><br>
    <strong>Operator:</strong> <?= esc($operator) ?>
</div>

<form method="post" action="/die-casting/daily-production/store">
<?= csrf_field() ?>

<?php foreach ($shifts as $shift): ?>

<h5 class="mt-4 mb-2"><?= esc($shift['shift_name']) ?></h5>

<div class="table-scroll">
<table class="production-table table table-bordered table-sm">

<thead>
<tr>
    <th rowspan="2" class="sticky-left col-part">Part</th>
    <th rowspan="2" class="sticky-left-2 col-target-shift">Target<br>Shift</th>

    <?php foreach ($shift['slots'] as $slot): ?>
        <th colspan="4"
            class="slot-header"
            data-start="<?= $slot['time_start'] ?>"
            data-end="<?= $slot['time_end'] ?>">
            <?= substr($slot['time_start'],0,5) ?> - <?= substr($slot['time_end'],0,5) ?>
        </th>
    <?php endforeach ?>
</tr>

<tr>
<?php foreach ($shift['slots'] as $slot): ?>
    <th class="col-slot-target">Target</th>
    <th class="col-slot-fg">FG</th>
    <th class="col-slot-ng">NG</th>
    <th class="col-slot-remark">Ket.</th>
<?php endforeach ?>
</tr>
</thead>

<tbody class="shift-body">
<?php foreach ($shift['items'] as $item): ?>
<tr>

<td class="sticky-left col-part text-start">
    <?= esc($item['part_no'].' - '.$item['part_name']) ?>
</td>

<td class="sticky-left-2 col-target-shift fw-bold text-center">
    <?= esc($item['qty_p']) ?>
</td>

<?php foreach ($shift['slots'] as $slot):

$targetSlot = round(
    ($item['qty_p'] / $shift['total_minute']) * $slot['minute']
);

$exist = $shift['hourly_map']
    [$item['machine_id']]
    [$item['product_id']]
    [$slot['id']] ?? null;
?>

<td class="col-slot-target fw-bold bg-light text-center">
    <?= $targetSlot ?>
</td>

<td class="col-slot-fg">
<input type="number"
       class="form-control form-control-sm slot-input fg"
       data-start="<?= $slot['time_start'] ?>"
       data-end="<?= $slot['time_end'] ?>"
       name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][fg]"
       value="<?= $exist['qty_fg'] ?? 0 ?>">
</td>

<td class="col-slot-ng">
<input type="number"
       class="form-control form-control-sm slot-input ng"
       data-start="<?= $slot['time_start'] ?>"
       data-end="<?= $slot['time_end'] ?>"
       name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][ng]"
       value="<?= $exist['qty_ng'] ?? 0 ?>">
</td>

<td class="col-slot-remark">
<input type="text"
       class="form-control form-control-sm slot-input"
       data-start="<?= $slot['time_start'] ?>"
       data-end="<?= $slot['time_end'] ?>"
       name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][ng_remark]"
       value="<?= $exist['ng_category'] ?? '' ?>">
</td>

<input type="hidden" name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][shift_id]" value="<?= $shift['id'] ?>">
<input type="hidden" name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][machine_id]" value="<?= $item['machine_id'] ?>">
<input type="hidden" name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][product_id]" value="<?= $item['product_id'] ?>">
<input type="hidden" name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][time_slot_id]" value="<?= $slot['id'] ?>">
<input type="hidden" name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][date]" value="<?= $date ?>">

<?php endforeach ?>

</tr>
<?php endforeach ?>
</tbody>

<tfoot>
<tr>
<td colspan="2" class="fw-bold text-end">TOTAL SHIFT</td>
<td colspan="<?= count($shift['slots']) * 4 ?>" class="text-end">
FG: <span class="total-fg">0</span> |
NG: <span class="total-ng">0</span> |
Eff: <span class="eff">0%</span>
</td>
</tr>
</tfoot>

</table>
</div>

<?php endforeach ?>

<button class="btn btn-success mt-3">
<i class="bi bi-save"></i> Simpan
</button>

</form>

<!-- ================= CSS ================= -->
<style>
.table-scroll {
    width: 100%;
    overflow-x: auto;
    margin-bottom: 20px;
}

.production-table {
    min-width: 2600px;
    table-layout: fixed;
    border-collapse: separate;
    border-spacing: 0;
    background: #fff;
}

.production-table th,
.production-table td {
    font-size: 13px;
    padding: 4px;
    vertical-align: middle;
    text-align: center;
    white-space: nowrap;
}

.col-part { width: 260px; min-width: 260px; }
.col-target-shift { width: 110px; min-width: 110px; }

.col-slot-target { width: 70px; }
.col-slot-fg     { width: 70px; }
.col-slot-ng     { width: 70px; }
.col-slot-remark { width: 120px; }

.sticky-left {
    position: sticky;
    left: 0;
    z-index: 5;
    background: #fff;
}
.sticky-left-2 {
    position: sticky;
    left: 260px;
    z-index: 5;
    background: #fff;
}

.slot-active {
    background-color: #dcfce7 !important;
}
.slot-header-active {
    background-color: #fde68a !important;
}
</style>

<!-- ================= JS ================= -->
<script>
function isSlotActive(start, end) {
    const now = new Date();
    const today = now.toISOString().slice(0,10);
    let s = new Date(`${today}T${start}`);
    let e = new Date(`${today}T${end}`);

    if (e <= s) {
        if (now >= s) e.setDate(e.getDate() + 1);
        else s.setDate(s.getDate() - 1);
    }
    return now >= s && now <= e;
}

function updateActiveSlots() {
    document.querySelectorAll('.slot-input').forEach(inp => {
        const active = isSlotActive(inp.dataset.start, inp.dataset.end);
        inp.disabled = !active;
        inp.closest('td').classList.toggle('slot-active', active);
    });

    document.querySelectorAll('.slot-header').forEach(th => {
        th.classList.toggle(
            'slot-header-active',
            isSlotActive(th.dataset.start, th.dataset.end)
        );
    });
}

function calcTotals(){
    document.querySelectorAll('.shift-body').forEach(tb=>{
        let fg=0, ng=0, target=0;
        tb.querySelectorAll('.fg').forEach(i=>fg+=+i.value||0);
        tb.querySelectorAll('.ng').forEach(i=>ng+=+i.value||0);
        tb.querySelectorAll('.sticky-left-2').forEach(t=>target+=+t.innerText||0);

        const table = tb.closest('table');
        table.querySelector('.total-fg').innerText = fg;
        table.querySelector('.total-ng').innerText = ng;
        table.querySelector('.eff').innerText =
            target ? ((fg/target)*100).toFixed(1)+'%' : '0%';
    });
}

updateActiveSlots();
calcTotals();
setInterval(updateActiveSlots, 30000);
document.addEventListener('input', calcTotals);
</script>

<?= $this->endSection() ?>
