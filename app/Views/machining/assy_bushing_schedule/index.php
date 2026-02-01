<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
  #assignIncomingAssyBushingModal .modal-dialog { margin-top: 80px; }
  #incomingTableAssy th, #incomingTableAssy td { vertical-align: middle; }
  .incoming-mini { font-size: 12px; color: #6c757d; }
</style>

<h4 class="mb-3">DAILY PRODUCTION SCHEDULE – ASSY BUSHING</h4>

<!-- ✅ ALERT: Ada Awaiting WIP Assy Bushing -->
<div class="alert alert-warning d-none align-items-center justify-content-between gap-2"
     id="awaitingWipAbAlert"
     role="alert">
  <div>
    <div class="fw-bold">
      <i class="bi bi-exclamation-triangle-fill"></i>
      Ada WIP Awaiting untuk Assy Bushing!
    </div>
    <div class="small">
      Tanggal <span class="fw-bold" id="awaitingWipAbDate"><?= esc($date) ?></span> —
      Total item: <span class="fw-bold" id="awaitingWipAbCount">0</span>,
      Total qty: <span class="fw-bold" id="awaitingWipAbQty">0</span>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="button" class="btn btn-outline-dark btn-sm" id="btnRefreshAwaitingWipAb">
      <i class="bi bi-arrow-repeat"></i> Refresh
    </button>

    <button type="button" class="btn btn-success btn-sm" onclick="openAssignIncomingAssyBushingModal()">
      <i class="bi bi-box-arrow-in-down"></i> Assign Sekarang
    </button>
  </div>
</div>

<form method="get" class="mb-3" style="max-width:220px">
  <label class="form-label fw-bold">Tanggal</label>
  <input type="date" name="date" value="<?= esc($date) ?>" class="form-control" onchange="this.form.submit()">
</form>

<div class="mb-3">
  <button type="button" class="btn btn-primary btn-sm" onclick="openAssignIncomingAssyBushingModal()">
    <i class="bi bi-plus-circle"></i> Ambil Incoming dari Proses Sebelumnya
  </button>
</div>

<?php foreach ($shifts as $shift): ?>
  <hr>
  <h5 class="mt-4 mb-3"><?= esc($shift['shift_name']) ?> – Assy Bushing</h5>

  <form method="post" action="/machining/assy-bushing/schedule/store">
    <?= csrf_field() ?>
    <input type="hidden" name="date" value="<?= esc($date) ?>">

    <table class="table table-bordered table-sm align-middle text-center">
      <thead class="table-secondary">
        <tr>
          <th style="width:60px">Line</th>
          <th style="width:120px">Kode Mesin</th>
          <th>Mesin</th>
          <th style="width:260px">Part</th>
          <th style="width:80px">CT</th>
          <th style="width:120px">Planning</th>
          <th style="width:80px">Actual</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($machines as $idx => $machine):

        $keyPlan = $shift['id'].'_'.$machine['id'];
        $plan    = $planMap[$keyPlan] ?? null;

        $actual  = 0;
        if (!empty($plan['product_id'])) {
          $actKey  = $shift['id'].'_'.$machine['id'].'_'.$plan['product_id'];
          $actual  = $actualMap[$actKey]['act'] ?? 0;
        }
      ?>
        <tr>
          <td><?= esc($machine['line_position']) ?></td>
          <td class="fw-bold text-primary"><?= esc($machine['machine_code']) ?></td>
          <td class="text-start"><?= esc($machine['machine_name']) ?></td>

          <td>
            <select class="form-select form-select-sm product-select"
                    data-machine="<?= (int)$machine['id'] ?>"
                    data-shift="<?= (int)$shift['id'] ?>"
                    data-selected="<?= esc($plan['product_id'] ?? '') ?>"
                    name="items[<?= $idx ?>][product_id]">
              <option value="">Loading...</option>
            </select>
          </td>

          <td>
            <input type="text" class="form-control form-control-sm text-center cycle-time"
                   value="<?= esc($plan['cycle_time'] ?? '') ?>" readonly>
          </td>

          <td>
            <!-- ✅ name sesuai controller Assy Bushing: target_per_shift -->
            <input type="number" class="form-control form-control-sm text-center plan-input"
                   name="items[<?= $idx ?>][target_per_shift]" max="1200"
                   value="<?= esc($plan['target_per_shift'] ?? '') ?>">
          </td>

          <td>
            <input type="text" class="form-control form-control-sm text-center"
                   value="<?= esc($actual) ?>" readonly>
          </td>

          <input type="hidden" name="items[<?= $idx ?>][machine_id]" value="<?= (int)$machine['id'] ?>">
          <input type="hidden" name="items[<?= $idx ?>][shift_id]" value="<?= (int)$shift['id'] ?>">
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>

    <button type="submit" class="btn btn-success btn-sm mb-4">
      <i class="bi bi-save"></i> Simpan <?= esc($shift['shift_name']) ?> – Assy Bushing
    </button>
  </form>
<?php endforeach ?>

<!-- ✅ MODAL INCOMING - TABLE (SAMA PERSIS SEPERTI LEAK TEST) -->
<div class="modal fade" id="assignIncomingAssyBushingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Incoming dari Proses Sebelumnya → Assign ke Assy Bushing</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">

        <div class="row g-2 align-items-end mb-2">
          <div class="col-md-4">
            <label class="form-label fw-bold">Shift (tujuan schedule)</label>
            <select class="form-select form-select-sm" id="ab_shift_bulk">
              <?php foreach ($shifts as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= esc($s['shift_name']) ?></option>
              <?php endforeach ?>
            </select>
            <div class="incoming-mini">Shift dipakai untuk semua baris yang di-assign.</div>
          </div>

          <div class="col-md-8 text-end">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllIncomingAb(true)">
              Pilih Semua
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllIncomingAb(false)">
              Uncheck Semua
            </button>

            <button type="button" class="btn btn-success btn-sm" onclick="submitAssignIncomingAssyBushingBulk()">
              <i class="bi bi-check2-circle"></i> Assign Selected
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-sm align-middle" id="incomingTableAssy">
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
            <tbody id="incomingTbodyAssy">
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

<script>
/* =========================
 * LOAD PRODUCT untuk schedule table (Assy Bushing)
 * ========================= */
async function loadProducts(selectEl) {
  const machineId  = selectEl.dataset.machine;
  const shiftId    = selectEl.dataset.shift;
  const selectedId = selectEl.dataset.selected;

  const url =
    `/machining/assy-bushing/schedule/product-target` +
    `?machine_id=${encodeURIComponent(machineId)}` +
    `&shift_id=${encodeURIComponent(shiftId)}`;

  selectEl.innerHTML = '<option value="">Loading...</option>';

  try {
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    if (!res.ok) {
      selectEl.innerHTML = `<option value="">Gagal load (${res.status})</option>`;
      return;
    }

    const data = await res.json();

    selectEl.innerHTML = '<option value="">-- pilih part --</option>';

    if (!Array.isArray(data) || data.length === 0) {
      selectEl.innerHTML = `<option value="">(Tidak ada part untuk Assy Bushing)</option>`;
      return;
    }

    data.forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = `${p.part_no} - ${p.part_name}`;
      opt.dataset.ct = p.cycle_time ?? '';
      opt.dataset.target = p.target ?? 0;
      selectEl.appendChild(opt);
    });

    if (selectedId) selectEl.value = String(selectedId);
    selectEl.dispatchEvent(new Event('change'));

  } catch (e) {
    console.error(e);
    selectEl.innerHTML = '<option value="">Error load part</option>';
  }
}

document.querySelectorAll('.product-select').forEach(selectEl => {
  loadProducts(selectEl);

  selectEl.addEventListener('change', () => {
    const opt = selectEl.selectedOptions[0];
    const row = selectEl.closest('tr');
    if (!row) return;

    if (!opt || !selectEl.value) {
      row.querySelector('.cycle-time').value = '';
      return;
    }

    row.querySelector('.cycle-time').value = opt.dataset.ct || '';
    row.querySelector('.plan-input').value = opt.dataset.target || 0;
  });
});

/* =========================
 * MODAL INCOMING (TABLE) - Assy Bushing
 * ========================= */
let __incomingAssy = [];
const __machines = <?= json_encode(array_map(fn($m) => [
  'id' => (int)$m['id'],
  'code' => (string)$m['machine_code'],
  'name' => (string)$m['machine_name'],
], $machines)) ?>;

function getCsrfPair() {
  // cari input csrf pertama di halaman
  const inputs = document.querySelectorAll('input[type="hidden"][name]');
  for (const el of inputs) {
    // CI4 csrf name biasanya random, value hash
    if (el.value && el.value.length >= 10) {
      return { name: el.name, value: el.value };
    }
  }
  return null;
}

function renderMachineOptions(selectedId = '') {
  let html = `<option value="">-- pilih mesin --</option>`;
  __machines.forEach(m => {
    const sel = (String(m.id) === String(selectedId)) ? 'selected' : '';
    html += `<option value="${m.id}" ${sel}>${escapeHtml(m.code)} - ${escapeHtml(m.name)}</option>`;
  });
  return html;
}

async function openAssignIncomingAssyBushingModal() {
  if (typeof bootstrap === 'undefined') {
    alert('Bootstrap JS belum ter-load. Pastikan layout/layout include bootstrap.bundle.min.js');
    return;
  }

  const date = "<?= esc($date) ?>";
  const tbody = document.getElementById('incomingTbodyAssy');
  tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">Loading incoming...</td></tr>`;

  const res = await fetch(`/machining/assy-bushing/schedule/incoming-wip?date=${encodeURIComponent(date)}`, {
    headers: { 'Accept': 'application/json' }
  });

  const json = await res.json();

  if (!json.status) {
    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">${escapeHtml(json.message || 'Gagal load incoming')}</td></tr>`;
    const modalEl = document.getElementById('assignIncomingAssyBushingModal');
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    return;
  }

  __incomingAssy = json.data || [];

  if (__incomingAssy.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">Tidak ada incoming WIP (WAITING) untuk tanggal ini.</td></tr>`;
  } else {
    tbody.innerHTML = '';
    __incomingAssy.forEach((p, idx) => {
      const avail = parseInt(p.avail || 0, 10);
      const rowId = `ab_inc_${idx}`;

      tbody.insertAdjacentHTML('beforeend', `
        <tr id="${rowId}">
          <td class="text-center">
            <input type="checkbox" class="form-check-input ab-inc-check" checked>
          </td>

          <td class="text-center">
            <span class="badge bg-secondary">${escapeHtml(p.wip_id)}</span>
            <input type="hidden" class="ab-inc-wip-id" value="${escapeHtml(p.wip_id)}">
            <input type="hidden" class="ab-inc-product-id" value="${escapeHtml(p.product_id)}">
          </td>

          <td class="text-start">
            <div><strong>${escapeHtml(p.part_no)}</strong> - ${escapeHtml(p.part_name)}</div>
            <div class="incoming-mini">Product ID: ${escapeHtml(p.product_id)}</div>
          </td>

          <td class="text-center">
            <span class="badge bg-info text-dark">${avail}</span>
            <input type="hidden" class="ab-inc-avail" value="${avail}">
          </td>

          <td>
            <select class="form-select form-select-sm ab-inc-machine">
              ${renderMachineOptions('')}
            </select>
          </td>

          <td>
            <input type="number" class="form-control form-control-sm ab-inc-qty"
                   min="1" max="${avail}" value="${avail}">
          </td>

          <td class="text-center">
            <button type="button" class="btn btn-success btn-sm" onclick="submitAssignIncomingAssyBushingRow('${rowId}')">
              Assign
            </button>
          </td>
        </tr>
      `);
    });
  }

  const modalEl = document.getElementById('assignIncomingAssyBushingModal');
  bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

function toggleAllIncomingAb(flag) {
  document.querySelectorAll('.ab-inc-check').forEach(ch => ch.checked = !!flag);
}

async function submitAssignIncomingAssyBushingRow(rowId) {
  const row = document.getElementById(rowId);
  if (!row) return;

  const date = "<?= esc($date) ?>";
  const shiftId = document.getElementById('ab_shift_bulk').value;

  const wipId = row.querySelector('.ab-inc-wip-id').value;
  const productId = row.querySelector('.ab-inc-product-id').value;
  const avail = parseInt(row.querySelector('.ab-inc-avail').value || '0', 10);

  const machineId = row.querySelector('.ab-inc-machine').value;
  const qty = parseInt(row.querySelector('.ab-inc-qty').value || '0', 10);

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
    const res = await fetch('/machining/assy-bushing/schedule/assign-incoming-wip', {
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

async function submitAssignIncomingAssyBushingBulk() {
  const rows = Array.from(document.querySelectorAll('#incomingTbodyAssy tr'));
  const selectedRows = rows.filter(r => r.querySelector('.ab-inc-check') && r.querySelector('.ab-inc-check').checked);

  if (selectedRows.length === 0) {
    alert('Tidak ada baris yang dipilih.');
    return;
  }

  for (const r of selectedRows) {
    if (r.classList.contains('table-success')) continue;
    const id = r.getAttribute('id');
    await submitAssignIncomingAssyBushingRow(id);
  }

  alert('Proses assign selected selesai. (Baris sukses akan berwarna hijau)');
}

/* =========================
 * ✅ ALERT CHECKER (Awaiting WIP Assy Bushing)
 * ========================= */
async function checkAwaitingWipAssyBushing() {
  const date = "<?= esc($date) ?>";
  const alertEl = document.getElementById('awaitingWipAbAlert');
  const countEl = document.getElementById('awaitingWipAbCount');
  const qtyEl   = document.getElementById('awaitingWipAbQty');

  if (!alertEl || !countEl || !qtyEl) return;

  try {
    const res = await fetch(`/machining/assy-bushing/schedule/incoming-wip?date=${encodeURIComponent(date)}`, {
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
    console.error('checkAwaitingWipAssyBushing error', e);
    alertEl.classList.add('d-none');
    alertEl.classList.remove('d-flex');
  }
}

document.getElementById('btnRefreshAwaitingWipAb')?.addEventListener('click', () => {
  checkAwaitingWipAssyBushing();
});

checkAwaitingWipAssyBushing();

/* =========================
 * UTIL
 * ========================= */
function escapeHtml(str) {
  return String(str ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}
</script>

<?= $this->endSection() ?>
