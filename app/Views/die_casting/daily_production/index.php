<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">DIE CASTING – DAILY PRODUCTION PER HOUR</h4>

<div class="mb-3">
    <strong>Tanggal:</strong> <?= esc($date) ?><br>
    <strong>Operator:</strong> <?= esc($operator) ?>
</div>

<!-- FILTER TANGGAL -->
<form method="get" class="mb-3">
    <label class="fw-bold me-2">Tanggal Produksi:</label>
    <input type="date"
           name="date"
           value="<?= esc($date) ?>"
           class="form-control d-inline-block"
           style="width:180px"
           onchange="this.form.submit()">
</form>

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

<td class="sticky-left text-start fw-bold">
    <?= esc($item['part_no'].' - '.$item['part_name']) ?>
</td>

<td class="sticky-left-2 fw-bold text-center target-shift">
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

<td class="slot-target fw-bold bg-light text-center">
    <?= $targetSlot ?>
</td>

<td>
<input type="number"
       class="form-control form-control-sm slot-input fg"
       data-start="<?= $slot['time_start'] ?>"
       data-end="<?= $slot['time_end'] ?>"
       value="<?= $exist['qty_fg'] ?? 0 ?>"
       name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][fg]">
</td>

<td>
<input type="number"
       class="form-control form-control-sm slot-input ng"
       data-start="<?= $slot['time_start'] ?>"
       data-end="<?= $slot['time_end'] ?>"
       value="<?= $exist['qty_ng'] ?? 0 ?>"
       name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][ng]">
</td>

<td>
<input type="text"
       class="form-control form-control-sm slot-input"
       data-start="<?= $slot['time_start'] ?>"
       data-end="<?= $slot['time_end'] ?>"
       value="<?= $exist['ng_category'] ?? '' ?>"
       name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][ng_remark]">
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

<!-- TOTAL PER JAM -->
<tr class="total-slot-row fw-bold">
    <td colspan="2" class="text-end">TOTAL / JAM</td>
    <?php foreach ($shift['slots'] as $slot): ?>
        <td class="total-slot-target text-center">0</td>
        <td class="total-slot-fg text-center">0</td>
        <td class="total-slot-ng text-center">0</td>
        <td class="total-slot-eff text-center">0%</td>
    <?php endforeach ?>
</tr>

</tfoot>

</table>
</div>

<!-- ===== SUMMARY SHIFT (DI LUAR TABEL) ===== -->
<div class="shift-summary mt-2 mb-4 p-2 border rounded bg-light">
    <strong>SUMMARY <?= esc($shift['shift_name']) ?> :</strong>
    <span class="ms-3">FG: <span class="total-fg">0</span></span>
    <span class="ms-3">NG: <span class="total-ng">0</span></span>
    <span class="ms-3">Efficiency: <span class="eff">0%</span></span>
</div>

<?php endforeach ?>

<button class="btn btn-success mt-3">
<i class="bi bi-save"></i> Simpan
</button>

</form>

<!-- ================= CSS ================= -->
<style>
.table-scroll{overflow-x:auto}
.production-table{min-width:2600px}
.production-table th,td{font-size:13px;padding:4px;white-space:nowrap;text-align:center}
.col-part{width:260px}.col-target-shift{width:110px}
.sticky-left{position:sticky;left:0;background:#fff;z-index:5}
.sticky-left-2{position:sticky;left:260px;background:#fff;z-index:5}
.slot-active{background:#dcfce7!important}
.slot-header-active{background:#fde68a!important}
</style>

<!-- ================= JS ================= -->
<script>
function isSlotActive(start,end){
 const now=new Date()
 const today=now.toISOString().slice(0,10)
 let s=new Date(`${today}T${start}`)
 let e=new Date(`${today}T${end}`)
 if(e<=s){
  if(now>=s)e.setDate(e.getDate()+1)
  else s.setDate(s.getDate()-1)
 }
 return now>=s&&now<=e
}

function updateActiveSlots(){
 document.querySelectorAll('.slot-input').forEach(i=>{
  const a=isSlotActive(i.dataset.start,i.dataset.end)
  i.disabled=!a
  i.closest('td').classList.toggle('slot-active',a)
 })
 document.querySelectorAll('.slot-header').forEach(h=>{
  h.classList.toggle('slot-header-active',
   isSlotActive(h.dataset.start,h.dataset.end))
 })
}

function calcTotals(){
 document.querySelectorAll('.production-table').forEach(t=>{
  let fg=0,ng=0,target=0
  t.querySelectorAll('.fg').forEach(i=>fg+=+i.value||0)
  t.querySelectorAll('.ng').forEach(i=>ng+=+i.value||0)
  t.querySelectorAll('.target-shift').forEach(td=>target+=+td.innerText||0)

  const wrap=t.closest('.table-scroll').parentElement
  wrap.querySelector('.total-fg').innerText=fg
  wrap.querySelector('.total-ng').innerText=ng
  wrap.querySelector('.eff').innerText=
    target?((fg/target)*100).toFixed(1)+'%':'0%'
 })
}

function calcSlotTotals(){
 document.querySelectorAll('.production-table').forEach(t=>{
  const rows=t.querySelectorAll('tbody tr')
  const slots=t.querySelectorAll('.total-slot-target').length
  let tg=Array(slots).fill(0),
      fg=Array(slots).fill(0),
      ng=Array(slots).fill(0)

  rows.forEach(r=>{
   const c=r.querySelectorAll('td')
   for(let i=2;i<c.length;i+=4){
    const idx=(i-2)/4
    tg[idx]+=+c[i].innerText||0
    fg[idx]+=+(c[i+1].querySelector('.fg')?.value||0)
    ng[idx]+=+(c[i+2].querySelector('.ng')?.value||0)
   }
  })

  t.querySelectorAll('.total-slot-target').forEach((e,i)=>e.innerText=tg[i])
  t.querySelectorAll('.total-slot-fg').forEach((e,i)=>e.innerText=fg[i])
  t.querySelectorAll('.total-slot-ng').forEach((e,i)=>e.innerText=ng[i])
  t.querySelectorAll('.total-slot-eff').forEach((e,i)=>{
   e.innerText=tg[i]?((fg[i]/tg[i])*100).toFixed(1)+'%':'0%'
  })
 })
}

function recalcAll(){calcTotals();calcSlotTotals()}
updateActiveSlots();recalcAll()
setInterval(updateActiveSlots,30000)
document.addEventListener('input',recalcAll)
</script>

<?= $this->endSection() ?>
