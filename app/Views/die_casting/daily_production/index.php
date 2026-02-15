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
  min-width:2600px;
  border-collapse:separate !important;
  border-spacing:0 !important;
  table-layout:fixed;
}
.production-table th,
.production-table td{
  font-size:13px;
  padding:10px 10px;
  white-space:nowrap;
  text-align:center;
  vertical-align:middle;
  box-sizing:border-box;
  line-height:1.2;
  background:#fff;
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

.col-machine{ width:120px; min-width:120px; max-width:120px; }
.col-part{ width:320px; min-width:320px; max-width:320px; }
.col-target-shift{ width:140px; min-width:140px; max-width:140px; }

.col-slot-target{ width:90px; min-width:90px; }
.col-slot-fg{ width:90px; min-width:90px; }
.col-slot-ng{ width:90px; min-width:90px; }
.col-slot-remark{ width:320px; min-width:320px; } /* agak lebar karena ada tabel NG */

.sticky-left{ position:sticky; left:0; z-index:40; background:#fff; }
.sticky-left-2{ position:sticky; left:120px; z-index:40; background:#fff; }
.sticky-left-3{ position:sticky; left:440px; z-index:40; background:#fff; }

.th-sticky-left{ z-index:60 !important; background:#f8fafc !important; }

.slot-active{ background:#dcfce7 !important; }
.slot-header-active{ background:#fde68a !important; }

.production-table input.form-control,
.production-table select.form-select{
  min-width:80px;
  padding:6px 8px;
}

/* ===== NG INLINE TABLE (tanpa badge) ===== */
.ng-inline{
  display:flex;
  flex-direction:column;
  gap:8px;
}
.ng-inline-head{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:8px;
}
.ng-inline-head .meta{
  font-size:12px;
  color:#64748b;
  font-weight:700;
}
.ng-mini-table{
  width:100%;
  border-collapse:separate;
  border-spacing:0;
}
.ng-mini-table th, .ng-mini-table td{
  border:1px solid #e5e7eb;
  padding:6px 6px;
  font-size:12px;
  text-align:left;
  background:#fff;
}
.ng-mini-table th{
  background:#f8fafc;
  font-weight:900;
  text-align:center;
}
.ng-mini-table td.ng-no{
  width:60px;
  text-align:center;
  font-weight:900;
}
.ng-mini-table td.ng-qty{
  width:110px;
}
.ng-mini-table td.ng-act{
  width:70px;
  text-align:center;
}
.ng-empty{
  font-size:12px;
  color:#64748b;
  font-weight:700;
  text-align:center;
  padding:8px 0;
  border:1px dashed #cbd5e1;
  border-radius:8px;
}

/* slot terkunci */
.slot-locked{ opacity:.55; }
</style>

<form method="post" action="/die-casting/daily-production/store" id="mainForm">
  <?= csrf_field() ?>

  <?php foreach ($shifts as $shift): ?>
    <?php
      $shiftId = (int)$shift['id'];
      $shiftCode = (int)($shift['shift_code'] ?? 0);
      $finishAllowed = (bool)($shift['finish_allowed'] ?? false);
    ?>

    <div class="d-flex align-items-center justify-content-between gap-2 mt-4 mb-2">
      <h5 class="m-0"><?= esc($shift['shift_name']) ?></h5>

      <!-- <div class="d-flex align-items-center gap-3">
        <button type="button"
                class="btn <?= $finishAllowed ? 'btn-warning' : 'btn-secondary' ?>"
                data-shift-id="<?= $shiftId ?>"
                data-shift-code="<?= $shiftCode ?>"
                onclick="finishShiftPerShift(this)"
                <?= $finishAllowed ? '' : 'disabled' ?>>
          <i class="bi bi-send"></i> Finish Shift <?= $shiftCode ?>
        </button>

        <div class="small text-muted fw-bold">
          <?php if (!$isAdmin): ?>
            Aktif ±15 menit sebelum shift selesai
          <?php else: ?>
            Admin override (bebas finish kapan saja)
          <?php endif; ?>
        </div> -->
      </div>
    </div>

    <div class="table-scroll">
      <table class="production-table table table-sm align-middle">
        <thead>
          <tr class="thead-row-1">
            <th rowspan="2" class="sticky-left col-machine th-sticky-left">Mesin</th>
            <th rowspan="2" class="sticky-left-2 col-part th-sticky-left">Part</th>
            <th rowspan="2" class="sticky-left-3 col-target-shift th-sticky-left">Target<br>Shift</th>

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
              <td class="sticky-left fw-bold text-center">
                <?= esc($item['machine_code']) ?>
              </td>

              <td class="sticky-left-2 text-start fw-bold">
                <?= esc(($item['part_prod'] ?? '')) ?>
                <?php if (!empty($item['part_prod']) && !empty($item['part_name'])): ?>&nbsp;-&nbsp;<?php endif; ?>
                <?= esc(($item['part_name'] ?? '')) ?>
              </td>

              <td class="sticky-left-3 fw-bold text-center target-shift">
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

                $ngDetail = $shift['ng_detail_map']
                  [(int)$item['machine_id']]
                  [(int)$item['product_id']]
                  [(int)$slot['id']] ?? [];

                $key = $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'];
              ?>

                <td class="slot-target fw-bold bg-light text-center">
                  <?= (int)$targetSlot ?>
                </td>

                <td>
                  <input type="number"
                         class="form-control form-control-sm slot-input fg"
                         data-date="<?= esc($date) ?>"
                         data-start="<?= esc($slot['time_start']) ?>"
                         data-end="<?= esc($slot['time_end']) ?>"
                         value="<?= (int)($exist['qty_fg'] ?? 0) ?>"
                         name="items[<?= esc($key) ?>][fg]">
                </td>

                <td>
                  <!-- total NG slot (auto update dari table NG inline) -->
                  <input type="number"
                         class="form-control form-control-sm slot-input ng"
                         readonly
                         id="ngTotalInput_<?= esc($key) ?>"
                         value="<?= (int)($exist['qty_ng'] ?? 0) ?>"
                         name="items[<?= esc($key) ?>][ng]">
                </td>

                <td class="text-start">
                  <div class="ng-inline" data-key="<?= esc($key) ?>">
                    <div class="ng-inline-head">
                      <div class="meta">
                        Total NG: <span class="fw-bold" id="ngTotalBadge_<?= esc($key) ?>">0</span>
                      </div>
                      <button type="button"
                              class="btn btn-sm btn-outline-primary ng-add-btn"
                              onclick="addNgRowInline('<?= esc($key) ?>')"
                              data-start="<?= esc($slot['time_start']) ?>"
                              data-end="<?= esc($slot['time_end']) ?>">
                        + Add NG
                      </button>
                    </div>

                    <div class="table-responsive">
                      <table class="ng-mini-table">
                        <thead>
                          <tr>
                            <th style="width:60px">NG</th>
                            <th>Category</th>
                            <th style="width:110px">Qty</th>
                            <th style="width:70px"></th>
                          </tr>
                        </thead>
                        <tbody id="ngBody_<?= esc($key) ?>">
                          <!-- di-render JS dari hidden -->
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <!-- hidden untuk submit (dipakai controller) -->
                  <div class="ng-hidden d-none" id="ngHidden_<?= esc($key) ?>">
                    <?php foreach ($ngDetail as $idx => $d): ?>
                      <input type="hidden" name="items[<?= esc($key) ?>][ng_details][<?= $idx ?>][ng_category_id]" value="<?= (int)$d['ng_category_id'] ?>">
                      <input type="hidden" name="items[<?= esc($key) ?>][ng_details][<?= $idx ?>][qty]" value="<?= (int)$d['qty'] ?>">
                    <?php endforeach; ?>
                  </div>

                  <input type="hidden" name="items[<?= esc($key) ?>][date]" value="<?= esc($date) ?>">
                  <input type="hidden" name="items[<?= esc($key) ?>][shift_id]" value="<?= (int)$shift['id'] ?>">
                  <input type="hidden" name="items[<?= esc($key) ?>][machine_id]" value="<?= (int)$item['machine_id'] ?>">
                  <input type="hidden" name="items[<?= esc($key) ?>][product_id]" value="<?= (int)$item['product_id'] ?>">
                  <input type="hidden" name="items[<?= esc($key) ?>][time_slot_id]" value="<?= (int)$slot['id'] ?>">
                </td>

              <?php endforeach ?>
            </tr>
          <?php endforeach ?>
        </tbody>

        <tfoot>
          <tr class="fw-bold">
            <td colspan="3" class="text-end">TOTAL / JAM</td>
            <?php foreach ($shift['slots'] as $slot): ?>
              <td class="text-center">0</td>
              <td class="text-center">0</td>
              <td class="text-center">0</td>
              <td class="text-center"></td>
            <?php endforeach ?>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="mt-2 mb-4 p-3 border rounded bg-light">
      <strong>SUMMARY <?= esc($shift['shift_name']) ?> :</strong>
      <span class="ms-3">FG: <span class="total-fg">0</span></span>
      <span class="ms-3">NG: <span class="total-ng">0</span></span>
      <span class="ms-3">Efficiency: <span class="eff">0%</span></span>
    </div>

  <?php endforeach ?>

  <div class="d-flex gap-2 align-items-center mt-3">
    <button class="btn btn-success" id="btnSave" type="submit">
      <i class="bi bi-save"></i> Simpan
    </button>
  </div>
</form>

<script>
const NG_CATEGORIES = <?= json_encode(array_map(fn($x)=>[
  'id'=>(int)$x['id'],
  'ng_code'=>(int)$x['ng_code'],
  'ng_name'=>(string)$x['ng_name']
], $ngCategories)) ?>;

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

  Object.keys(map)
    .sort((a,b)=>parseInt(a,10)-parseInt(b,10))
    .forEach(k=>{
      rows.push({
        ng_category_id: parseInt(map[k].ng_category_id || '0',10),
        qty: parseInt(map[k].qty || '0',10),
      });
    });

  return rows;
}

/* ✅ FIX UTAMA: jangan buang row 0/0 */
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

function updateNgTotalUI(key, total){
  const badge = document.getElementById('ngTotalBadge_'+key);
  if(badge) badge.textContent = String(total);

  const ngInp = document.getElementById('ngTotalInput_'+key);
  if(ngInp) ngInp.value = String(total);
}

function renderNgTable(key){
  const tbody = document.getElementById('ngBody_'+key);
  if(!tbody) return;

  const rows = readNgHidden(key);

  tbody.innerHTML = '';

  if(rows.length === 0){
    tbody.innerHTML = `<tr><td colspan="4"><div class="ng-empty">Belum ada NG</div></td></tr>`;
    updateNgTotalUI(key, 0);
    return;
  }

  rows.forEach((r, idx)=>{
    const cat = NG_CATEGORIES.find(x=>x.id === (parseInt(r.ng_category_id||0,10))) || null;
    const code = cat ? cat.ng_code : '-';

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="ng-no">${escapeHtml(code)}</td>
      <td>
        <select class="form-select form-select-sm ngSel"
                data-key="${escapeHtml(key)}"
                data-idx="${idx}">
          ${buildNgSelectOptions(r.ng_category_id)}
        </select>
      </td>
      <td class="ng-qty">
        <input type="number"
               class="form-control form-control-sm ngQty"
               min="0"
               data-key="${escapeHtml(key)}"
               data-idx="${idx}"
               value="${parseInt(r.qty||0,10)}">
      </td>
      <td class="ng-act">
        <button type="button"
                class="btn btn-sm btn-danger"
                onclick="deleteNgRowInline('${escapeHtml(key)}', ${idx})">
          Del
        </button>
      </td>
    `;
    tbody.appendChild(tr);
  });

  updateNgTotalUI(key, calcTotalNg(rows));
}

function addNgRowInline(key){
  const rows = readNgHidden(key);
  rows.push({ ng_category_id: 0, qty: 0 }); // ✅ row kosong tetap disimpan
  writeNgHiddenFromRows(key, rows);
  renderNgTable(key);
}

function deleteNgRowInline(key, idx){
  const rows = readNgHidden(key);
  rows.splice(idx, 1);
  writeNgHiddenFromRows(key, rows);
  renderNgTable(key);
}

document.addEventListener('change', function(e){
  const sel = e.target.closest('.ngSel');
  if(!sel) return;

  const key = sel.dataset.key;
  const idx = parseInt(sel.dataset.idx||'0',10);

  const rows = readNgHidden(key);
  if(!rows[idx]) return;

  rows[idx].ng_category_id = parseInt(sel.value||'0',10);
  writeNgHiddenFromRows(key, rows);

  const cat = NG_CATEGORIES.find(x=>x.id === rows[idx].ng_category_id) || null;
  const tr = sel.closest('tr');
  const codeTd = tr ? tr.querySelector('.ng-no') : null;
  if(codeTd) codeTd.textContent = cat ? cat.ng_code : '-';

  updateNgTotalUI(key, calcTotalNg(rows));
});

document.addEventListener('input', function(e){
  const inp = e.target.closest('.ngQty');
  if(!inp) return;

  const key = inp.dataset.key;
  const idx = parseInt(inp.dataset.idx||'0',10);

  let v = parseInt(inp.value||'0',10);
  if(isNaN(v) || v < 0) v = 0;
  inp.value = String(v);

  const rows = readNgHidden(key);
  if(!rows[idx]) return;

  rows[idx].qty = v;
  writeNgHiddenFromRows(key, rows);

  updateNgTotalUI(key, calcTotalNg(rows));
});

/* init semua slot NG */
document.querySelectorAll('.ng-inline[data-key]').forEach(box=>{
  const key = box.getAttribute('data-key');
  renderNgTable(key);
});

/* ===== finish shift ===== */
function getCsrfPair() {
  const input = document.querySelector('#mainForm input[type="hidden"][name]');
  if (!input) return null;
  return { name: input.name, value: input.value, el: input };
}

function finishShiftPerShift(btn){
  const shiftId = parseInt(btn.dataset.shiftId || '0', 10);
  const shiftCode = parseInt(btn.dataset.shiftCode || '0', 10);
  if(!shiftId) return;

  if(!confirm(`Finish Shift ${shiftCode}?`)) return;

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
      alert(res.message || 'Berhasil.');
      location.reload();
    }else{
      alert(res.message || 'Gagal');
    }
  })
  .catch(()=> alert('Network error'));
}

/* ========= TIME SLOT LOCK (FG + Add NG hanya aktif di slot berjalan) ========= */
function parseTimeOnDate(dateISO, hhmmss){
  const t = String(hhmmss || '').slice(0,5);
  return new Date(`${dateISO}T${t}:00`);
}
function isSlotActive(prodDateISO, start, end){
  const now = new Date();
  let s = parseTimeOnDate(prodDateISO, start);
  let e = parseTimeOnDate(prodDateISO, end);
  if (e <= s) e.setDate(e.getDate() + 1);
  return now >= s && now <= e;
}

function applySlotLock(){
  const prodDateISO = "<?= esc($date) ?>";

  document.querySelectorAll('input.fg.slot-input').forEach(inp=>{
    const active = isSlotActive(prodDateISO, inp.dataset.start, inp.dataset.end);
    inp.disabled = !active;
    const td = inp.closest('td');
    if(td){
      td.classList.toggle('slot-active', active);
      td.classList.toggle('slot-locked', !active);
    }
  });

  document.querySelectorAll('.ng-add-btn').forEach(btn=>{
    const active = isSlotActive(prodDateISO, btn.dataset.start, btn.dataset.end);
    btn.disabled = !active;
    const td = btn.closest('td');
    if(td) td.classList.toggle('slot-locked', !active);
  });

  document.querySelectorAll('th.slot-header').forEach(th=>{
    th.classList.toggle('slot-header-active', isSlotActive(prodDateISO, th.dataset.start, th.dataset.end));
  });
}

applySlotLock();
setInterval(applySlotLock, 30000);
</script>

<?= $this->endSection() ?>
