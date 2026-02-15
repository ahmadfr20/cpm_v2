<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
  .select2-container .select2-selection--single {
    height: 31px;
    padding: 2px 6px;
    border: 1px solid #ced4da;
    border-radius: .2rem;
  }
  .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 26px;
    padding-left: 2px;
  }
  .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 29px;
  }

  .badge-stock {
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 999px;
    background: #eef2ff;
    color: #1e3a8a;
    font-weight: 700;
  }
  .badge-stock.zero{
    background: #fee2e2;
    color: #991b1b;
  }

  #assignIncomingAssyShaftModal .modal-dialog { margin-top: 80px; }
  #incomingTableAssyShaft th, #incomingTableAssyShaft td { vertical-align: middle; }
  .incoming-mini { font-size: 12px; color: #6c757d; }
</style>

<h4 class="mb-3">DAILY PRODUCTION SCHEDULE – ASSY SHAFT</h4>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<!-- ✅ optional: alert awaiting incoming (tetap) -->
<div class="alert alert-warning d-none align-items-center justify-content-between gap-2"
     id="awaitingWipAsAlert"
     role="alert">
  <div>
    <div class="fw-bold">
      <i class="bi bi-exclamation-triangle-fill"></i>
      Ada WIP Awaiting untuk Assy Shaft!
    </div>
    <div class="small">
      Tanggal <span class="fw-bold" id="awaitingWipAsDate"><?= esc($date) ?></span> —
      Total item: <span class="fw-bold" id="awaitingWipAsCount">0</span>,
      Total qty: <span class="fw-bold" id="awaitingWipAsQty">0</span>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="button" class="btn btn-outline-dark btn-sm" id="btnRefreshAwaitingWipAs">
      <i class="bi bi-arrow-repeat"></i> Refresh
    </button>

    <button type="button" class="btn btn-success btn-sm" onclick="openAssignIncomingAssyShaftModal()">
      <i class="bi bi-box-arrow-in-down"></i> Assign Sekarang
    </button>
  </div>
</div>

<div class="mb-3">
  <button type="button" class="btn btn-primary btn-sm" onclick="openAssignIncomingAssyShaftModal()">
    <i class="bi bi-plus-circle"></i> Ambil Incoming dari Proses Sebelumnya
  </button>
</div>

<form method="get" class="mb-3" style="max-width:220px">
  <label class="form-label fw-bold">Tanggal</label>
  <input type="date"
         name="date"
         value="<?= esc($date) ?>"
         class="form-control"
         onchange="this.form.submit()">
</form>

<?php foreach ($shifts as $shift): ?>
  <hr>
  <h5 class="mt-4 mb-3"><?= esc($shift['shift_name']) ?></h5>

  <form method="post" action="/machining/assy-shaft/schedule/store" class="as-form">
    <?= csrf_field() ?>
    <input type="hidden" name="date" value="<?= esc($date) ?>">

    <table class="table table-bordered table-sm align-middle text-center">
      <thead class="table-secondary">
        <tr>
          <th style="width:60px">Line</th>
          <th style="width:120px">Alamat Mesin</th>
          <th>Tipe Mesin</th>
          <th style="width:380px">Part</th>
          <th style="width:90px">CT (AS)</th>
          <th style="width:140px">Stock Prev</th>
          <th style="width:130px">Planning</th>
          <th style="width:80px">Actual</th>
        </tr>
      </thead>

      <tbody>
      <?php foreach ($machines as $idx => $machine):

          $keyPlan = $shift['id'].'_'.$machine['id'];
          $plan    = $planMap[$keyPlan] ?? null;

          $actKey  = $shift['id'].'_'.$machine['id'].'_'.($plan['product_id'] ?? 0);
          $actual  = $actualMap[$actKey]['act'] ?? 0;
      ?>
        <tr>
          <td><?= esc($machine['line_position']) ?></td>

          <td class="fw-bold text-primary">
            <?= esc($machine['machine_code']) ?>
          </td>

          <td class="text-start">
            <?= esc($machine['machine_name']) ?>
          </td>

          <td class="text-start">
            <select class="form-select form-select-sm product-select w-100"
                    data-machine="<?= (int)$machine['id'] ?>"
                    data-shift="<?= (int)$shift['id'] ?>"
                    data-selected="<?= esc($plan['product_id'] ?? '') ?>"
                    name="items[<?= $idx ?>][product_id]">
              <option value="">-- pilih part --</option>
            </select>
          </td>

          <td>
            <input type="text"
                   class="form-control form-control-sm text-center cycle-time"
                   value="<?= esc($plan['cycle_time'] ?? '') ?>"
                   readonly>
          </td>

          <td class="text-center">
            <span class="badge-stock stock-badge" data-val="0">0</span>
          </td>

          <td>
            <input type="number"
                   class="form-control form-control-sm text-center plan-input"
                   name="items[<?= $idx ?>][plan]"
                   min="0"
                   max="1200"
                   value="<?= esc($plan['target_per_shift'] ?? '') ?>">
          </td>

          <td>
            <input type="text"
                   class="form-control form-control-sm text-center"
                   value="<?= esc($actual) ?>"
                   readonly>
          </td>

          <input type="hidden" name="items[<?= $idx ?>][machine_id]" value="<?= (int)$machine['id'] ?>">
          <input type="hidden" name="items[<?= $idx ?>][shift_id]" value="<?= (int)$shift['id'] ?>">
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>

    <button class="btn btn-success btn-sm mb-4">
      <i class="bi bi-save"></i> Simpan <?= esc($shift['shift_name']) ?>
    </button>
  </form>
<?php endforeach ?>

<!-- =========================
 * MODAL INCOMING (TABLE) - Assy Shaft (tetap)
 * ========================= -->
<div class="modal fade" id="assignIncomingAssyShaftModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Incoming dari Proses Sebelumnya → Assign ke Assy Shaft</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">

        <div class="row g-2 align-items-end mb-2">
          <div class="col-md-4">
            <label class="form-label fw-bold">Shift (tujuan schedule)</label>
            <select class="form-select form-select-sm" id="as_shift_bulk">
              <?php foreach ($shifts as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= esc($s['shift_name']) ?></option>
              <?php endforeach ?>
            </select>
            <div class="incoming-mini">Shift dipakai untuk semua baris yang di-assign.</div>
          </div>

          <div class="col-md-8 text-end">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllIncomingAs(true)">
              Pilih Semua
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllIncomingAs(false)">
              Uncheck Semua
            </button>

            <button type="button" class="btn btn-success btn-sm" onclick="submitAssignIncomingAssyShaftBulk()">
              <i class="bi bi-check2-circle"></i> Assign Selected
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-sm align-middle" id="incomingTableAssyShaft">
            <thead class="table-secondary">
              <tr class="text-center">
                <th style="width:50px">Pick</th>
                <th style="width:90px">WIP ID</th>
                <th>Part</th>
                <th style="width:140px">Avail</th>
                <th style="width:220px">Pilih Mesin</th>
                <th style="width:140px">Qty Assign</th>
                <th style="width:140px">Aksi</th>
              </tr>
            </thead>
            <tbody id="incomingTbodyAssyShaft">
              <tr>
                <td colspan="7" class="text-center text-muted">Klik tombol “Ambil Incoming…” untuk memuat data.</td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>

    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
const productUrl   = "/machining/assy-shaft/schedule/product-target";
const scheduleDate = "<?= esc($date) ?>";

/** init select2 */
function initSelect2(selectEl) {
  const $el = $(selectEl);
  if ($el.data('select2')) return;

  $el.select2({
    width: '100%',
    placeholder: '-- pilih part --',
    allowClear: true,
    dropdownAutoWidth: true
  });
}

/** update badge stock */
function setStockBadge(row, stockVal){
  const badge = row.querySelector('.stock-badge');
  const v = parseInt(stockVal || '0', 10);
  badge.dataset.val = String(v);
  badge.textContent = v.toLocaleString('id-ID');
  badge.classList.toggle('zero', v <= 0);
}

/** load options per select */
async function loadProducts(selectEl) {
  const machineId  = selectEl.dataset.machine;
  const shiftId    = selectEl.dataset.shift;
  const selectedId = selectEl.dataset.selected;

  try {
    const res  = await fetch(`${productUrl}?machine_id=${machineId}&shift_id=${shiftId}&date=${encodeURIComponent(scheduleDate)}`);
    const data = await res.json();

    selectEl.innerHTML = '<option value=""></option>';

    data.forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = `${p.part_no} - ${p.part_name}`;

      // ✅ ct yg dipakai controller
      opt.dataset.ct = p.cycle_time_used || '';

      // target default
      opt.dataset.targetShift = p.target_per_shift || 0;

      // ✅ stock prev + prev process
      opt.dataset.stockPrev = p.stock_prev || 0;
      opt.dataset.prevProcessId = p.prev_process_id || 0;

      selectEl.appendChild(opt);
    });

    initSelect2(selectEl);

    if (selectedId) {
      $(selectEl).val(String(selectedId)).trigger('change');
    } else {
      $(selectEl).trigger('change');
    }

  } catch (e) {
    console.error("Gagal load product assy shaft", e);
    initSelect2(selectEl);
  }
}

/** validasi plan tidak boleh > stock prev */
function validatePlanAgainstStock(row, showAlert = true) {
  const selectEl = row.querySelector('.product-select');
  const planEl   = row.querySelector('.plan-input');

  if (!selectEl || !planEl) return true;
  if (!selectEl.value) return true;

  const opt = selectEl.selectedOptions[0];
  const stockPrev = parseInt(planEl.dataset.stockPrev || opt.dataset.stockPrev || '0', 10);
  let v = parseInt(planEl.value || '0', 10);

  if (stockPrev <= 0 && v > 0) {
    if (showAlert) alert('Stock kosong pada proses sebelumnya. Tidak bisa scheduling.');
    planEl.value = 0;
    return false;
  }

  if (v > stockPrev) {
    if (showAlert) alert(`Scheduling tidak boleh melebihi stock yang tersedia (${stockPrev}).`);
    planEl.value = stockPrev;
    return false;
  }

  return true;
}

/** on change product */
$(document).on('change', '.product-select', function() {
  const selectEl = this;
  const opt = selectEl.selectedOptions[0];
  const row = selectEl.closest('tr');

  const ctEl   = row.querySelector('.cycle-time');
  const planEl = row.querySelector('.plan-input');

  // clear
  if (!opt || !selectEl.value) {
    if (ctEl) ctEl.value = '';
    if (planEl) planEl.removeAttribute('data-stock-prev');
    setStockBadge(row, 0);
    return;
  }

  // set CT
  ctEl.value = opt.dataset.ct || '';

  // set stock prev into plan
  const stockPrev = parseInt(opt.dataset.stockPrev || '0', 10);
  planEl.dataset.stockPrev = String(stockPrev);

  // show stock badge
  setStockBadge(row, stockPrev);

  // stock kosong
  if (stockPrev <= 0) {
    alert('Stock kosong pada proses sebelumnya. Tidak bisa scheduling product ini.');
    planEl.value = 0;
    return;
  }

  // default plan kalau kosong
  if (!planEl.value || planEl.value == 0) {
    planEl.value = parseInt(opt.dataset.targetShift || '0', 10);
  }

  // cap kalau melebihi stock
  validatePlanAgainstStock(row, true);
});

/** on input plan */
$(document).on('input', '.plan-input', function() {
  const row = this.closest('tr');
  validatePlanAgainstStock(row, true);
});

/** validasi sebelum submit */
$(document).on('submit', '.as-form', function(e){
  const rows = this.querySelectorAll('tbody tr');
  for (const row of rows) {
    const ok = validatePlanAgainstStock(row, true);
    if (!ok) {
      e.preventDefault();
      return false;
    }
  }
  return true;
});

// init all selects
document.querySelectorAll('.product-select').forEach(selectEl => {
  initSelect2(selectEl);
  loadProducts(selectEl);
});

/* =========================
 * MODAL INCOMING (TABLE) - Assy Shaft
 * ========================= */
let __incomingAssyShaft = [];

// pakai mesin yang sama (list dari PHP machines)
const __machinesAssyShaft = <?= json_encode(array_map(fn($m) => [
  'id'   => (int)$m['id'],
  'code' => (string)$m['machine_code'],
  'name' => (string)$m['machine_name'],
], $machines)) ?>;

function escapeHtml(str) {
  return String(str ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function getCsrfPair() {
  const inputs = document.querySelectorAll('input[type="hidden"][name]');
  for (const el of inputs) {
    if (el.value && el.value.length >= 10) return { name: el.name, value: el.value };
  }
  return null;
}

function renderMachineOptionsAssyShaft(selectedId = '') {
  let html = `<option value="">-- pilih mesin --</option>`;
  __machinesAssyShaft.forEach(m => {
    const sel = (String(m.id) === String(selectedId)) ? 'selected' : '';
    html += `<option value="${m.id}" ${sel}>${escapeHtml(m.code)} - ${escapeHtml(m.name)}</option>`;
  });
  return html;
}

async function openAssignIncomingAssyShaftModal() {
  if (typeof bootstrap === 'undefined') {
    alert('Bootstrap JS belum ter-load. Pastikan layout/layout include bootstrap.bundle.min.js');
    return;
  }

  const date = scheduleDate;
  const tbody = document.getElementById('incomingTbodyAssyShaft');
  tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">Loading incoming...</td></tr>`;

  const res = await fetch(`/machining/assy-shaft/schedule/incoming-wip?date=${encodeURIComponent(date)}`, {
    headers: { 'Accept': 'application/json' }
  });

  const json = await res.json();

  if (!json.status) {
    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">${escapeHtml(json.message || 'Gagal load incoming')}</td></tr>`;
    const modalEl = document.getElementById('assignIncomingAssyShaftModal');
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    return;
  }

  __incomingAssyShaft = json.data || [];

  if (__incomingAssyShaft.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">Tidak ada incoming WIP (WAITING) untuk tanggal ini.</td></tr>`;
  } else {
    tbody.innerHTML = '';
    __incomingAssyShaft.forEach((p, idx) => {
      const avail = parseInt(p.avail || 0, 10);
      const rowId = `as_inc_${idx}`;

      tbody.insertAdjacentHTML('beforeend', `
        <tr id="${rowId}">
          <td class="text-center">
            <input type="checkbox" class="form-check-input as-inc-check" checked>
          </td>

          <td class="text-center">
            <span class="badge bg-secondary">${escapeHtml(p.wip_id)}</span>
            <input type="hidden" class="as-inc-wip-id" value="${escapeHtml(p.wip_id)}">
            <input type="hidden" class="as-inc-product-id" value="${escapeHtml(p.product_id)}">
          </td>

          <td class="text-start">
            <div><strong>${escapeHtml(p.part_no)}</strong> - ${escapeHtml(p.part_name)}</div>
            <div class="incoming-mini">Product ID: ${escapeHtml(p.product_id)}</div>
          </td>

          <td class="text-center">
            <span class="badge bg-info text-dark">${avail}</span>
            <input type="hidden" class="as-inc-avail" value="${avail}">
          </td>

          <td>
            <select class="form-select form-select-sm as-inc-machine">
              ${renderMachineOptionsAssyShaft('')}
            </select>
          </td>

          <td>
            <input type="number" class="form-control form-control-sm as-inc-qty"
                   min="1" max="${avail}" value="${avail}">
          </td>

          <td class="text-center">
            <button type="button" class="btn btn-success btn-sm" onclick="submitAssignIncomingAssyShaftRow('${rowId}')">
              Assign
            </button>
          </td>
        </tr>
      `);
    });
  }

  const modalEl = document.getElementById('assignIncomingAssyShaftModal');
  bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

function toggleAllIncomingAs(flag) {
  document.querySelectorAll('.as-inc-check').forEach(ch => ch.checked = !!flag);
}

async function submitAssignIncomingAssyShaftRow(rowId) {
  const row = document.getElementById(rowId);
  if (!row) return;

  const date = scheduleDate;
  const shiftId = document.getElementById('as_shift_bulk').value;

  const wipId = row.querySelector('.as-inc-wip-id').value;
  const productId = row.querySelector('.as-inc-product-id').value;
  const avail = parseInt(row.querySelector('.as-inc-avail').value || '0', 10);

  const machineId = row.querySelector('.as-inc-machine').value;
  const qty = parseInt(row.querySelector('.as-inc-qty').value || '0', 10);

  if (!shiftId || !machineId || !wipId || !productId || qty <= 0) {
    alert('Shift / Mesin / Qty wajib diisi.');
    return;
  }
  if (qty > avail) {
    alert(`Qty melebihi available (${avail}).`);
    return;
  }

  row.style.opacity = 0.6;
  row.querySelectorAll('input,select,button').forEach(el => el.disabled = true);

  const csrf = getCsrfPair();
  const payload = new URLSearchParams({
    date: date,
    shift_id: shiftId,
    machine_id: machineId,
    product_id: productId,
    qty: String(qty),
    wip_id: String(wipId),
  });
  if (csrf) payload.append(csrf.name, csrf.value);

  try {
    const res = await fetch('/machining/assy-shaft/schedule/assign-incoming-wip', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      body: payload
    });

    const json = await res.json();

    if (!json.status) {
      alert(json.message || 'Assign gagal');
      row.style.opacity = 1;
      row.querySelectorAll('input,select,button').forEach(el => el.disabled = false);
      return;
    }

    row.classList.add('table-success');
    row.querySelector('td:last-child').innerHTML = `<span class="badge bg-success">Assigned</span>`;

  } catch (e) {
    alert('Server error / jaringan bermasalah');
    row.style.opacity = 1;
    row.querySelectorAll('input,select,button').forEach(el => el.disabled = false);
  }
}

async function submitAssignIncomingAssyShaftBulk() {
  const rows = Array.from(document.querySelectorAll('#incomingTbodyAssyShaft tr'));
  const selectedRows = rows.filter(r => r.querySelector('.as-inc-check') && r.querySelector('.as-inc-check').checked);

  if (selectedRows.length === 0) {
    alert('Tidak ada baris yang dipilih.');
    return;
  }

  for (const r of selectedRows) {
    if (r.classList.contains('table-success')) continue;
    await submitAssignIncomingAssyShaftRow(r.getAttribute('id'));
  }

  alert('Proses assign selected selesai. (Baris sukses akan berwarna hijau)');
}

/* =========================
 * ✅ ALERT CHECKER (Awaiting WIP Assy Shaft)
 * ========================= */
async function checkAwaitingWipAssyShaft() {
  const date = scheduleDate;
  const alertEl = document.getElementById('awaitingWipAsAlert');
  const countEl = document.getElementById('awaitingWipAsCount');
  const qtyEl   = document.getElementById('awaitingWipAsQty');

  if (!alertEl || !countEl || !qtyEl) return;

  try {
    const res = await fetch(`/machining/assy-shaft/schedule/incoming-wip?date=${encodeURIComponent(date)}`, {
      headers: { 'Accept': 'application/json' }
    });

    if (!res.ok) {
      alertEl.classList.add('d-none');
      alertEl.classList.remove('d-flex');
      return;
    }

    const json = await res.json();
    if (!json.status) {
      alertEl.classList.add('d-none');
      alertEl.classList.remove('d-flex');
      return;
    }

    const data = json.data || [];
    if (data.length === 0) {
      alertEl.classList.add('d-none');
      alertEl.classList.remove('d-flex');
      return;
    }

    let totalQty = 0;
    data.forEach(x => {
      const q = parseInt(x.avail ?? 0, 10);
      if (!isNaN(q)) totalQty += q;
    });

    countEl.textContent = String(data.length);
    qtyEl.textContent   = totalQty.toLocaleString('id-ID');

    alertEl.classList.remove('d-none');
    alertEl.classList.add('d-flex');

  } catch (e) {
    console.error('checkAwaitingWipAssyShaft error', e);
    alertEl.classList.add('d-none');
    alertEl.classList.remove('d-flex');
  }
}

document.getElementById('btnRefreshAwaitingWipAs')?.addEventListener('click', () => {
  checkAwaitingWipAssyShaft();
});
checkAwaitingWipAssyShaft();
</script>

<?= $this->endSection() ?>
