<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">DIE CASTING – DAILY PRODUCTION PER HOUR</h4>

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

<form method="post" action="/die-casting/daily-production/store" id="mainForm">
  <?= csrf_field() ?>

  <?php
    // ambil shift 3 DC untuk tombol Finish Shift (hari ini) di bawah
    $shift3Id = null;
    $shift3Start = null;
    $shift3End = null;

    foreach ($shifts as $s) {
      $shiftCode = (int)($s['shift_code'] ?? 0);
      $isShift3  = ($shiftCode === 3);
      $isDC      = (stripos((string)($s['shift_name'] ?? ''), 'DC') !== false);

      if ($isShift3 && $isDC) {
        $shift3Id = (int)($s['id'] ?? 0);
        if (!empty($s['slots'])) {
          $shift3Start = $s['slots'][0]['time_start'] ?? null;
          $lastSlot = end($s['slots']);
          $shift3End = $lastSlot['time_end'] ?? null;
          reset($s['slots']);
        }
        break;
      }
    }
  ?>

  <?php foreach ($shifts as $shift): ?>
    <h5 class="mt-4 mb-2 d-flex align-items-center justify-content-between">
      <span><?= esc($shift['shift_name']) ?></span>
    </h5>

    <div class="table-scroll">
      <table class="production-table table table-bordered table-sm align-middle">
        <thead>
          <tr class="thead-row-1">
            <th rowspan="2" class="sticky-left col-machine th-sticky-left">
              Mesin
            </th>
            <th rowspan="2" class="sticky-left-2 col-part th-sticky-left">
              Part
            </th>
            <th rowspan="2" class="sticky-left-3 col-target-shift th-sticky-left">
              Target<br>Shift
            </th>

            <?php foreach ($shift['slots'] as $slot): ?>
              <th colspan="4"
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
              <th class="col-slot-fg">FG</th>
              <th class="col-slot-ng">NG</th>
              <th class="col-slot-remark">NG Category</th>
            <?php endforeach ?>
          </tr>
        </thead>

        <tbody class="shift-body">
          <?php foreach ($shift['items'] as $item): ?>
            <tr>
              <td class="sticky-left fw-bold text-center td-sticky-left">
                <?= esc($item['machine_code']) ?>
              </td>

              <td class="sticky-left-2 text-start fw-bold td-sticky-left">
                <?= esc($item['part_name']) ?>
              </td>

              <td class="sticky-left-3 fw-bold text-center target-shift td-sticky-left">
                <?= (int)$item['qty_p'] ?>
              </td>

              <?php foreach ($shift['slots'] as $slot):

                $targetSlot = $shift['total_minute'] > 0
                  ? (int) round(((int)$item['qty_p'] / (float)$shift['total_minute']) * (float)$slot['minute'])
                  : 0;

                $exist = $shift['hourly_map']
                  [(int)$item['machine_id']]
                  [(int)$item['product_id']]
                  [(int)$slot['id']] ?? null;

                $key = $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'];
              ?>

                <td class="slot-target fw-bold bg-light text-center">
                  <?= (int)$targetSlot ?>
                </td>

                <td>
                  <input type="number"
                         class="form-control form-control-sm slot-input fg"
                         data-start="<?= esc($slot['time_start']) ?>"
                         data-end="<?= esc($slot['time_end']) ?>"
                         data-date="<?= esc($date) ?>"
                         data-shift-id="<?= (int)$shift['id'] ?>"
                         data-machine-id="<?= (int)$item['machine_id'] ?>"
                         data-product-id="<?= (int)$item['product_id'] ?>"
                         data-time-slot-id="<?= (int)$slot['id'] ?>"
                         value="<?= (int)($exist['qty_fg'] ?? 0) ?>"
                         name="items[<?= esc($key) ?>][fg]">
                </td>

                <td>
                  <input type="number"
                         class="form-control form-control-sm slot-input ng"
                         data-start="<?= esc($slot['time_start']) ?>"
                         data-end="<?= esc($slot['time_end']) ?>"
                         data-date="<?= esc($date) ?>"
                         data-shift-id="<?= (int)$shift['id'] ?>"
                         data-machine-id="<?= (int)$item['machine_id'] ?>"
                         data-product-id="<?= (int)$item['product_id'] ?>"
                         data-time-slot-id="<?= (int)$slot['id'] ?>"
                         value="<?= (int)($exist['qty_ng'] ?? 0) ?>"
                         name="items[<?= esc($key) ?>][ng]">
                </td>

                <td>
                  <select class="form-select form-select-sm slot-input ngcat"
                          data-start="<?= esc($slot['time_start']) ?>"
                          data-end="<?= esc($slot['time_end']) ?>"
                          data-date="<?= esc($date) ?>"
                          data-shift-id="<?= (int)$shift['id'] ?>"
                          data-machine-id="<?= (int)$item['machine_id'] ?>"
                          data-product-id="<?= (int)$item['product_id'] ?>"
                          data-time-slot-id="<?= (int)$slot['id'] ?>"
                          name="items[<?= esc($key) ?>][ng_category_id]">
                    <option value="">-- NG --</option>
                    <?php foreach ($ngCategories as $ng): ?>
                      <option value="<?= (int)$ng['id'] ?>"
                        <?= ((string)($exist['ng_category_id'] ?? '') === (string)$ng['id']) ? 'selected' : '' ?>>
                        <?= esc($ng['ng_code'].' - '.$ng['ng_name']) ?>
                      </option>
                    <?php endforeach ?>
                  </select>
                </td>

                <input type="hidden" name="items[<?= esc($key) ?>][shift_id]" value="<?= (int)$shift['id'] ?>">
                <input type="hidden" name="items[<?= esc($key) ?>][machine_id]" value="<?= (int)$item['machine_id'] ?>">
                <input type="hidden" name="items[<?= esc($key) ?>][product_id]" value="<?= (int)$item['product_id'] ?>">
                <input type="hidden" name="items[<?= esc($key) ?>][time_slot_id]" value="<?= (int)$slot['id'] ?>">
                <input type="hidden" name="items[<?= esc($key) ?>][date]" value="<?= esc($date) ?>">

              <?php endforeach ?>
            </tr>
          <?php endforeach ?>
        </tbody>

        <tfoot>
          <tr class="total-slot-row fw-bold">
            <td colspan="3" class="text-end td-sticky-left">
              TOTAL / JAM
            </td>
            <?php foreach ($shift['slots'] as $slot): ?>
              <td class="total-slot-target text-center">0</td>
              <td class="total-slot-fg text-center">0</td>
              <td class="total-slot-ng text-center">0</td>
              <td class="total-slot-eff text-center"></td>
            <?php endforeach ?>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="shift-summary mt-2 mb-4 p-2 border rounded bg-light">
      <strong>SUMMARY <?= esc($shift['shift_name']) ?> :</strong>
      <span class="ms-3">FG: <span class="total-fg">0</span></span>
      <span class="ms-3">NG: <span class="total-ng">0</span></span>
      <span class="ms-3">Efficiency: <span class="eff">0%</span></span>
    </div>

  <?php endforeach ?>

  <!-- BUTTON BAR -->
  <div class="d-flex gap-2 align-items-center mt-3">
    <button class="btn btn-success" id="btnSave" type="submit">
      <i class="bi bi-save"></i> Simpan
    </button>

    <?php if ($shift3Id): ?>
      <button type="button"
              id="btnFinishShiftToday"
              class="btn btn-secondary"
              data-shift-id="<?= (int)$shift3Id ?>"
              data-start="<?= esc((string)($shift3Start ?? '')) ?>"
              data-end="<?= esc((string)($shift3End ?? '')) ?>"
              disabled
              onclick="finishShiftToday()">
        <i class="bi bi-send"></i> Finish Shift (Hari Ini)
      </button>

      <small class="text-muted">
        Aktif saat Shift 3 berjalan
        (<?= esc(substr((string)($shift3Start ?? '-'),0,5)) ?> - <?= esc(substr((string)($shift3End ?? '-'),0,5)) ?>)
      </small>
    <?php endif; ?>
  </div>

</form>

<style>
/* ====== FIX STICKY + SCROLL RAPi ====== */
.table-scroll{
  overflow:auto;
  position:relative;
  max-width:100%;
  border:1px solid #e5e7eb;
  border-radius:8px;
}

/* gunakan separate agar sticky tidak geser karena border-collapse */
.production-table{
  width:max-content;
  min-width:2600px;
  border-collapse:separate !important;
  border-spacing:0 !important;
  table-layout:fixed;
}

.production-table th,
.production-table td{
  font-size:13px;
  padding:6px;
  white-space:nowrap;
  text-align:center;
  vertical-align:middle;
  box-sizing:border-box;
}

/* tinggi header biar baris 1/2 konsisten */
.production-table thead tr.thead-row-1 th{
  height:38px;
}
.production-table thead tr.thead-row-2 th{
  height:38px;
}

/* sticky header atas (2 baris) */
.production-table thead tr.thead-row-1 th{
  position:sticky;
  top:0;
  z-index:30;
  background:#f8fafc;
}
.production-table thead tr.thead-row-2 th{
  position:sticky;
  top:38px;            /* sama dengan height row 1 */
  z-index:29;
  background:#f8fafc;
}

/* lebar kolom kiri */
.col-machine{ width:110px; min-width:110px; max-width:110px; }
.col-part{ width:260px; min-width:260px; max-width:260px; }
.col-target-shift{ width:120px; min-width:120px; max-width:120px; }

.col-slot-target{ width:80px; min-width:80px; }
.col-slot-fg{ width:80px; min-width:80px; }
.col-slot-ng{ width:80px; min-width:80px; }
.col-slot-remark{ width:160px; min-width:160px; }

/* sticky kolom kiri: TD */
.sticky-left{ position:sticky; left:0; z-index:20; background:#fff; }
.sticky-left-2{ position:sticky; left:110px; z-index:20; background:#fff; }
.sticky-left-3{ position:sticky; left:370px; z-index:20; background:#fff; } /* 110 + 260 */

/* sticky kolom kiri: TH (di THEAD harus lebih tinggi z-index) */
.th-sticky-left{
  z-index:40 !important;
  background:#f8fafc !important;
}

/* biar garis border rapi saat sticky */
.production-table th, .production-table td{
  border-right:1px solid #e5e7eb;
  border-bottom:1px solid #e5e7eb;
}
.production-table tr > *:first-child{
  border-left:1px solid #e5e7eb;
}
.production-table thead tr:first-child > *{
  border-top:1px solid #e5e7eb;
}

/* highlight slot aktif */
.slot-active{ background:#dcfce7 !important; }
.slot-header-active{ background:#fde68a !important; }

/* input biar tidak “menyempit” */
.production-table input.form-control,
.production-table select.form-select{
  min-width:70px;
}
</style>

<script>
let __isSubmitting = false;

const mainForm = document.getElementById('mainForm');
if (mainForm) mainForm.addEventListener('submit', () => { __isSubmitting = true; });

function getCsrfPair() {
  const input = document.querySelector('#mainForm input[type="hidden"][name]');
  if (!input) return null;
  return { name: input.name, value: input.value, el: input };
}

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

function shiftWindowActiveByProductionDate(prodDateISO, start, end){
  const now = new Date();
  let s = timeToDate(prodDateISO, start);
  let e = timeToDate(prodDateISO, end);
  if (e <= s) e.setDate(e.getDate() + 1);
  return now >= s && now <= e;
}

function updateActiveSlots(){
  document.querySelectorAll('.slot-input').forEach(el=>{
    const a = isSlotActive(el.dataset.start, el.dataset.end);
    if (el.tagName === 'SELECT') el.disabled = !a;
    else el.readOnly = !a;
    el.closest('td')?.classList.toggle('slot-active', a);
  });

  document.querySelectorAll('.slot-header').forEach(h=>{
    h.classList.toggle('slot-header-active', isSlotActive(h.dataset.start,h.dataset.end));
  });
}

function updateFinishButton(){
  const btn = document.getElementById('btnFinishShiftToday');
  if (!btn) return;

  const prodDate = '<?= esc($date) ?>';
  const start = btn.dataset.start || '';
  const end   = btn.dataset.end || '';

  const ok = start && end && start !== '-' && end !== '-'
    ? shiftWindowActiveByProductionDate(prodDate, start, end)
    : false;

  btn.disabled = !ok;
  btn.classList.toggle('btn-warning', ok);
  btn.classList.toggle('btn-secondary', !ok);
}

/* totals */
function calcTotals(){
  document.querySelectorAll('.production-table').forEach(table=>{
    let fg = 0, ng = 0, target = 0;
    table.querySelectorAll('.fg').forEach(i=> fg += +i.value || 0);
    table.querySelectorAll('.ng').forEach(i=> ng += +i.value || 0);
    table.querySelectorAll('.target-shift').forEach(td=> target += +td.innerText || 0);

    const summary = table.closest('.table-scroll')?.nextElementSibling;
    if(!summary || !summary.classList.contains('shift-summary')) return;

    summary.querySelector('.total-fg').innerText = fg;
    summary.querySelector('.total-ng').innerText = ng;
    summary.querySelector('.eff').innerText = target ? ((fg/target)*100).toFixed(1)+'%' : '0%';
  });
}

function calcSlotTotals(){
  document.querySelectorAll('.production-table').forEach(t=>{
    const rows = t.querySelectorAll('tbody tr');
    const slotCount = t.querySelectorAll('.total-slot-target').length;

    let tg = Array(slotCount).fill(0);
    let fg = Array(slotCount).fill(0);
    let ng = Array(slotCount).fill(0);

    rows.forEach(r=>{
      const cells = r.querySelectorAll('td');
      for(let i=3;i<cells.length;i+=4){
        const idx = (i-3)/4;
        tg[idx] += +cells[i].innerText || 0;
        fg[idx] += +(cells[i+1].querySelector('.fg')?.value || 0);
        ng[idx] += +(cells[i+2].querySelector('.ng')?.value || 0);
      }
    });

    t.querySelectorAll('.total-slot-target').forEach((e,i)=>e.innerText=tg[i]);
    t.querySelectorAll('.total-slot-fg').forEach((e,i)=>e.innerText=fg[i]);
    t.querySelectorAll('.total-slot-ng').forEach((e,i)=>e.innerText=ng[i]);
  });
}

function recalcAll(){ calcTotals(); calcSlotTotals(); }

updateActiveSlots();
updateFinishButton();
recalcAll();

setInterval(() => {
  updateActiveSlots();
  updateFinishButton();
}, 30000);

document.addEventListener('input', recalcAll);

function finishShiftToday(){
  const btn = document.getElementById('btnFinishShiftToday');
  if (!btn) return;

  const shiftId = parseInt(btn.dataset.shiftId || '0', 10);
  if (!shiftId) return;

  if(!confirm('Kirim FG Shift 3 ke proses berikutnya?')) return;

  const csrf = getCsrfPair();
  const payload = { date: '<?= esc($date) ?>', shift_id: shiftId };
  if (csrf) payload[csrf.name] = csrf.value;

  fetch('/die-casting/daily-production/finish-shift', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: new URLSearchParams(payload)
  })
  .then(r => r.json())
  .then(res => {
    if(res.status){
      alert('Berhasil.');
      location.reload();
    }else{
      alert(res.message || 'Gagal');
    }
  })
  .catch(()=> alert('Network error'));
}
</script>

<?= $this->endSection() ?>
