<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">MACHINING – LEAK TEST DAILY PRODUCTION PER HOUR</h4>

<div class="d-flex flex-wrap align-items-end gap-4 mb-4">
  <div>
    <form method="get" class="mb-0">
      <label class="fw-bold me-2">Tanggal Produksi:</label>
      <input type="date"
             name="date"
             value="<?= esc($date) ?>"
             class="form-control d-inline-block"
             style="width: 180px"
             onchange="this.form.submit()">
    </form>
  </div>
  <div>
    <strong>Operator:</strong> <span class="text-primary"><?= esc($operator ?? 'Unknown') ?></span>
  </div>

  <?php if ($isAdmin): ?>
  <div class="form-check form-switch mb-1">
    <input class="form-check-input border-danger" type="checkbox" id="adminOverrideToggle" role="switch" style="cursor: pointer; transform: scale(1.2);">
    <label class="form-check-label fw-bold text-danger ms-2" for="adminOverrideToggle" style="cursor: pointer;">
      <i class="bi bi-unlock-fill"></i> Unlock Semua Slot Waktu (Admin)
    </label>
  </div>
  <?php endif; ?>
</div>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <strong><i class="bi bi-check-circle"></i> Berhasil!</strong> <?= esc(session()->getFlashdata('success')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong><i class="bi bi-exclamation-triangle"></i> Gagal!</strong> <?= esc(session()->getFlashdata('error')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif ?>

<style>
.table-scroll{
  overflow:auto;
  position:relative;
  max-width:100%;
  border:1px solid #e5e7eb;
  border-radius:12px;
  background:#fff;
  padding:10px;
}
.production-table{
  width:max-content;
  border-collapse:separate !important;
  border-spacing:0 !important;
  table-layout:fixed;
}
.production-table th,
.production-table td{
  font-size:13px;
  padding:8px 8px;
  white-space:nowrap;
  text-align:center;
  vertical-align:middle;
  box-sizing:border-box;
  line-height:1.2;
  background:#fff;
  transition: background 0.3s;
}
.production-table th,
.production-table td{
  border-right:1px solid #e5e7eb;
  border-bottom:1px solid #e5e7eb;
}
.production-table tr > *:first-child{ border-left:1px solid #e5e7eb; }
.production-table thead tr:first-child > *{ border-top:1px solid #e5e7eb; }

.production-table thead tr.thead-row-1 th{
  position:sticky; top:0; z-index:30;
  background:#f8fafc; font-weight:900; height:44px;
}
.production-table thead tr.thead-row-2 th{
  position:sticky; top:44px; z-index:29;
  background:#f1f5f9; font-weight:900; height:44px; font-size:12px;
}

.col-line{ width:90px; min-width:90px; max-width:90px; }
.col-machine{ width:120px; min-width:120px; max-width:120px; }
.col-part{ width:320px; min-width:320px; max-width:320px; }
.col-target-shift{ width:140px; min-width:140px; max-width:140px; }

.col-slot-target{ width:90px; min-width:90px; }
.col-slot-ok{ width:90px; min-width:90px; }
.col-slot-ng{ width:90px; min-width:90px; }
.col-slot-remark{ width:360px; min-width:360px; } 

.sticky-left{ position:sticky; left:0; z-index:40; background:#fff; }
.sticky-left-2{ position:sticky; left:90px; z-index:40; background:#fff; }
.sticky-left-3{ position:sticky; left:210px; z-index:40; background:#fff; }
.sticky-left-4{ position:sticky; left:530px; z-index:40; background:#fff; }

.th-sticky-left{ z-index:60 !important; background:#f8fafc !important; }

.slot-active{ background:#dcfce7 !important; }
.slot-header-active{ background:#fde68a !important; }
.slot-rest { background-color: #cbd5e1 !important; opacity: 0.7; }
.slot-locked input, .slot-locked select { background-color: #f1f5f9; }

/* ===== NG INLINE TABLE ===== */
.ng-inline{ display:flex; flex-direction:column; gap:8px; }
.ng-inline-head{ display:flex; justify-content:space-between; align-items:center; gap:8px; }
.ng-inline-head .meta{ font-size:12px; color:#64748b; font-weight:700; }

.ng-mini-wrap{
  max-height:140px;
  overflow:auto;
  border:1px solid #e5e7eb;
  border-radius:10px;
  background:#fff;
}
.ng-mini-table{ width:100%; border-collapse:separate; border-spacing:0; }
.ng-mini-table th, .ng-mini-table td{
  border:1px solid #e5e7eb;
  padding:6px 6px;
  font-size:12px;
  text-align:left;
  background:#fff;
}
.ng-mini-table th{
  position:sticky; top:0; z-index:2;
  background:#f8fafc; font-weight:900; text-align:center;
}
.ng-mini-table td.ng-no{ width:60px; text-align:center; font-weight:900; }
.ng-mini-table td.ng-qty{ width:110px; }
.ng-mini-table td.ng-act{ width:70px; text-align:center; }
.ng-empty{
  font-size:12px; color:#64748b; font-weight:700;
  text-align:center; padding:8px 0;
  border:1px dashed #cbd5e1; border-radius:8px;
}

.rest-toggle { transform: scale(0.9); margin-top: 0.25rem !important; }
</style>

<form method="post" action="/machining/leak-test/hourly/store" id="formSaveHourly">
  <?= csrf_field() ?>

  <?php foreach ($shifts as $shift): ?>
    <?php 
      if (empty($shift['slots'])) continue; 
      $shiftId = (int)$shift['id']; 
    ?>

    <div class="d-flex flex-column gap-1 mt-4 mb-2">
      <div class="d-flex align-items-center justify-content-between">
        <h5 class="m-0 text-primary border-start border-4 border-primary ps-2"><?= esc($shift['shift_name']) ?></h5>
      </div>
      <small class="text-muted ms-3">
        <i class="bi bi-clock-history"></i> Total Waktu Produksi (Slot Aktif): 
        <strong class="shift-active-minutes-display" data-shift-id="<?= $shift['id'] ?>" data-original-minutes="<?= $shift['total_minute'] ?>">
          <?= $shift['total_minute'] ?> Menit
        </strong>
      </small>
    </div>

    <div class="table-scroll">
      <table class="production-table table table-sm align-middle">
        <thead>
          <tr class="thead-row-1">
            <th rowspan="2" class="sticky-left col-line th-sticky-left">Line</th>
            <th rowspan="2" class="sticky-left-2 col-machine th-sticky-left">Machine</th>
            <th rowspan="2" class="sticky-left-3 col-part th-sticky-left">Part</th>
            <th rowspan="2" class="sticky-left-4 col-target-shift th-sticky-left">Target<br>Shift</th>

            <?php foreach ($shift['slots'] as $slot): ?>
              <th colspan="4"
                  class="slot-header"
                  data-start="<?= esc($slot['time_start']) ?>"
                  data-end="<?= esc($slot['time_end']) ?>"
                  data-shift-id="<?= $shift['id'] ?>"
                  data-slot-id="<?= $slot['id'] ?>">
                <div class="mb-1"><?= esc(substr((string)$slot['time_start'],0,5)) ?> - <?= esc(substr((string)$slot['time_end'],0,5)) ?></div>
                
                <div class="form-check form-switch d-flex justify-content-center m-0 pb-1">
                  <input class="form-check-input rest-toggle border-secondary" type="checkbox" 
                         id="rest_<?= $shift['id'] ?>_<?= $slot['id'] ?>" 
                         data-shift-id="<?= $shift['id'] ?>" 
                         data-slot-id="<?= $slot['id'] ?>" 
                         data-slot-minutes="<?= $slot['minute'] ?>"
                         title="Tandai sebagai Jam Istirahat">
                  <label class="form-check-label ms-1 text-muted fw-normal" style="font-size: 11px; cursor:pointer;" for="rest_<?= $shift['id'] ?>_<?= $slot['id'] ?>">Rest</label>
                </div>
              </th>
            <?php endforeach ?>
          </tr>

          <tr class="thead-row-2">
            <?php foreach ($shift['slots'] as $slot): ?>
              <th class="col-slot-target">Target</th>
              <th class="col-slot-ok">OK</th>
              <th class="col-slot-ng">NG</th>
              <th class="col-slot-remark">NG Category</th>
            <?php endforeach ?>
          </tr>
        </thead>

        <tbody class="shift-body" data-shift-id="<?= $shift['id'] ?>">
        <?php foreach ($shift['items'] as $item): ?>
          <tr class="item-row" data-original-target="<?= (int)$item['target_per_shift'] ?>">
            <td class="sticky-left fw-bold text-center"><?= esc($item['line_position']) ?></td>
            <td class="sticky-left-2 text-center fw-bold"><?= esc($item['machine_code']) ?></td>
            <td class="sticky-left-3 text-start fw-bold"><?= esc($item['part_no'].' - '.$item['part_name']) ?></td>
            <td class="sticky-left-4 fw-bold text-center text-primary fs-6 target-shift">
                <span class="target-shift-display"><?= (int)$item['target_per_shift'] ?></span>
            </td>

            <?php foreach ($shift['slots'] as $slot):
              $targetSlot = $shift['total_minute'] > 0
                ? (int) round(((int)$item['target_per_shift'] / (float)$shift['total_minute']) * (float)$slot['minute'])
                : 0;

              $exist = $shift['hourly_map'][(int)$item['machine_id']][(int)$item['product_id']][(int)$slot['id']] ?? null;
              $ngDetail = $shift['ng_detail_map'][(int)$item['machine_id']][(int)$item['product_id']][(int)$slot['id']] ?? [];

              $key = $shiftId.'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'];
            ?>

              <td class="slot-target-cell fw-bold bg-light text-center" data-slot-id="<?= $slot['id'] ?>">
                 <span class="slot-target-display"><?= (int)$targetSlot ?></span>
              </td>

              <td>
                <input type="number"
                       class="form-control form-control-sm slot-input ok val-ok"
                       data-date="<?= esc($date) ?>"
                       data-start="<?= esc($slot['time_start']) ?>"
                       data-end="<?= esc($slot['time_end']) ?>"
                       data-shift-id="<?= $shift['id'] ?>"
                       data-slot-id="<?= $slot['id'] ?>"
                       value="<?= (int)($exist['qty_ok'] ?? 0) ?>"
                       name="items[<?= esc($key) ?>][ok]">
              </td>

              <td>
                <input type="number"
                       class="form-control form-control-sm slot-input ng val-ng"
                       readonly
                       id="ngTotalInput_<?= esc($key) ?>"
                       value="<?= (int)($exist['qty_ng'] ?? 0) ?>"
                       name="items[<?= esc($key) ?>][ng]">
              </td>

              <td class="text-start outer-td">
                <div class="ng-inline" data-key="<?= esc($key) ?>">
                  <div class="ng-inline-head">
                    <div class="meta">
                      Total NG: <span class="fw-bold text-danger" id="ngTotalBadge_<?= esc($key) ?>">0</span>
                    </div>
                    <button type="button"
                            class="btn btn-sm btn-outline-danger fw-bold ng-add-btn"
                            onclick="addNgRowInline('<?= esc($key) ?>', <?= $shift['id'] ?>)"
                            data-start="<?= esc($slot['time_start']) ?>"
                            data-end="<?= esc($slot['time_end']) ?>"
                            data-shift-id="<?= $shift['id'] ?>"
                            data-slot-id="<?= $slot['id'] ?>">
                      + NG
                    </button>
                  </div>

                  <div class="ng-mini-wrap">
                    <table class="ng-mini-table">
                      <thead>
                        <tr>
                          <th style="width:60px">NG</th>
                          <th>Category</th>
                          <th style="width:110px">Qty</th>
                          <th style="width:70px"></th>
                        </tr>
                      </thead>
                      <tbody id="ngBody_<?= esc($key) ?>"></tbody>
                    </table>
                  </div>
                </div>

                <div class="ng-hidden d-none" id="ngHidden_<?= esc($key) ?>">
                  <?php foreach ($ngDetail as $idx => $d): ?>
                    <input type="hidden" name="items[<?= esc($key) ?>][ng_details][<?= $idx ?>][ng_category_id]" value="<?= (int)($d['ng_category_id'] ?? 0) ?>">
                    <input type="hidden" name="items[<?= esc($key) ?>][ng_details][<?= $idx ?>][qty]" value="<?= (int)($d['qty'] ?? 0) ?>">
                  <?php endforeach; ?>
                </div>

                <input type="hidden" name="items[<?= esc($key) ?>][shift_id]" value="<?= (int)$shiftId ?>">
                <input type="hidden" name="items[<?= esc($key) ?>][machine_id]" value="<?= (int)$item['machine_id'] ?>">
                <input type="hidden" name="items[<?= esc($key) ?>][product_id]" value="<?= (int)$item['product_id'] ?>">
                <input type="hidden" name="items[<?= esc($key) ?>][time_slot_id]" value="<?= (int)$slot['id'] ?>">
                <input type="hidden" name="items[<?= esc($key) ?>][date]" value="<?= esc($date) ?>">
              </td>

            <?php endforeach ?>
          </tr>
        <?php endforeach ?>
        </tbody>

        <tfoot>
          <tr class="total-slot-row fw-bold">
            <td colspan="4" class="text-end">TOTAL / JAM</td>
            <?php foreach ($shift['slots'] as $slot): ?>
              <td class="total-slot-target text-center">0</td>
              <td class="total-slot-ok text-center">0</td>
              <td class="total-slot-ng text-center">0</td>
              <td class="total-slot-eff text-center"></td>
            <?php endforeach ?>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="shift-summary mt-2 mb-4 p-3 border rounded bg-light summary-box" data-shift-id="<?= $shift['id'] ?>">
      <strong>SUMMARY <?= esc($shift['shift_name']) ?> :</strong>
      <span class="ms-4">TOTAL TARGET: <span class="total-target fw-bold text-dark fs-5">0</span></span>
      <span class="ms-4">TOTAL OK: <span class="total-ok fw-bold text-success fs-5">0</span></span>
      <span class="ms-4">TOTAL NG: <span class="total-ng fw-bold text-danger fs-5">0</span></span>
      <span class="ms-4 border-start ps-4">EFISIENSI (Ach): <span class="eff fw-bold text-primary fs-5">0%</span></span>
    </div>

  <?php endforeach ?>

  <div class="position-sticky bottom-0 bg-white p-3 border-top shadow-sm d-flex justify-content-end mt-3 z-3">
      <button class="btn btn-success fw-bold shadow-sm px-5" id="btnSave" type="submit">
        <i class="bi bi-save me-1"></i> Simpan Leak Test
      </button>
  </div>
</form>

<script>
const NG_CATEGORIES = <?= json_encode(array_map(fn($x)=>[
  'id'=>(int)$x['id'],
  'ng_code'=>(int)($x['ng_code'] ?? $x['id']),
  'ng_name'=>(string)($x['ng_name'] ?? 'NG')
], $ngCategories ?? [])) ?>;

function escapeHtml(str) {
  return String(str ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function buildNgSelectOptions(selectedId){
  let html = `<option value="0">-- pilih --</option>`;
  NG_CATEGORIES.forEach(c=>{
    const sel = (parseInt(selectedId||0,10) === c.id) ? 'selected' : '';
    html += `<option value="${c.id}" ${sel}>${c.ng_code} - ${escapeHtml(c.ng_name)}</option>`;
  });
  return html;
}

function readNgHidden(key){
  const hidden = document.getElementById('ngHidden_'+key);
  const rows = [];
  if(!hidden) return rows;

  const inputs = hidden.querySelectorAll('input');
  const map = {};
  inputs.forEach(inp=>{
    const m = inp.name.match(/\[ng_details\]\[(\d+)\]\[(ng_category_id|qty)\]/);
    if(!m) return;
    const idx = m[1];
    const field = m[2];
    map[idx] = map[idx] || {};
    map[idx][field] = inp.value;
  });

  Object.keys(map).sort((a,b)=>parseInt(a,10)-parseInt(b,10)).forEach(k=>{
    rows.push({
      ng_category_id: parseInt(map[k].ng_category_id || '0',10),
      qty: parseInt(map[k].qty || '0',10),
    });
  });

  return rows;
}

function writeNgHiddenFromRows(key, rows){
  const hidden = document.getElementById('ngHidden_'+key);
  if(!hidden) return;
  hidden.innerHTML = '';

  rows.forEach((r,i)=>{
    const ngId = parseInt(r.ng_category_id || 0, 10);
    const qty  = parseInt(r.qty || 0, 10);

    const a = document.createElement('input');
    a.type='hidden';
    a.name=`items[${key}][ng_details][${i}][ng_category_id]`;
    a.value=String(isNaN(ngId)?0:ngId);
    hidden.appendChild(a);

    const b = document.createElement('input');
    b.type='hidden';
    b.name=`items[${key}][ng_details][${i}][qty]`;
    b.value=String(isNaN(qty)?0:qty);
    hidden.appendChild(b);
  });
}

function calcTotalNg(rows){
  return rows.reduce((s,r)=>{
    const ngId = parseInt(r.ng_category_id||0,10);
    const qty  = parseInt(r.qty||0,10);
    if(ngId>0 && qty>0) return s + qty;
    return s;
  },0);
}

function updateNgTotalUI(key, total, shiftId){
  const badge = document.getElementById('ngTotalBadge_'+key);
  if(badge) badge.textContent = String(total);

  const ngInp = document.getElementById('ngTotalInput_'+key);
  if(ngInp) {
      ngInp.value = String(total);
      if(shiftId) recalcShiftSummary(shiftId);
  }
}

function renderNgTable(key, shiftId){
  const tbody = document.getElementById('ngBody_'+key);
  if(!tbody) return;

  const rows = readNgHidden(key);
  tbody.innerHTML = '';

  if(rows.length === 0){
    tbody.innerHTML = `<tr><td colspan="4"><div class="ng-empty">Belum ada NG</div></td></tr>`;
    updateNgTotalUI(key, 0, shiftId);
  } else {
    rows.forEach((r, idx)=>{
      const cat = NG_CATEGORIES.find(x=>x.id === (parseInt(r.ng_category_id||0,10))) || null;
      const code = cat ? cat.ng_code : '-';

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="ng-no">${escapeHtml(code)}</td>
        <td>
          <select class="form-select form-select-sm ngSel"
                  data-key="${escapeHtml(key)}"
                  data-shift-id="${shiftId}"
                  data-idx="${idx}">
            ${buildNgSelectOptions(r.ng_category_id)}
          </select>
        </td>
        <td class="ng-qty">
          <input type="number"
                 class="form-control form-control-sm ngQty"
                 min="0"
                 data-key="${escapeHtml(key)}"
                 data-shift-id="${shiftId}"
                 data-idx="${idx}"
                 value="${parseInt(r.qty||0,10)}">
        </td>
        <td class="ng-act">
          <button type="button"
                  class="btn btn-sm btn-danger py-0 px-2 ng-del-btn"
                  onclick="deleteNgRowInline('${escapeHtml(key)}', ${idx}, ${shiftId})">
            <i class="bi bi-x"></i>
          </button>
        </td>
      `;
      tbody.appendChild(tr);
    });

    updateNgTotalUI(key, calcTotalNg(rows), shiftId);
  }

  // Handle slot lock if in Rest state or Not Active
  const inlineDiv = document.querySelector(`.ng-inline[data-key="${key}"]`);
  if (inlineDiv) {
     const td = inlineDiv.closest('td.outer-td');
     if (td && td.classList.contains('slot-locked')) {
         inlineDiv.querySelectorAll('input.ngQty').forEach(el => el.readOnly = true);
         inlineDiv.querySelectorAll('select.ngSel, button.ng-del-btn').forEach(el => el.disabled = true);
     }
  }
}

function addNgRowInline(key, shiftId){
  const rows = readNgHidden(key);
  rows.push({ ng_category_id: 0, qty: 0 });
  writeNgHiddenFromRows(key, rows);
  renderNgTable(key, shiftId);
}

function deleteNgRowInline(key, idx, shiftId){
  const rows = readNgHidden(key);
  rows.splice(idx, 1);
  writeNgHiddenFromRows(key, rows);
  renderNgTable(key, shiftId);
  recalcAll();
}

document.addEventListener('change', function(e){
  const sel = e.target.closest('.ngSel');
  if(!sel) return;

  const key = sel.dataset.key;
  const shiftId = sel.dataset.shiftId;
  const idx = parseInt(sel.dataset.idx||'0',10);

  const rows = readNgHidden(key);
  if(!rows[idx]) return;

  rows[idx].ng_category_id = parseInt(sel.value||'0',10);
  writeNgHiddenFromRows(key, rows);

  const cat = NG_CATEGORIES.find(x=>x.id === rows[idx].ng_category_id) || null;
  const tr = sel.closest('tr');
  const codeTd = tr ? tr.querySelector('.ng-no') : null;
  if(codeTd) codeTd.textContent = cat ? cat.ng_code : '-';

  updateNgTotalUI(key, calcTotalNg(rows), shiftId);
  recalcAll();
});

document.addEventListener('input', function(e){
  const inp = e.target.closest('.ngQty');
  if(!inp) return;

  const key = inp.dataset.key;
  const shiftId = inp.dataset.shiftId;
  const idx = parseInt(inp.dataset.idx||'0',10);

  let v = parseInt(inp.value||'0',10);
  if(isNaN(v) || v < 0) v = 0;
  inp.value = String(v);

  const rows = readNgHidden(key);
  if(!rows[idx]) return;

  rows[idx].qty = v;
  writeNgHiddenFromRows(key, rows);

  updateNgTotalUI(key, calcTotalNg(rows), shiftId);
  recalcAll();
});

/* ========= TIME SLOT LOCK, REST TIME & DYNAMIC TARGET RECALCULATION ========= */
function recalculateTargets(shiftId) {
    const shiftMinutesEl = document.querySelector(`.shift-active-minutes-display[data-shift-id="${shiftId}"]`);
    if (!shiftMinutesEl) return;

    const originalMinutes = parseInt(shiftMinutesEl.dataset.originalMinutes || 0, 10);
    if (originalMinutes <= 0) return;

    let restMinutes = 0;
    const toggles = document.querySelectorAll(`.rest-toggle[data-shift-id="${shiftId}"]`);
    toggles.forEach(t => {
        if (t.checked) {
            restMinutes += parseInt(t.dataset.slotMinutes || 0, 10);
        }
    });

    const activeMinutes = originalMinutes - restMinutes;
    shiftMinutesEl.innerText = `${activeMinutes} Menit`;

    const rows = document.querySelectorAll(`tbody[data-shift-id="${shiftId}"] .item-row`);
    rows.forEach(row => {
        const originalTarget = parseInt(row.dataset.originalTarget || 0, 10);
        
        let newShiftTarget = 0;
        if (activeMinutes > 0) {
            newShiftTarget = Math.round(originalTarget * (activeMinutes / originalMinutes));
        }
        
        const shiftTargetDisplay = row.querySelector('.target-shift-display');
        if (shiftTargetDisplay) shiftTargetDisplay.innerText = newShiftTarget;

        toggles.forEach(t => {
            const slotId = t.dataset.slotId;
            const slotMinutes = parseInt(t.dataset.slotMinutes || 0, 10);
            const slotTargetCell = row.querySelector(`.slot-target-cell[data-slot-id="${slotId}"] .slot-target-display`);
            
            if (slotTargetCell) {
                if (t.checked || activeMinutes <= 0) {
                    slotTargetCell.innerText = '0';
                } else {
                    const newSlotTarget = Math.round(newShiftTarget * (slotMinutes / activeMinutes));
                    slotTargetCell.innerText = newSlotTarget;
                }
            }
        });
    });

    recalcShiftSummary(shiftId);
    calcSlotTotals();
}

/* ========= AUTO CALCULATE SHIFT SUMMARY & EFFICIENCY ========= */
function recalcShiftSummary(shiftId) {
    const tbody = document.querySelector(`tbody[data-shift-id="${shiftId}"]`);
    if(!tbody) return;

    let totalTarget = 0;
    let totalOk = 0;
    let totalNg = 0;

    tbody.querySelectorAll('.target-shift-display').forEach(span => {
        totalTarget += parseInt(span.innerText.trim() || 0, 10);
    });

    tbody.querySelectorAll('.val-ok').forEach(inp => {
        totalOk += parseInt(inp.value || 0, 10);
    });

    tbody.querySelectorAll('.val-ng').forEach(inp => {
        totalNg += parseInt(inp.value || 0, 10);
    });

    const box = document.querySelector(`.summary-box[data-shift-id="${shiftId}"]`);
    if(box) {
        box.querySelector('.total-target').innerText = totalTarget.toLocaleString('id-ID');
        box.querySelector('.total-ok').innerText = totalOk.toLocaleString('id-ID');
        box.querySelector('.total-ng').innerText = totalNg.toLocaleString('id-ID');

        let efficiency = 0;
        if(totalTarget > 0) {
            efficiency = (totalOk / totalTarget) * 100;
        }

        const effSpan = box.querySelector('.eff');
        if(effSpan) {
            effSpan.innerText = efficiency.toFixed(2) + '%';
            effSpan.className = 'eff fw-bold fs-5 ' + (efficiency >= 90 ? 'text-success' : (efficiency >= 75 ? 'text-warning' : 'text-danger'));
        }
    }
}

function parseTimeOnDate(dateISO, hhmmss){
  const t = String(hhmmss || '').slice(0,5);
  return new Date(`${dateISO}T${t}:00`);
}

function isSlotActive(prodDateISO, start, end){
  const now = new Date();
  let s = parseTimeOnDate(prodDateISO, start);
  let e = parseTimeOnDate(prodDateISO, end);

  const startHour = parseInt(String(start).split(':')[0], 10);
  const endHour = parseInt(String(end).split(':')[0], 10);

  if (startHour >= 0 && startHour < 7) s.setDate(s.getDate() + 1);
  if (endHour >= 0 && endHour < 7) e.setDate(e.getDate() + 1);
  if (e <= s) e.setDate(e.getDate() + 1);

  return now >= s && now <= e;
}

function applySlotLock(){
  const prodDateISO = "<?= esc($date) ?>";
  const overrideToggle = document.getElementById('adminOverrideToggle');
  const isAdminOverride = overrideToggle ? overrideToggle.checked : false;

  document.querySelectorAll('th.slot-header').forEach(th => {
     const shiftId = th.dataset.shiftId;
     const slotId = th.dataset.slotId;
     const isCurrentTime = isSlotActive(prodDateISO, th.dataset.start, th.dataset.end);
     
     const restToggle = document.getElementById(`rest_${shiftId}_${slotId}`);
     const isRest = restToggle ? restToggle.checked : false;
     
     const canEdit = !isRest && (isAdminOverride || isCurrentTime);

     th.classList.toggle('slot-header-active', isCurrentTime && !isRest);
     th.classList.toggle('bg-secondary', isRest);
     th.classList.toggle('bg-opacity-25', isRest);

     document.querySelectorAll(`.ok[data-shift-id="${shiftId}"][data-slot-id="${slotId}"]`).forEach(inp => {
         inp.readOnly = !canEdit; 
         
         const tdOk = inp.closest('td');
         if(tdOk) {
             const tdTarget = tdOk.previousElementSibling;
             const tdNg = tdOk.nextElementSibling;
             const tdNgInline = tdNg.nextElementSibling;
             
             [tdTarget, tdOk, tdNg, tdNgInline].forEach(td => {
                 if(td) {
                     td.classList.toggle('slot-active', isCurrentTime && !isRest);
                     td.classList.toggle('slot-locked', !canEdit);
                     td.classList.toggle('slot-rest', isRest);
                 }
             });
         }
     });

     document.querySelectorAll(`.ng-add-btn[data-shift-id="${shiftId}"][data-slot-id="${slotId}"]`).forEach(btn => {
         btn.disabled = !canEdit;
         
         const key = btn.closest('.ng-inline').dataset.key;
         const ngBody = document.getElementById(`ngBody_${key}`);
         if(ngBody) {
             ngBody.querySelectorAll('input.ngQty').forEach(el => el.readOnly = !canEdit);
             ngBody.querySelectorAll('select.ngSel, button.ng-del-btn').forEach(el => el.disabled = !canEdit);
         }
     });
  });
}

document.querySelectorAll('.rest-toggle').forEach(t => {
   const storageKey = `rest_lt_<?= esc($date) ?>_${t.dataset.shiftId}_${t.dataset.slotId}`;
   
   if(localStorage.getItem(storageKey) === '1') {
       t.checked = true;
   }
   
   t.addEventListener('change', function() {
       localStorage.setItem(storageKey, this.checked ? '1' : '0');
       applySlotLock();
       recalculateTargets(this.dataset.shiftId);
   });
});

const adminOverrideEl = document.getElementById('adminOverrideToggle');
if (adminOverrideEl) {
  adminOverrideEl.addEventListener('change', applySlotLock);
}

function calcSlotTotals(){
  document.querySelectorAll('.production-table').forEach(t=>{
    const rows = t.querySelectorAll('tbody.shift-body > tr');
    const slots = t.querySelectorAll('.total-slot-target').length;
    let tg=Array(slots).fill(0), ok=Array(slots).fill(0), ng=Array(slots).fill(0);

    rows.forEach(r=>{
      const cells = r.children;
      for(let i=4; i<cells.length; i+=4){
        const idx = Math.floor((i-4)/4);
        if(idx < slots) {
            tg[idx] += parseInt(cells[i].innerText || 0, 10);
            ok[idx] += parseInt(cells[i+1]?.querySelector('.ok')?.value || 0, 10);
            ng[idx] += parseInt(cells[i+2]?.querySelector('.ng')?.value || 0, 10);
        }
      }
    });

    t.querySelectorAll('.total-slot-target').forEach((c,i)=>c.innerText=tg[i]);
    t.querySelectorAll('.total-slot-ok').forEach((c,i)=>c.innerText=ok[i]);
    t.querySelectorAll('.total-slot-ng').forEach((c,i)=>c.innerText=ng[i]);
    t.querySelectorAll('.total-slot-eff').forEach((c,i)=>{
      c.innerText = tg[i] ? ((ok[i]/tg[i])*100).toFixed(1)+'%' : '0%';
    });
  });
}

function recalcAll() { 
    document.querySelectorAll('.shift-body').forEach(tbody => {
        recalcShiftSummary(tbody.dataset.shiftId);
    });
    calcSlotTotals(); 
}

/* init */
document.querySelectorAll('.ng-inline[data-key]').forEach(box=>{
  const key = box.getAttribute('data-key');
  const btn = box.querySelector('.ng-add-btn');
  const shiftId = btn ? btn.dataset.shiftId : null;
  renderNgTable(key, shiftId);
});

applySlotLock();
document.querySelectorAll('.shift-body').forEach(tbody => {
    recalculateTargets(tbody.dataset.shiftId);
});
setInterval(applySlotLock, 30000);

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('val-ok') || e.target.classList.contains('val-ng')) {
        recalcAll();
    }
});
</script>

<?= $this->endSection() ?>