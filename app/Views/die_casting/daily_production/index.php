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

    <?php foreach ($shifts as $shift): ?>
        <?php
            $shiftCode = (int)($shift['shift_code'] ?? 0);
            $isShift3  = ($shiftCode === 3);
            $isDC      = (stripos((string)($shift['shift_name'] ?? ''), 'DC') !== false);

            // untuk tombol shift 3 aktif berdasarkan window shift 3
            $firstStart = '-';
            $lastEnd    = '-';
            if (!empty($shift['slots'])) {
                $firstStart = $shift['slots'][0]['time_start'] ?? '-';
                $lastSlot   = end($shift['slots']);
                $lastEnd    = $lastSlot['time_end'] ?? '-';
                reset($shift['slots']);
            }
        ?>

        <h5 class="mt-4 mb-2 d-flex align-items-center justify-content-between">
            <span><?= esc($shift['shift_name']) ?></span>

            <!-- Button hanya tampil pada Shift 3 -->
            <?php if ($isShift3 && $isDC): ?>
                <div class="d-flex align-items-center gap-2">
                    <small class="text-muted">
                        Aktif saat Shift 3 berjalan (<?= esc(substr((string)$firstStart,0,5)) ?> - <?= esc(substr((string)$lastEnd,0,5)) ?>)
                    </small>
                    <button type="button"
                            id="btn-finish-shift-<?= (int)$shift['id'] ?>"
                            class="btn btn-secondary btn-sm"
                            data-shift-code="<?= (int)$shiftCode ?>"
                            data-start="<?= esc($firstStart) ?>"
                            data-end="<?= esc($lastEnd) ?>"
                            disabled
                            onclick="finishShift3(<?= (int)$shift['id'] ?>)">
                        <i class="bi bi-send"></i> Finish Shift 3
                    </button>
                </div>
            <?php endif; ?>
        </h5>

        <div class="table-scroll">
            <table class="production-table table table-bordered table-sm">

                <thead>
                <tr>
                    <th rowspan="2" class="sticky-left col-machine">Mesin</th>
                    <th rowspan="2" class="sticky-left-2 col-part">Part</th>
                    <th rowspan="2" class="sticky-left-3 col-target-shift">Target<br>Shift</th>

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
                        <th class="col-slot-remark">NG Category</th>
                    <?php endforeach ?>
                </tr>
                </thead>

                <tbody class="shift-body">
                <?php foreach ($shift['items'] as $item): ?>
                    <tr>
                        <td class="sticky-left fw-bold text-center">
                            <?= esc($item['machine_code']) ?>
                        </td>

                        <td class="sticky-left-2 text-start fw-bold">
                            <?= esc($item['part_name']) ?>
                        </td>

                        <td class="sticky-left-3 fw-bold text-center target-shift">
                            <?= esc($item['qty_p']) ?>
                        </td>

                        <?php foreach ($shift['slots'] as $slot):

                            $targetSlot = $shift['total_minute'] > 0
                                ? (int) round(($item['qty_p'] / $shift['total_minute']) * $slot['minute'])
                                : 0;

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
                                       data-date="<?= esc($date) ?>"
                                       data-shift-id="<?= (int)$shift['id'] ?>"
                                       data-machine-id="<?= (int)$item['machine_id'] ?>"
                                       data-product-id="<?= (int)$item['product_id'] ?>"
                                       data-time-slot-id="<?= (int)$slot['id'] ?>"
                                       value="<?= $exist['qty_fg'] ?? 0 ?>"
                                       name="items[<?= $key ?>][fg]">
                            </td>

                            <td>
                                <input type="number"
                                       class="form-control form-control-sm slot-input ng"
                                       data-start="<?= $slot['time_start'] ?>"
                                       data-end="<?= $slot['time_end'] ?>"
                                       data-date="<?= esc($date) ?>"
                                       data-shift-id="<?= (int)$shift['id'] ?>"
                                       data-machine-id="<?= (int)$item['machine_id'] ?>"
                                       data-product-id="<?= (int)$item['product_id'] ?>"
                                       data-time-slot-id="<?= (int)$slot['id'] ?>"
                                       value="<?= $exist['qty_ng'] ?? 0 ?>"
                                       name="items[<?= $key ?>][ng]">
                            </td>

                            <td>
                                <select class="form-select form-select-sm slot-input ngcat"
                                        data-start="<?= $slot['time_start'] ?>"
                                        data-end="<?= $slot['time_end'] ?>"
                                        data-date="<?= esc($date) ?>"
                                        data-shift-id="<?= (int)$shift['id'] ?>"
                                        data-machine-id="<?= (int)$item['machine_id'] ?>"
                                        data-product-id="<?= (int)$item['product_id'] ?>"
                                        data-time-slot-id="<?= (int)$slot['id'] ?>"
                                        name="items[<?= $key ?>][ng_category_id]">
                                    <option value="">-- NG --</option>
                                    <?php foreach ($ngCategories as $ng): ?>
                                        <option value="<?= $ng['id'] ?>"
                                            <?= ($exist['ng_category_id'] ?? '') == $ng['id'] ? 'selected' : '' ?>>
                                            <?= esc($ng['ng_code'].' - '.$ng['ng_name']) ?>
                                        </option>
                                    <?php endforeach ?>
                                </select>
                            </td>

                            <input type="hidden" name="items[<?= $key ?>][shift_id]" value="<?= (int)$shift['id'] ?>">
                            <input type="hidden" name="items[<?= $key ?>][machine_id]" value="<?= (int)$item['machine_id'] ?>">
                            <input type="hidden" name="items[<?= $key ?>][product_id]" value="<?= (int)$item['product_id'] ?>">
                            <input type="hidden" name="items[<?= $key ?>][time_slot_id]" value="<?= (int)$slot['id'] ?>">
                            <input type="hidden" name="items[<?= $key ?>][date]" value="<?= esc($date) ?>">

                        <?php endforeach ?>
                    </tr>
                <?php endforeach ?>
                </tbody>

                <tfoot>
                <tr class="total-slot-row fw-bold">
                    <td colspan="3" class="text-end">TOTAL / JAM</td>
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

    <button class="btn btn-success mt-3" id="btnSave">
        <i class="bi bi-save"></i> Simpan
    </button>

</form>

<!-- ================= CSS ================= -->
<style>
.table-scroll{overflow-x:auto}
.production-table{min-width:2600px}
.production-table th,td{font-size:13px;padding:4px;white-space:nowrap;text-align:center}

.col-machine{width:110px}
.col-part{width:260px}
.col-target-shift{width:110px}

.sticky-left{
    position:sticky;
    left:0;
    background:#fff;
    z-index:6
}
.sticky-left-2{
    position:sticky;
    left:110px;
    background:#fff;
    z-index:6
}
.sticky-left-3{
    position:sticky;
    left:370px; /* 110 + 260 */
    background:#fff;
    z-index:6
}

.slot-active{background:#dcfce7!important}
.slot-header-active{background:#fde68a!important}
</style>

<!-- ================= JS ================= -->
<script>
let __isSubmitting = false;

const mainForm = document.getElementById('mainForm');
if (mainForm) {
  mainForm.addEventListener('submit', () => {
    __isSubmitting = true;
  });
}

// Ambil CSRF name+value dari hidden input (CI4)
function getCsrfPair() {
  const input = document.querySelector('#mainForm input[type="hidden"][name]');
  if (!input) return null;
  return { name: input.name, value: input.value, el: input };
}

// tanggal lokal ISO (bukan UTC)
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

// SLOT aktif pakai tanggal lokal hari ini
function isSlotActive(start,end){
  const now = new Date();
  const base = localDateISO(now);

  let s = timeToDate(base, start);
  let e = timeToDate(base, end);

  // lewat tengah malam
  if (e <= s) {
    if (now <= e) s.setDate(s.getDate() - 1); // start kemarin
    else e.setDate(e.getDate() + 1);          // end besok
  }
  return now >= s && now <= e;
}

// Shift 3 window aktif pakai TANGGAL PRODUKSI (base) agar tidak bug UTC
function shift3WindowActiveByProductionDate(prodDateISO, start, end){
  const now = new Date();

  let s = timeToDate(prodDateISO, start);
  let e = timeToDate(prodDateISO, end);

  // jika lewat midnight, end = besok
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

// Button Finish Shift 3 aktif hanya saat jam Shift 3 berjalan (mis 23:30-07:00)
function updateFinishShift3Buttons(){
  const prodDate = '<?= esc($date) ?>';

  document.querySelectorAll('button[id^="btn-finish-shift-"]').forEach(btn=>{
    const shiftCode = parseInt(btn.dataset.shiftCode || '0', 10);
    if (shiftCode !== 3) return;

    const start = btn.dataset.start || '';
    const end   = btn.dataset.end || '';

    const active = start && end && start !== '-' && end !== '-'
      ? shift3WindowActiveByProductionDate(prodDate, start, end)
      : false;

    btn.disabled = !active;
    btn.classList.toggle('btn-warning', active);
    btn.classList.toggle('btn-secondary', !active);

    btn.title = active
      ? 'Shift 3 sedang berjalan. Tombol aktif.'
      : 'Tombol hanya aktif saat jam Shift 3 berjalan.';
  });
}

/* ===============================
 * TOTAL PER SHIFT
 * =============================== */
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
    summary.querySelector('.eff').innerText =
      target ? ((fg/target)*100).toFixed(1)+'%' : '0%';
  });
}

/* ===============================
 * TOTAL PER SLOT (setelah 3 kolom sticky)
 * =============================== */
function calcSlotTotals(){
  document.querySelectorAll('.production-table').forEach(t=>{
    const rows = t.querySelectorAll('tbody tr');
    const slotCount = t.querySelectorAll('.total-slot-target').length;

    let tg = Array(slotCount).fill(0);
    let fg = Array(slotCount).fill(0);
    let ng = Array(slotCount).fill(0);

    rows.forEach(r=>{
      const cells = r.querySelectorAll('td');

      // setelah 3 kolom sticky: Mesin, Part, TargetShift
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

function recalcAll(){
  calcTotals();
  calcSlotTotals();
}

updateActiveSlots();
updateFinishShift3Buttons();
recalcAll();

setInterval(() => {
  updateActiveSlots();
  updateFinishShift3Buttons();
}, 30000);

document.addEventListener('input',recalcAll);

/* ===============================
 * AUTO SAVE PER SLOT (no alert saat submit / abort)
 * =============================== */
async function postSaveSlot(payload){
  const csrf = getCsrfPair();
  if (csrf) payload[csrf.name] = csrf.value;

  const res = await fetch('/die-casting/daily-production/save-slot', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: new URLSearchParams(payload),
    keepalive: true
  });

  const ct = res.headers.get('content-type') || '';
  if (!ct.includes('application/json')) {
    throw new Error('Non-JSON response');
  }

  const json = await res.json();

  // kalau backend mengembalikan csrf baru, update hidden input
  if (json.csrf && csrf?.el) csrf.el.value = json.csrf;

  return json;
}

function getRowSlotPayload(changedEl){
  const tr = changedEl.closest('tr');
  const time_slot_id = changedEl.dataset.timeSlotId;

  const fgEl  = tr.querySelector(`.fg[data-time-slot-id="${time_slot_id}"]`);
  const ngEl  = tr.querySelector(`.ng[data-time-slot-id="${time_slot_id}"]`);
  const ngcEl = tr.querySelector(`select.ngcat[data-time-slot-id="${time_slot_id}"]`);

  return {
    date: changedEl.dataset.date,
    shift_id: changedEl.dataset.shiftId,
    machine_id: changedEl.dataset.machineId,
    product_id: changedEl.dataset.productId,
    time_slot_id,
    fg: fgEl?.value || 0,
    ng: ngEl?.value || 0,
    ng_category_id: ngcEl?.value || ''
  };
}

document.querySelectorAll('.slot-input').forEach(el => {
  el.addEventListener('change', async () => {
    if (__isSubmitting) return;

    const td = el.closest('td');
    const payload = getRowSlotPayload(el);

    try {
      const res = await postSaveSlot(payload);
      if(res.status){
        td.classList.add('bg-success-subtle');
        setTimeout(()=>td.classList.remove('bg-success-subtle'),800);
      } else {
        if (!__isSubmitting) alert(res.message || 'Gagal simpan slot');
      }
    } catch (e) {
      // saat klik Simpan, navigasi halaman bisa abort fetch -> jangan alert
      if (__isSubmitting) return;
      if (e?.name === 'AbortError') return;

      td.classList.add('bg-danger-subtle');
      setTimeout(()=>td.classList.remove('bg-danger-subtle'),1200);
      alert('Network error');
    }
  });
});

/* ===============================
 * FINISH SHIFT 3
 * =============================== */
function finishShift3(shiftId){
  if(!confirm('Kirim ke proses berikutnya?')) return;

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
