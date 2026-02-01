<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">MACHINING – DAILY PRODUCTION PER HOUR</h4>

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
           style="width: 180px"
           onchange="this.form.submit()">
</form>

<form method="post" action="/machining/hourly/store" id="hourlyForm">
<?= csrf_field() ?>

<?php foreach ($shifts as $shift): ?>

<?php
  // deteksi shift 3
  $isShift3 = ((int)($shift['shift_code'] ?? 0) === 3) || preg_match('/\b3\b/', (string)($shift['shift_name'] ?? ''));
?>

<h5 class="mt-4 mb-2 d-flex align-items-center justify-content-between">
  <span><?= esc($shift['shift_name']) ?></span>


</h5>

<div class="table-scroll">
<table class="production-table table table-bordered table-sm">

<thead>
<tr>
    <th rowspan="2" class="sticky-left col-line">Line</th>
    <th rowspan="2" class="sticky-left-2 col-machine">Machine</th>
    <th rowspan="2" class="sticky-left-3 col-part">Part</th>
    <th rowspan="2" class="sticky-left-4 col-target-shift">Target<br>Shift</th>

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
<tr class="total-slot-row fw-bold">
    <td colspan="4" class="text-end">TOTAL / JAM</td>
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

<div class="shift-summary mt-2 mb-4 p-2 border rounded bg-light">
    <strong>SUMMARY <?= esc($shift['shift_name']) ?> :</strong>
    <span class="ms-3">
        FG: <span class="total-fg">0</span>
    </span>
    <span class="ms-3">
        NG: <span class="total-ng">0</span>
    </span>
    <span class="ms-3">
        Efficiency: <span class="eff">0%</span>
    </span>
</div>

<?php endforeach ?>

<!-- tombol simpan biasa -->
<button class="btn btn-success mt-3" type="submit" formaction="/machining/hourly/store">
  <i class="bi bi-save"></i> Simpan
</button>

<?php if (!empty($isAdmin) || !empty($canFinish)): ?>
  <button class="btn btn-warning mt-3 ms-2" type="submit" formaction="/machining/hourly/finish-shift"
          onclick="return confirm('Finish Shift? Total FG Machining akan dikirim ke proses berikutnya sesuai flow.')">
    <i class="bi bi-send-check"></i>
    Finish Shift <?= !empty($isAdmin) ? '(ADMIN)' : '' ?>
  </button>

  <?php if (empty($isAdmin) && !empty($shift3EndAt)): ?>
    <div class="text-muted mt-2" style="font-size:13px">
      Finish Shift aktif setelah Shift 3 selesai (<?= esc($shift3EndAt) ?> WIB)
    </div>
  <?php endif; ?>

<?php else: ?>
  <button class="btn btn-warning mt-3 ms-2" type="button" disabled
          title="<?= esc($finishError ?? 'Belum bisa Finish Shift') ?>">
    <i class="bi bi-send-check"></i> Finish Shift (Kirim FG)
  </button>

  <?php if (!empty($shift3EndAt)): ?>
    <div class="text-muted mt-2" style="font-size:13px">
      Finish Shift aktif setelah Shift 3 selesai (<?= esc($shift3EndAt) ?> WIB)
    </div>
  <?php endif; ?>
<?php endif; ?>



</form>

<style>
.table-scroll{overflow-x:auto}
.production-table{min-width:3000px}
.production-table th,td{font-size:13px;padding:4px;white-space:nowrap;text-align:center}
.col-line{width:90px}.col-machine{width:120px}.col-part{width:280px}.col-target-shift{width:110px}
.sticky-left{position:sticky;left:0;background:#fff;z-index:5}
.sticky-left-2{position:sticky;left:90px;background:#fff;z-index:5}
.sticky-left-3{position:sticky;left:210px;background:#fff;z-index:5}
.sticky-left-4{position:sticky;left:490px;background:#fff;z-index:5}
.slot-active{background:#dcfce7!important}
.slot-header-active{background:#fde68a!important}
</style>

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
        th.classList.toggle('slot-header-active', isSlotActive(th.dataset.start, th.dataset.end));
    });
}

function calcTotals(){
 document.querySelectorAll('.production-table').forEach(t=>{
  let fg=0,ng=0,target=0;
  t.querySelectorAll('.fg').forEach(i=>fg+=+i.value||0);
  t.querySelectorAll('.ng').forEach(i=>ng+=+i.value||0);
  t.querySelectorAll('.target-shift').forEach(td=>target+=+td.innerText||0);

  const summary = t.closest('div')?.nextElementSibling; // shift-summary
  if (summary) {
    summary.querySelector('.total-fg').innerText = fg;
    summary.querySelector('.total-ng').innerText = ng;
    summary.querySelector('.eff').innerText = target ? ((fg/target)*100).toFixed(1)+'%' : '0%';
  }
 })
}

function calcSlotTotals(){
 document.querySelectorAll('.production-table').forEach(t=>{
  const rows=t.querySelectorAll('tbody tr');
  const slots=t.querySelectorAll('.total-slot-target').length;
  let tg=Array(slots).fill(0),fg=Array(slots).fill(0),ng=Array(slots).fill(0);

  rows.forEach(r=>{
   const cells=r.querySelectorAll('td');
   for(let i=4;i<cells.length;i+=4){
    const idx=(i-4)/4;
    tg[idx]+=+cells[i].innerText||0;
    fg[idx]+=+(cells[i+1].querySelector('.fg')?.value||0);
    ng[idx]+=+(cells[i+2].querySelector('.ng')?.value||0);
   }
  });

  t.querySelectorAll('.total-slot-target').forEach((c,i)=>c.innerText=tg[i]);
  t.querySelectorAll('.total-slot-fg').forEach((c,i)=>c.innerText=fg[i]);
  t.querySelectorAll('.total-slot-ng').forEach((c,i)=>c.innerText=ng[i]);
  t.querySelectorAll('.total-slot-eff').forEach((c,i)=>{
   c.innerText=tg[i]?((fg[i]/tg[i])*100).toFixed(1)+'%':'0%';
  });
 })
}

function recalcAll(){calcTotals();calcSlotTotals();}
updateActiveSlots();recalcAll();
setInterval(updateActiveSlots,30000);
document.addEventListener('input',recalcAll);
</script>

<?= $this->endSection() ?>
