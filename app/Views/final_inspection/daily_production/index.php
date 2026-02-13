<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">FINAL INSPECTION – DAILY PRODUCTION PER HOUR</h4>

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

<form method="post" action="/final-inspection/daily-production/store" id="mainForm">
  <?= csrf_field() ?>

  <?php foreach ($shifts as $shift): ?>
    <h5 class="mt-4 mb-2 d-flex align-items-center justify-content-between">
      <span><?= esc($shift['shift_name']) ?></span>
    </h5>

    <div class="table-scroll">
      <table class="production-table table table-bordered table-sm align-middle">
        <thead>
          <tr class="thead-row-1">
            <th rowspan="2" class="sticky-left col-partno th-sticky-left">Part No</th>
            <th rowspan="2" class="sticky-left-2 col-partname th-sticky-left">Part Name</th>
            <th rowspan="2" class="sticky-left-3 col-incoming th-sticky-left">Incoming</th>

            <?php foreach ($shift['slots'] as $slot): ?>
              <th colspan="3"
                  class="slot-header"
                  data-start="<?= esc($slot['time_start']) ?>"
                  data-end="<?= esc($slot['time_end']) ?>">
                <?= esc(substr((string)$slot['time_start'],0,5)) ?> - <?= esc(substr((string)$slot['time_end'],0,5)) ?>
              </th>
            <?php endforeach ?>
          </tr>

          <tr class="thead-row-2">
            <?php foreach ($shift['slots'] as $slot): ?>
              <th class="col-slot-target">Target</th>
              <th class="col-slot-ok">OK</th>
              <th class="col-slot-ng">NG</th>
            <?php endforeach ?>
          </tr>
        </thead>

        <tbody class="shift-body">
          <?php foreach ($shift['items'] as $item): ?>
            <tr>
              <td class="sticky-left fw-bold text-start td-sticky-left">
                <?= esc($item['part_no']) ?>
              </td>

              <td class="sticky-left-2 text-start td-sticky-left">
                <?= esc($item['part_name']) ?>
              </td>

              <td class="sticky-left-3 fw-bold text-center td-sticky-left incoming">
                <?= (int)$item['incoming'] ?>
              </td>

              <?php foreach ($shift['slots'] as $slot):

                $targetSlot = $shift['total_minute'] > 0
                  ? (int) round(((int)$item['incoming'] / (float)$shift['total_minute']) * (float)$slot['minute'])
                  : 0;

                $exist = $shift['hourly_map'][(int)$item['product_id']][(int)$slot['id']] ?? null;
                $key = $shift['id'].'_'.$item['product_id'].'_'.$slot['id'];
              ?>
                <td class="slot-target fw-bold bg-light text-center"><?= (int)$targetSlot ?></td>

                <td>
                  <input type="number"
                         class="form-control form-control-sm slot-input ok"
                         data-start="<?= esc($slot['time_start']) ?>"
                         data-end="<?= esc($slot['time_end']) ?>"
                         data-date="<?= esc($date) ?>"
                         data-shift-id="<?= (int)$shift['id'] ?>"
                         data-product-id="<?= (int)$item['product_id'] ?>"
                         data-time-slot-id="<?= (int)$slot['id'] ?>"
                         value="<?= (int)($exist['qty_ok'] ?? 0) ?>"
                         name="items[<?= esc($key) ?>][ok]">
                </td>

                <td>
                  <input type="number"
                         class="form-control form-control-sm slot-input ng"
                         data-start="<?= esc($slot['time_start']) ?>"
                         data-end="<?= esc($slot['time_end']) ?>"
                         data-date="<?= esc($date) ?>"
                         data-shift-id="<?= (int)$shift['id'] ?>"
                         data-product-id="<?= (int)$item['product_id'] ?>"
                         data-time-slot-id="<?= (int)$slot['id'] ?>"
                         value="<?= (int)($exist['qty_ng'] ?? 0) ?>"
                         name="items[<?= esc($key) ?>][ng]">
                </td>

                <input type="hidden" name="items[<?= esc($key) ?>][shift_id]" value="<?= (int)$shift['id'] ?>">
                <input type="hidden" name="items[<?= esc($key) ?>][product_id]" value="<?= (int)$item['product_id'] ?>">
                <input type="hidden" name="items[<?= esc($key) ?>][time_slot_id]" value="<?= (int)$slot['id'] ?>">
                <input type="hidden" name="items[<?= esc($key) ?>][date]" value="<?= esc($date) ?>">
                <input type="hidden" name="items[<?= esc($key) ?>][process_id]" value="<?= (int)$fiProcessId ?>">

              <?php endforeach ?>
            </tr>
          <?php endforeach ?>
        </tbody>

        <tfoot>
          <tr class="total-slot-row fw-bold">
            <td colspan="3" class="text-end td-sticky-left">TOTAL / JAM</td>
            <?php foreach ($shift['slots'] as $slot): ?>
              <td class="total-slot-target text-center">0</td>
              <td class="total-slot-ok text-center">0</td>
              <td class="total-slot-ng text-center">0</td>
            <?php endforeach ?>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="shift-summary mt-2 mb-4 p-2 border rounded bg-light">
      <strong>SUMMARY <?= esc($shift['shift_name']) ?> :</strong>
      <span class="ms-3">OK: <span class="total-ok">0</span></span>
      <span class="ms-3">NG: <span class="total-ng">0</span></span>
      <span class="ms-3">Inspected: <span class="total-done">0</span></span>
      <span class="ms-3">Efficiency: <span class="eff">0%</span></span>
    </div>

  <?php endforeach ?>

  <div class="d-flex gap-2 align-items-center mt-3">
    <button class="btn btn-success" id="btnSave" type="submit">
      <i class="bi bi-save"></i> Simpan
    </button>
  </div>

</form>

<style>
.table-scroll{ overflow:auto; position:relative; max-width:100%; border:1px solid #e5e7eb; border-radius:8px; }
.production-table{ width:max-content; min-width:2200px; border-collapse:separate !important; border-spacing:0 !important; table-layout:fixed; }
.production-table th, .production-table td{ font-size:13px; padding:6px; white-space:nowrap; text-align:center; vertical-align:middle; box-sizing:border-box; }
.production-table thead tr.thead-row-1 th{ height:38px; position:sticky; top:0; z-index:30; background:#f8fafc; }
.production-table thead tr.thead-row-2 th{ height:38px; position:sticky; top:38px; z-index:29; background:#f8fafc; }

.col-partno{ width:160px; min-width:160px; }
.col-partname{ width:280px; min-width:280px; }
.col-incoming{ width:120px; min-width:120px; }

.col-slot-target{ width:90px; min-width:90px; }
.col-slot-ok{ width:90px; min-width:90px; }
.col-slot-ng{ width:90px; min-width:90px; }

.sticky-left{ position:sticky; left:0; z-index:20; background:#fff; }
.sticky-left-2{ position:sticky; left:160px; z-index:20; background:#fff; }
.sticky-left-3{ position:sticky; left:440px; z-index:20; background:#fff; }
.th-sticky-left{ z-index:40 !important; background:#f8fafc !important; }

.production-table th, .production-table td{ border-right:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; }
.production-table tr > *:first-child{ border-left:1px solid #e5e7eb; }
.production-table thead tr:first-child > *{ border-top:1px solid #e5e7eb; }

.slot-active{ background:#dcfce7 !important; }
.slot-header-active{ background:#fde68a !important; }
</style>

<script>
function localDateISO(d = new Date()){
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2,'0');
  const day = String(d.getDate()).padStart(2,'0');
  return `${y}-${m}-${day}`;
}
function timeToDate(baseISO, timeStr){
  const hhmm = String(timeStr || '').slice(0,5);
  return new Date(`${baseISO}T${hhmm}:00`);
}
function isSlotActive(start,end){
  const now = new Date();
  const base = localDateISO(now);
  let s = timeToDate(base, start);
  let e = timeToDate(base, end);
  if (e <= s) {
    if (now <= e) s.setDate(s.getDate() - 1);
    else e.setDate(e.getDate() + 1);
  }
  return now >= s && now <= e;
}
function updateActiveSlots(){
  document.querySelectorAll('.slot-input').forEach(el=>{
    const a = isSlotActive(el.dataset.start, el.dataset.end);
    el.readOnly = !a;
    el.closest('td')?.classList.toggle('slot-active', a);
  });
  document.querySelectorAll('.slot-header').forEach(h=>{
    h.classList.toggle('slot-header-active', isSlotActive(h.dataset.start,h.dataset.end));
  });
}

/* totals */
function calcTotals(){
  document.querySelectorAll('.production-table').forEach(table=>{
    let ok = 0, ng = 0, target = 0;
    table.querySelectorAll('.ok').forEach(i=> ok += +i.value || 0);
    table.querySelectorAll('.ng').forEach(i=> ng += +i.value || 0);
    table.querySelectorAll('.incoming').forEach(td=> target += +td.innerText || 0);

    const summary = table.closest('.table-scroll')?.nextElementSibling;
    if(!summary || !summary.classList.contains('shift-summary')) return;

    summary.querySelector('.total-ok').innerText = ok;
    summary.querySelector('.total-ng').innerText = ng;
    summary.querySelector('.total-done').innerText = (ok+ng);
    summary.querySelector('.eff').innerText = target ? (((ok+ng)/target)*100).toFixed(1)+'%' : '0%';
  });
}
function calcSlotTotals(){
  document.querySelectorAll('.production-table').forEach(t=>{
    const rows = t.querySelectorAll('tbody tr');
    const slotCount = t.querySelectorAll('.total-slot-target').length;

    let tg = Array(slotCount).fill(0);
    let ok = Array(slotCount).fill(0);
    let ng = Array(slotCount).fill(0);

    rows.forEach(r=>{
      const cells = r.querySelectorAll('td');
      for(let i=3;i<cells.length;i+=3){
        const idx = (i-3)/3;
        tg[idx] += +cells[i].innerText || 0;
        ok[idx] += +(cells[i+1].querySelector('.ok')?.value || 0);
        ng[idx] += +(cells[i+2].querySelector('.ng')?.value || 0);
      }
    });

    t.querySelectorAll('.total-slot-target').forEach((e,i)=>e.innerText=tg[i]);
    t.querySelectorAll('.total-slot-ok').forEach((e,i)=>e.innerText=ok[i]);
    t.querySelectorAll('.total-slot-ng').forEach((e,i)=>e.innerText=ng[i]);
  });
}
function recalcAll(){ calcTotals(); calcSlotTotals(); }

updateActiveSlots();
recalcAll();
setInterval(updateActiveSlots, 30000);
document.addEventListener('input', recalcAll);
</script>

<?= $this->endSection() ?>
