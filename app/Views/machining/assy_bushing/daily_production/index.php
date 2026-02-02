<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<?php
$isAdmin = $isAdmin ?? (strtoupper((string)(session()->get('role') ?? '')) === 'ADMIN');

$canFinishUI   = $isAdmin ? true : (bool)($canFinish ?? false);
$finishTitleUI = $isAdmin
    ? 'Admin: Finish Shift kapan saja'
    : (!(bool)($canFinish ?? false) ? esc($finishError ?? 'Belum bisa finish') : 'Finish Shift');
?>

<h4 class="mb-3">MACHINING – ASSY BUSHING DAILY PRODUCTION PER HOUR</h4>

<div class="mb-3">
    <strong>Tanggal:</strong> <?= esc($date) ?><br>
    <strong>Operator:</strong> <?= esc($operator) ?><br>
    <strong>Role:</strong> <?= esc(strtoupper((string)(session()->get('role') ?? '-'))) ?>
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

<!-- ✅ pastikan controller selalu dapat date -->
<input type="hidden" name="date" value="<?= esc($date) ?>">

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
    ($item['target_per_shift'] / max(1, $shift['total_minute'])) * ($slot['minute'] ?? 0)
);

$exist = $shift['hourly_map']
    [$item['machine_id']]
    [$item['product_id']]
    [$slot['id']] ?? null;

$key = $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'];
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
       name="items[<?= $key ?>][ok]">
</td>

<td>
<input type="number"
       class="form-control form-control-sm slot-input ng"
       data-start="<?= $slot['time_start'] ?>"
       data-end="<?= $slot['time_end'] ?>"
       value="<?= $exist['qty_ng'] ?? 0 ?>"
       name="items[<?= $key ?>][ng]">
</td>

<input type="hidden" name="items[<?= $key ?>][date]" value="<?= esc($date) ?>">
<input type="hidden" name="items[<?= $key ?>][shift_id]" value="<?= (int)$shift['id'] ?>">
<input type="hidden" name="items[<?= $key ?>][machine_id]" value="<?= (int)$item['machine_id'] ?>">
<input type="hidden" name="items[<?= $key ?>][product_id]" value="<?= (int)$item['product_id'] ?>">
<input type="hidden" name="items[<?= $key ?>][time_slot_id]" value="<?= (int)$slot['id'] ?>">

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

<div class="d-flex gap-2 mb-4">
  <button class="btn btn-success">
      <i class="bi bi-save"></i>
      Simpan <?= esc($shift['shift_name']) ?>
  </button>

  <?php if (!empty($shift['is_shift3'])): ?>
    <button
      type="submit"
      class="btn btn-warning"
      formaction="/machining/assy-bushing/hourly/finish-shift"
      <?= (!$canFinishUI) ? 'disabled' : '' ?>
      title="<?= $finishTitleUI ?>"
    >
      <i class="bi bi-check2-circle"></i>
      Finish Shift
      <?php if ($isAdmin): ?>
        <span class="badge bg-dark ms-2">ADMIN</span>
      <?php endif; ?>
    </button>

    <?php if (!$isAdmin && !($canFinish ?? false) && !empty($shift3EndAt)): ?>
      <div class="small text-muted align-self-center">
        Finish aktif setelah Shift 3 selesai (<?= esc($shift3EndAt) ?>)
      </div>
    <?php endif; ?>

    <?php if ($isAdmin && !empty($shift3EndAt)): ?>
      <div class="small text-muted align-self-center">
        Admin override aktif (Shift 3 selesai normal: <?= esc($shift3EndAt) ?>)
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

</form>
<?php endforeach ?>

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
