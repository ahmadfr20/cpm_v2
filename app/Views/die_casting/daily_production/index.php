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
/* =========================
   LAYOUT TABLE BIAR RAPI
========================= */
.table-scroll{
  overflow:auto;
  position:relative;
  max-width:100%;
  border:1px solid #e5e7eb;
  border-radius:12px;
  background:#fff;
  padding:10px;              /* biar gak mepet */
}

/* table: gunakan separate agar sticky aman */
.production-table{
  width:max-content;
  min-width:2600px;
  border-collapse:separate !important;
  border-spacing:0 !important;
  table-layout:fixed;
}

/* spacing cell */
.production-table th,
.production-table td{
  font-size:13px;
  padding:10px 10px;        /* lebih lega */
  white-space:nowrap;
  text-align:center;
  vertical-align:middle;
  box-sizing:border-box;
  line-height:1.2;
  background:#fff;
}

/* border */
.production-table th,
.production-table td{
  border-right:1px solid #e5e7eb;
  border-bottom:1px solid #e5e7eb;
}
.production-table tr > *:first-child{
  border-left:1px solid #e5e7eb;
}
.production-table thead tr:first-child > *{
  border-top:1px solid #e5e7eb;
}

/* sticky header (2 row) */
.production-table thead tr.thead-row-1 th{
  position:sticky;
  top:0;
  z-index:30;
  background:#f8fafc;
  font-weight:900;
  height:44px;
}
.production-table thead tr.thead-row-2 th{
  position:sticky;
  top:44px;
  z-index:29;
  background:#f1f5f9;
  font-weight:900;
  height:44px;
  font-size:12px;
}

/* kolom kiri */
.col-machine{ width:120px; min-width:120px; max-width:120px; }
.col-part{ width:320px; min-width:320px; max-width:320px; }
.col-target-shift{ width:140px; min-width:140px; max-width:140px; }

/* slot column */
.col-slot-target{ width:90px; min-width:90px; }
.col-slot-fg{ width:90px; min-width:90px; }
.col-slot-ng{ width:90px; min-width:90px; }
.col-slot-remark{ width:260px; min-width:260px; }

/* sticky left */
.sticky-left{ position:sticky; left:0; z-index:40; background:#fff; }
.sticky-left-2{ position:sticky; left:120px; z-index:40; background:#fff; }
.sticky-left-3{ position:sticky; left:440px; z-index:40; background:#fff; } /* 120 + 320 */

/* sticky th left lebih tinggi z */
.th-sticky-left{
  z-index:60 !important;
  background:#f8fafc !important;
}

/* highlight slot aktif */
.slot-active{ background:#dcfce7 !important; }
.slot-header-active{ background:#fde68a !important; }

/* input dan select biar enak */
.production-table input.form-control,
.production-table select.form-select{
  min-width:80px;
  padding:6px 8px;
}

/* NG cell layout */
.ng-box{
  display:flex;
  flex-direction:column;
  gap:8px;
}
.ng-chips{
  display:flex;
  flex-wrap:wrap;
  gap:6px;
}
.ng-chips .badge{
  font-weight:800;
  padding:6px 8px;
  border-radius:999px;
}
.ng-box .btn{
  width:fit-content;
}

/* FINISH BAR */
.shift-headbar{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
}
.finish-meta{
  font-size:12px;
  color:#64748b;
  font-weight:700;
}

/* slot terkunci */
.slot-locked{
  opacity:.55;
}
</style>

<form method="post" action="/die-casting/daily-production/store" id="mainForm">
  <?= csrf_field() ?>

  <?php foreach ($shifts as $shift): ?>
    <?php
      $shiftId = (int)$shift['id'];
      $shiftCode = (int)($shift['shift_code'] ?? 0);
      $finishAllowed = (bool)($shift['finish_allowed'] ?? false);
    ?>

    <div class="shift-headbar mt-4 mb-2">
      <h5 class="m-0"><?= esc($shift['shift_name']) ?></h5>

      <div class="d-flex align-items-center gap-3">
        <button type="button"
                class="btn <?= $finishAllowed ? 'btn-warning' : 'btn-secondary' ?>"
                data-shift-id="<?= $shiftId ?>"
                data-shift-code="<?= $shiftCode ?>"
                onclick="finishShiftPerShift(this)"
                <?= $finishAllowed ? '' : 'disabled' ?>>
          <i class="bi bi-send"></i> Finish Shift <?= $shiftCode ?>
        </button>

        <div class="finish-meta">
          <?php if (!$isAdmin): ?>
            Aktif ±15 menit sebelum shift selesai
          <?php else: ?>
            Admin override (bebas finish kapan saja)
          <?php endif; ?>
        </div>
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
              <td class="sticky-left fw-bold text-center td-sticky-left">
                <?= esc($item['machine_code']) ?>
              </td>

              <td class="sticky-left-2 text-start fw-bold td-sticky-left">
                <?= esc(($item['part_prod'] ?? '')) ?>
                <?php if (!empty($item['part_prod']) && !empty($item['part_name'])): ?>&nbsp;-&nbsp;<?php endif; ?>
                <?= esc(($item['part_name'] ?? '')) ?>
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
                  <input type="number"
                         class="form-control form-control-sm slot-input ng"
                         readonly
                         value="<?= (int)($exist['qty_ng'] ?? 0) ?>"
                         name="items[<?= esc($key) ?>][ng]">
                </td>

                <td class="text-start">
                  <div class="ng-box" data-key="<?= esc($key) ?>">
                    <div class="ng-chips">
                      <?php if (empty($ngDetail)): ?>
                        <span class="badge bg-secondary">-</span>
                      <?php else: ?>
                        <?php foreach ($ngDetail as $d): ?>
                          <span class="badge bg-primary">
                            <?= esc($d['ng_code']) ?> <?= esc($d['ng_name']) ?>: <?= (int)$d['qty'] ?>
                          </span>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </div>

                    <button type="button"
                            class="btn btn-sm btn-outline-primary ng-edit-btn"
                            data-start="<?= esc($slot['time_start']) ?>"
                            data-end="<?= esc($slot['time_end']) ?>"
                            onclick="openNgEditor('<?= esc($key) ?>')">
                      Edit NG
                    </button>
                  </div>

                  <input type="hidden" name="items[<?= esc($key) ?>][date]" value="<?= esc($date) ?>">
                  <input type="hidden" name="items[<?= esc($key) ?>][shift_id]" value="<?= (int)$shift['id'] ?>">
                  <input type="hidden" name="items[<?= esc($key) ?>][machine_id]" value="<?= (int)$item['machine_id'] ?>">
                  <input type="hidden" name="items[<?= esc($key) ?>][product_id]" value="<?= (int)$item['product_id'] ?>">
                  <input type="hidden" name="items[<?= esc($key) ?>][time_slot_id]" value="<?= (int)$slot['id'] ?>">

                  <div class="ng-hidden" id="ngHidden_<?= esc($key) ?>">
                    <?php foreach ($ngDetail as $idx => $d): ?>
                      <input type="hidden" name="items[<?= esc($key) ?>][ng_details][<?= $idx ?>][ng_category_id]" value="<?= (int)$d['ng_category_id'] ?>">
                      <input type="hidden" name="items[<?= esc($key) ?>][ng_details][<?= $idx ?>][qty]" value="<?= (int)$d['qty'] ?>">
                    <?php endforeach; ?>
                  </div>
                </td>

              <?php endforeach ?>
            </tr>
          <?php endforeach ?>
        </tbody>

        <tfoot>
          <tr class="total-slot-row fw-bold">
            <td colspan="3" class="text-end td-sticky-left">TOTAL / JAM</td>
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

    <div class="shift-summary mt-2 mb-4 p-3 border rounded bg-light">
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

<!-- ✅ Modal NG Editor -->
<div class="modal fade" id="ngModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">NG Editor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="ngModalKey" value="">

        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="text-muted">Tambah/hapus kategori NG + qty</div>
          <button type="button" class="btn btn-sm btn-primary" onclick="addNgRow()">
            + Add Row
          </button>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:70px">NG</th>
                <th>NG Category</th>
                <th style="width:140px">Qty</th>
                <th style="width:90px"></th>
              </tr>
            </thead>
            <tbody id="ngRows"></tbody>
          </table>
        </div>

        <div class="mt-2">
          <strong>Total NG:</strong> <span id="ngTotal">0</span>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" onclick="applyNgEditor()">Apply</button>
      </div>
    </div>
  </div>
</div>

<script>
const NG_CATEGORIES = <?= json_encode(array_map(fn($x)=>[
  'id'=>(int)$x['id'],
  'ng_code'=>(int)$x['ng_code'],
  'ng_name'=>(string)$x['ng_name']
], $ngCategories)) ?>;

let ngModal;

/* ===== finish shift ===== */
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

/* ============ NG MODAL ============ */
function openNgEditor(key){
  // kalau tombol disabled, stop
  const btn = document.querySelector(`.ng-box[data-key="${CSS.escape(key)}"] .ng-edit-btn`);
  if(btn && btn.disabled) return;

  if(!ngModal){
    ngModal = new bootstrap.Modal(document.getElementById('ngModal'));
  }
  document.getElementById('ngModalKey').value = key;

  const hidden = document.getElementById('ngHidden_'+key);
  const rows = [];
  if(hidden){
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
    Object.keys(map).forEach(k=>{
      rows.push({
        ng_category_id: parseInt(map[k].ng_category_id || '0',10),
        qty: parseInt(map[k].qty || '0',10)
      });
    });
  }

  renderNgRows(rows);
  calcNgTotal();
  ngModal.show();
}

function renderNgRows(rows){
  const tbody = document.getElementById('ngRows');
  tbody.innerHTML = '';
  if(!rows || rows.length===0){
    rows = [{ng_category_id: 0, qty: 0}];
  }
  rows.forEach(r=> appendNgRow(r.ng_category_id, r.qty));
}

function addNgRow(){ appendNgRow(0,0); }

function appendNgRow(selectedId, qty){
  const tbody = document.getElementById('ngRows');
  const tr = document.createElement('tr');

  const ngCell = document.createElement('td');
  ngCell.className = 'text-center fw-bold ngCodeCell';
  ngCell.textContent = '-';

  const catCell = document.createElement('td');
  const sel = document.createElement('select');
  sel.className = 'form-select form-select-sm ngCatSel';
  const opt0 = document.createElement('option');
  opt0.value = '0'; opt0.textContent='-- pilih --';
  sel.appendChild(opt0);
  NG_CATEGORIES.forEach(c=>{
    const o = document.createElement('option');
    o.value = c.id;
    o.textContent = `${c.ng_code} - ${c.ng_name}`;
    if(c.id === selectedId) o.selected = true;
    sel.appendChild(o);
  });
  sel.addEventListener('change', ()=>{
    const v = parseInt(sel.value||'0',10);
    const c = NG_CATEGORIES.find(x=>x.id===v);
    ngCell.textContent = c ? c.ng_code : '-';
    calcNgTotal();
  });
  catCell.appendChild(sel);

  const qtyCell = document.createElement('td');
  const inp = document.createElement('input');
  inp.type='number';
  inp.className='form-control form-control-sm ngQtyInp';
  inp.value = qty || 0;
  inp.addEventListener('input', calcNgTotal);
  qtyCell.appendChild(inp);

  const actCell = document.createElement('td');
  const del = document.createElement('button');
  del.type='button';
  del.className='btn btn-sm btn-danger';
  del.textContent='Hapus';
  del.onclick=()=>{ tr.remove(); calcNgTotal(); };
  actCell.appendChild(del);

  tr.appendChild(ngCell);
  tr.appendChild(catCell);
  tr.appendChild(qtyCell);
  tr.appendChild(actCell);
  tbody.appendChild(tr);

  const initId = parseInt(sel.value||'0',10);
  const initCat = NG_CATEGORIES.find(x=>x.id===initId);
  ngCell.textContent = initCat ? initCat.ng_code : '-';
}

function calcNgTotal(){
  let total = 0;
  document.querySelectorAll('#ngRows tr').forEach(tr=>{
    const qty = parseInt(tr.querySelector('.ngQtyInp')?.value || '0',10);
    total += qty > 0 ? qty : 0;
  });
  document.getElementById('ngTotal').textContent = total;
}

function applyNgEditor(){
  const key = document.getElementById('ngModalKey').value;
  if(!key) return;

  const details = [];
  document.querySelectorAll('#ngRows tr').forEach(tr=>{
    const ngId = parseInt(tr.querySelector('.ngCatSel')?.value || '0',10);
    const qty  = parseInt(tr.querySelector('.ngQtyInp')?.value || '0',10);
    if(ngId>0 && qty>0) details.push({ng_category_id: ngId, qty});
  });

  const hidden = document.getElementById('ngHidden_'+key);
  hidden.innerHTML = '';
  details.forEach((d,i)=>{
    const a = document.createElement('input');
    a.type='hidden';
    a.name=`items[${key}][ng_details][${i}][ng_category_id]`;
    a.value=d.ng_category_id;
    hidden.appendChild(a);

    const b = document.createElement('input');
    b.type='hidden';
    b.name=`items[${key}][ng_details][${i}][qty]`;
    b.value=d.qty;
    hidden.appendChild(b);
  });

  const box = document.querySelector(`.ng-box[data-key="${CSS.escape(key)}"] .ng-chips`);
  box.innerHTML = '';
  if(details.length===0){
    const span = document.createElement('span');
    span.className='badge bg-secondary';
    span.textContent='-';
    box.appendChild(span);
  }else{
    details.forEach(d=>{
      const c = NG_CATEGORIES.find(x=>x.id===d.ng_category_id);
      const span = document.createElement('span');
      span.className='badge bg-primary';
      span.textContent = `${c?.ng_code || '-'} ${c?.ng_name || ''}: ${d.qty}`;
      box.appendChild(span);
    });
  }

  const total = details.reduce((s,x)=>s+(x.qty||0),0);
  const ngInput = document.querySelector(`input[name="items[${CSS.escape(key)}][ng]"]`);
  if(ngInput) ngInput.value = total;

  ngModal.hide();
}

/* ===== helper csrf ===== */
function getCsrfPair() {
  const input = document.querySelector('#mainForm input[type="hidden"][name]');
  if (!input) return null;
  return { name: input.name, value: input.value, el: input };
}

/* ========= TIME SLOT LOCK (FG/NG editor hanya aktif di slot berjalan) ========= */
function pad2(n){ return String(n).padStart(2,'0'); }
function parseTimeOnDate(dateISO, hhmmss){
  const t = String(hhmmss || '').slice(0,5);
  return new Date(`${dateISO}T${t}:00`);
}
function isSlotActive(prodDateISO, start, end){
  const now = new Date();
  let s = parseTimeOnDate(prodDateISO, start);
  let e = parseTimeOnDate(prodDateISO, end);
  if (e <= s) e.setDate(e.getDate() + 1); // handle lewat midnight
  return now >= s && now <= e;
}

function applySlotLock(){
  const prodDateISO = "<?= esc($date) ?>";

  // FG input hanya aktif di slot berjalan
  document.querySelectorAll('input.fg.slot-input').forEach(inp=>{
    const start = inp.dataset.start;
    const end = inp.dataset.end;
    const active = isSlotActive(prodDateISO, start, end);

    inp.disabled = !active;

    const td = inp.closest('td');
    if(td){
      td.classList.toggle('slot-active', active);
      td.classList.toggle('slot-locked', !active);
    }
  });

  // tombol NG editor hanya aktif di slot berjalan
  document.querySelectorAll('.ng-edit-btn').forEach(btn=>{
    const start = btn.dataset.start;
    const end = btn.dataset.end;
    const active = isSlotActive(prodDateISO, start, end);

    btn.disabled = !active;

    const td = btn.closest('td');
    if(td){
      td.classList.toggle('slot-locked', !active);
    }
  });

  // header slot highlight
  document.querySelectorAll('th.slot-header').forEach(th=>{
    const start = th.dataset.start;
    const end = th.dataset.end;
    th.classList.toggle('slot-header-active', isSlotActive(prodDateISO, start, end));
  });
}

applySlotLock();
setInterval(applySlotLock, 30000);
</script>

<?= $this->endSection() ?>
