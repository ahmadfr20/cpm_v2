<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">MACHINING – ASSY BUSHING DAILY PRODUCTION PER HOUR</h4>

<div class="mb-3">
    <strong>Tanggal:</strong> <?= esc($date) ?><br>
    <strong>Operator:</strong> <?= esc($operator) ?>
</div>

<form method="get" class="mb-3">
    <label class="fw-bold me-2">Tanggal Produksi:</label>
    <input type="date"
           name="date"
           value="<?= esc($date) ?>"
           class="form-control d-inline-block"
           style="width:180px"
           onchange="this.form.submit()">
</form>

<?php foreach ($shifts as $shift): ?>

<form method="post" action="/machining/assy-bushing/hourly/store">
<?= csrf_field() ?>

<h5 class="mt-4 mb-2"><?= esc($shift['shift_name']) ?></h5>

<div class="table-scroll">
<table class="production-table table table-bordered table-sm">

<thead>
<tr>
    <th rowspan="2" class="sticky-left col-line">Line</th>
    <th rowspan="2" class="sticky-left-2 col-machine">Machine</th>
    <th rowspan="2" class="sticky-left-3 col-part">Part</th>
    <th rowspan="2" class="sticky-left-4 col-target-shift">
        Target<br>Shift
    </th>

    <?php foreach ($shift['slots'] as $slot): ?>
        <th colspan="3"
            class="slot-header"
            data-start="<?= $slot['time_start'] ?>"
            data-end="<?= $slot['time_end'] ?>">
            <?= substr($slot['time_start'],0,5) ?> -
            <?= substr($slot['time_end'],0,5) ?>
        </th>
    <?php endforeach ?>
</tr>

<tr>
<?php foreach ($shift['slots'] as $slot): ?>
    <th>Target</th>
    <th>OK</th>
    <th>NG</th>
<?php endforeach ?>
</tr>
</thead>

<tbody class="shift-body">
<?php foreach ($shift['items'] as $item): ?>
<tr>

<td class="sticky-left fw-bold text-center">
    <?= esc($item['line_position']) ?>
</td>

<td class="sticky-left-2 text-center">
    <?= esc($item['machine_code']) ?>
</td>

<td class="sticky-left-3 text-start">
    <?= esc($item['part_no'].' - '.$item['part_name']) ?>
</td>

<td class="sticky-left-4 fw-bold text-center target-shift">
    <?= esc($item['target_per_shift']) ?>
</td>

<?php foreach ($shift['slots'] as $slot):

$targetSlot = round(
    ($item['target_per_shift'] / $shift['total_minute']) * $slot['minute']
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
       name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][ok]">
</td>

<td>
<input type="number"
       class="form-control form-control-sm slot-input ng"
       data-start="<?= $slot['time_start'] ?>"
       data-end="<?= $slot['time_end'] ?>"
       value="<?= $exist['qty_ng'] ?? 0 ?>"
       name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][ng]">
</td>

<input type="hidden"
       name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][date]"
       value="<?= esc($date) ?>">

<input type="hidden"
       name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][shift_id]"
       value="<?= $shift['id'] ?>">

<input type="hidden"
       name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][machine_id]"
       value="<?= $item['machine_id'] ?>">

<input type="hidden"
       name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][product_id]"
       value="<?= $item['product_id'] ?>">

<input type="hidden"
       name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][time_slot_id]"
       value="<?= $slot['id'] ?>">

<?php endforeach ?>
</tr>
<?php endforeach ?>
</tbody>

<tfoot>
<tr class="total-slot-row fw-bold">
    <td colspan="4" class="text-end">TOTAL / JAM</td>
    <?php foreach ($shift['slots'] as $slot): ?>
        <td class="total-slot-target text-center">0</td>
        <td class="total-slot-fg text-center">0</td>
        <td class="total-slot-ng text-center">0</td>
    <?php endforeach ?>
</tr>
</tfoot>

</table>
</div>

<div class="shift-summary mt-2 mb-3 p-2 border rounded bg-light">
    <strong>SUMMARY <?= esc($shift['shift_name']) ?> :</strong>
    <span class="ms-3">OK: <span class="total-fg">0</span></span>
    <span class="ms-3">NG: <span class="total-ng">0</span></span>
    <span class="ms-3">Efficiency:
        <span class="eff">0%</span>
    </span>
</div>

<button class="btn btn-success mb-4">
    <i class="bi bi-save"></i>
    Simpan <?= esc($shift['shift_name']) ?>
</button>

</form>
<?php endforeach ?>

<!-- ================= CSS ================= -->
<style>
.table-scroll{overflow-x:auto}
.production-table{min-width:2600px}
.production-table th,td{
    font-size:13px;
    padding:4px;
    white-space:nowrap;
    text-align:center
}
.col-line{width:90px}
.col-machine{width:120px}
.col-part{width:280px}
.col-target-shift{width:110px}
.sticky-left{position:sticky;left:0;background:#fff;z-index:5}
.sticky-left-2{position:sticky;left:90px;background:#fff;z-index:5}
.sticky-left-3{position:sticky;left:210px;background:#fff;z-index:5}
.sticky-left-4{position:sticky;left:490px;background:#fff;z-index:5}
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
  i.readOnly=!a
  i.closest('td').classList.toggle('slot-active',a)
 })
 document.querySelectorAll('.slot-header').forEach(h=>{
  h.classList.toggle(
   'slot-header-active',
   isSlotActive(h.dataset.start,h.dataset.end)
  )
 })
}

function calcTotals(){
 document.querySelectorAll('.production-table').forEach(t=>{
  let ok=0,ng=0,target=0
  t.querySelectorAll('.fg').forEach(i=>ok+=+i.value||0)
  t.querySelectorAll('.ng').forEach(i=>ng+=+i.value||0)
  t.querySelectorAll('.target-shift').forEach(td=>target+=+td.innerText||0)

  const s=t.closest('.table-scroll').nextElementSibling
  if(!s)return
  s.querySelector('.total-fg').innerText=ok
  s.querySelector('.total-ng').innerText=ng
  s.querySelector('.eff').innerText=
   target?((ok/target)*100).toFixed(1)+'%':'0%'
 })
}

function calcSlotTotals(){
 document.querySelectorAll('.production-table').forEach(t=>{
  const rows=t.querySelectorAll('tbody tr')
  const slots=t.querySelectorAll('.total-slot-target').length
  let tg=Array(slots).fill(0),
      ok=Array(slots).fill(0),
      ng=Array(slots).fill(0)

  rows.forEach(r=>{
   const c=r.querySelectorAll('td')
   for(let i=4;i<c.length;i+=3){
    const idx=(i-4)/3
    tg[idx]+=+c[i].innerText||0
    ok[idx]+=+(c[i+1].querySelector('.fg')?.value||0)
    ng[idx]+=+(c[i+2].querySelector('.ng')?.value||0)
   }
  })

  t.querySelectorAll('.total-slot-target').forEach((e,i)=>e.innerText=tg[i])
  t.querySelectorAll('.total-slot-fg').forEach((e,i)=>e.innerText=ok[i])
  t.querySelectorAll('.total-slot-ng').forEach((e,i)=>e.innerText=ng[i])
 })
}

function recalcAll(){calcTotals();calcSlotTotals()}
updateActiveSlots()
recalcAll()
setInterval(updateActiveSlots,30000)
document.addEventListener('input',recalcAll)
</script>

<?= $this->endSection() ?>
