<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
  /* biar Select2 tinggi sama dengan form-select-sm bootstrap */
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

    /* Modal turun agar tidak ketutup navbar */
  .modal-dialog{
    margin-top: 80px !important; /* sesuaikan tinggi navbar */
  }

  /* kalau layar kecil, jangan terlalu turun */
  @media (max-width: 576px){
    .modal-dialog{
      margin-top: 60px !important;
    }
  }

  /* biar isi modal bisa discroll */
  .modal-body{
    max-height: calc(100vh - 220px);
    overflow-y: auto;
  }
</style>

<h4 class="mb-3">DAILY PRODUCTION SCHEDULE – MACHINING</h4>

<div class="alert alert-warning d-none align-items-center justify-content-between gap-2"
     id="awaitingWipAlert"
     role="alert">
  <div>
    <div class="fw-bold">
      <i class="bi bi-exclamation-triangle-fill"></i>
      Ada WIP Awaiting untuk Machining!
    </div>
    <div class="small">
      Tanggal <span class="fw-bold" id="awaitingWipDate"><?= esc($date) ?></span> —
      Total item: <span class="fw-bold" id="awaitingWipCount">0</span>,
      Total qty: <span class="fw-bold" id="awaitingWipQty">0</span>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="button" class="btn btn-outline-dark btn-sm" id="btnRefreshAwaitingWip">
      <i class="bi bi-arrow-repeat"></i> Refresh
    </button>

    <button type="button" class="btn btn-success btn-sm" onclick="openAssignWipModal()">
      <i class="bi bi-box-arrow-in-down"></i> Assign Sekarang
    </button>
  </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<form method="get" class="mb-3" style="max-width:220px">
  <label class="form-label fw-bold">Tanggal</label>
  <input type="date"
         name="date"
         value="<?= esc($date) ?>"
         class="form-control"
         onchange="this.form.submit()">
</form>

<div class="mb-3">
  <a href="/machining/daily-schedule/result?date=<?= esc($date) ?>"
     class="btn btn-outline-primary btn-sm">
    <i class="bi bi-graph-up"></i> Lihat Hasil & Efektivitas
  </a>
</div>

<div class="mb-3 d-flex gap-2">
  <button type="button" class="btn btn-primary btn-sm" onclick="openAssignWipModal()">
    <i class="bi bi-plus-circle"></i> Assign Produk dari Finish Shift (Prev → Machining)
  </button>
</div>


<?php foreach ($shifts as $shift): ?>
  <hr>
  <h5 class="mt-4 mb-3"><?= esc($shift['shift_name']) ?></h5>

  <form method="post" action="/machining/daily-schedule/store">
    <?= csrf_field() ?>
    <input type="hidden" name="date" value="<?= esc($date) ?>">

    <table class="table table-bordered table-sm align-middle text-center">
      <thead class="table-secondary">
        <tr>
          <th style="width:60px">Line</th>
          <th style="width:120px">Alamat Mesin</th>
          <th>Tipe Mesin</th>
          <th style="width:360px">Part</th>
          <th style="width:80px">CT</th>
          <th style="width:120px">Planning</th>
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
            <select class="form-select form-select-sm product-select js-product-select w-100"
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

          <td>
            <input type="number"
                   class="form-control form-control-sm text-center plan-input"
                   name="items[<?= $idx ?>][plan]"
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

<div class="modal fade" id="assignWipModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Assign Produk Incoming (Prev → Machining)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="row g-2 align-items-end mb-3">

          <div class="col-md-4">
            <label class="form-label fw-bold">Shift</label>
            <select class="form-select form-select-sm" id="aw_shift">
              <?php foreach ($shifts as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= esc($s['shift_name']) ?></option>
              <?php endforeach ?>
            </select>
          </div>

          <div class="col-md-8 d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="fillAllQty()">
              Assign All (qty = available)
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="fillAllMachineFirst()">
              Isi Mesin (pakai mesin pertama)
            </button>
            <button type="button" class="btn btn-primary btn-sm" onclick="reloadIncomingWip()">
              Refresh
            </button>
          </div>
        </div>

        <div class="alert alert-info py-2 mb-2">
          Pilih <b>mesin per produk</b> lalu isi qty yang mau di-assign.
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-sm align-middle">
            <thead class="table-secondary">
              <tr>
                <th style="width:60px">No</th>
                <th style="width:180px">Part No</th>
                <th>Part Name</th>
                <th style="width:140px" class="text-end">Available</th>
                <th style="width:260px">Mesin</th>
                <th style="width:160px">Qty Assign</th>
              </tr>
            </thead>
            <tbody id="aw_table_body">
              <tr>
                <td colspan="6" class="text-center text-muted">Klik tombol Assign untuk load data…</td>
              </tr>
            </tbody>
          </table>
        </div>

        <small class="text-muted" id="aw_hint"></small>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-success btn-sm" onclick="submitAssignWipBulkPerProduct()">
          <i class="bi bi-check2-circle"></i> Assign ke Schedule
        </button>
      </div>
    </div>
  </div>
</div>



<!-- jQuery + Select2 JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
const productUrl = "/machining/daily-schedule/product-target";

/** init select2 untuk satu select */
function initSelect2(selectEl) {
  const $el = $(selectEl);
  if ($el.data('select2')) return; // sudah init

  $el.select2({
    width: '100%',
    placeholder: '-- pilih part --',
    allowClear: true,
    dropdownAutoWidth: true
  });
}

/** load options dari server, lalu set selected dan trigger change */
async function loadProducts(selectEl) {
  const machineId  = selectEl.dataset.machine;
  const shiftId    = selectEl.dataset.shift;
  const selectedId = selectEl.dataset.selected;

  try {
    const res  = await fetch(`${productUrl}?machine_id=${machineId}&shift_id=${shiftId}`);
    const data = await res.json();

    // reset option
    selectEl.innerHTML = '<option value=""></option>'; // untuk allowClear & placeholder select2

    data.forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = `${p.part_no} - ${p.part_name}`;
      opt.dataset.ct = p.cycle_time || '';
      opt.dataset.targetShift = p.target_per_shift || 0;
      selectEl.appendChild(opt);
    });

    // init select2 setelah options ada
    initSelect2(selectEl);

    // set selected dari DB (kalau ada)
    if (selectedId) {
      $(selectEl).val(String(selectedId)).trigger('change');
    } else {
      // trigger change supaya CT/plan bisa reset
      $(selectEl).trigger('change');
    }

  } catch (e) {
    console.error("Gagal load product machining", e);
    initSelect2(selectEl);
  }
}

// event change (select2 tetap menembakkan 'change')
$(document).on('change', '.product-select', function() {
  const selectEl = this;
  const opt = selectEl.selectedOptions[0];
  const row = selectEl.closest('tr');

  // kalau kosong (clear)
  if (!opt || !selectEl.value) {
    row.querySelector('.cycle-time').value = '';
    // plan tidak dipaksa kosong supaya user tidak kehilangan input manual
    return;
  }

  // set CT
  row.querySelector('.cycle-time').value = opt.dataset.ct || '';

  // set Plan default (kalau kosong/0)
  const planEl = row.querySelector('.plan-input');
  if (!planEl.value || planEl.value == 0) {
    planEl.value = opt.dataset.targetShift || 0;
  }
});

// init semua select + load data
document.querySelectorAll('.product-select').forEach(selectEl => {
  initSelect2(selectEl);     // biar langsung bisa search (meskipun option masih loading)
  loadProducts(selectEl);    // load options dari server
});

const __machines = <?= json_encode(array_map(function($m){
  return [
    'id' => (int)$m['id'],
    'text' => $m['machine_code'].' - '.$m['machine_name']
  ];
}, $machines)); ?>;

let __incomingWip = [];

function getCsrfPair() {
  const input = document.querySelector('input[type="hidden"][name]');
  if (!input) return null;
  return { name: input.name, value: input.value };
}

async function openAssignWipModal() {
  await reloadIncomingWip();
  const modal = new bootstrap.Modal(document.getElementById('assignWipModal'));
  modal.show();
}

async function reloadIncomingWip() {
  const date = "<?= esc($date) ?>";
  const body = document.getElementById('aw_table_body');
  const hint = document.getElementById('aw_hint');

  body.innerHTML = `<tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>`;
  hint.innerText = '';

  const res  = await fetch(`/machining/daily-schedule/incoming-wip?date=${date}`);
  const json = await res.json();

  if (!json.status) {
    body.innerHTML = `<tr><td colspan="6" class="text-center text-danger">${json.message || 'Gagal load data'}</td></tr>`;
    return;
  }

  __incomingWip = json.data || [];

  if (__incomingWip.length === 0) {
    body.innerHTML = `<tr><td colspan="6" class="text-center text-muted">Tidak ada incoming WIP (WAITING) untuk tanggal ini.</td></tr>`;
    return;
  }

  body.innerHTML = '';
  __incomingWip.forEach((p, idx) => {
    const tr = document.createElement('tr');

    // build machine select options
    let optHtml = `<option value="">-- pilih mesin --</option>`;
    (__machines || []).forEach(m => {
      optHtml += `<option value="${m.id}">${escapeHtml(m.text)}</option>`;
    });

    tr.innerHTML = `
      <td class="text-center">${idx + 1}</td>
      <td>${escapeHtml(p.part_no)}</td>
      <td>${escapeHtml(p.part_name)}</td>
      <td class="text-end fw-bold">${Number(p.qty || 0).toLocaleString()}</td>

      <td>
        <select class="form-select form-select-sm aw-machine"
                data-product-id="${p.product_id}">
          ${optHtml}
        </select>
      </td>

      <td>
        <input type="number"
               class="form-control form-control-sm aw-qty"
               data-product-id="${p.product_id}"
               data-avail="${p.qty}"
               min="0"
               max="${p.qty}"
               value="0">
      </td>
    `;

    body.appendChild(tr);
  });

  hint.innerText = 'Isi qty > 0 pada produk yang ingin kamu assign + pilih mesin per produk.';
}

function fillAllQty() {
  document.querySelectorAll('.aw-qty').forEach(inp => {
    const avail = parseInt(inp.dataset.avail || '0', 10);
    inp.value = avail > 0 ? avail : 0;
  });
}

function fillAllMachineFirst() {
  const firstMachineId = (__machines && __machines[0]) ? String(__machines[0].id) : '';
  if (!firstMachineId) return;

  document.querySelectorAll('.aw-machine').forEach(sel => {
    if (!sel.value) sel.value = firstMachineId;
  });
}

function escapeHtml(str) {
  return String(str ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

async function submitAssignWipBulkPerProduct() {
  const date    = "<?= esc($date) ?>";
  const shiftId = document.getElementById('aw_shift').value;

  if (!shiftId) {
    alert('Shift wajib dipilih.');
    return;
  }

  // kumpulkan per baris: product_id, machine_id, qty
  const items = [];

  // mapping machine selection by product_id
  const machineMap = {};
  document.querySelectorAll('.aw-machine').forEach(sel => {
    const pid = parseInt(sel.dataset.productId || '0', 10);
    machineMap[pid] = parseInt(sel.value || '0', 10);
  });

  document.querySelectorAll('.aw-qty').forEach(inp => {
    const productId = parseInt(inp.dataset.productId || '0', 10);
    const avail     = parseInt(inp.dataset.avail || '0', 10);
    const qty       = parseInt(inp.value || '0', 10);
    const machineId = machineMap[productId] || 0;

    if (qty > 0) {
      if (qty > avail) {
        throw new Error(`Qty product ${productId} melebihi available (${avail})`);
      }
      if (machineId <= 0) {
        throw new Error(`Mesin belum dipilih untuk product ${productId}`);
      }
      items.push({ product_id: productId, machine_id: machineId, qty });
    }
  });

  if (items.length === 0) {
    alert('Tidak ada qty yang di-assign. Isi qty minimal 1 pada salah satu produk.');
    return;
  }

  const csrf = getCsrfPair();

  const payload = new URLSearchParams();
  payload.append('date', date);
  payload.append('shift_id', String(shiftId));
  payload.append('items', JSON.stringify(items));
  if (csrf) payload.append(csrf.name, csrf.value);

  const res  = await fetch('/machining/daily-schedule/assign-incoming-wip-bulk', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: payload
  });

  const json = await res.json();

  if (!json.status) {
    alert(json.message || 'Assign gagal');
    return;
  }

  alert('Assign berhasil. Schedule akan diperbarui.');
  location.reload();
}

async function checkAwaitingWipMachining() {
  const date = "<?= esc($date) ?>";
  const alertEl = document.getElementById('awaitingWipAlert');
  const countEl = document.getElementById('awaitingWipCount');
  const qtyEl   = document.getElementById('awaitingWipQty');

  if (!alertEl || !countEl || !qtyEl) return;

  try {
    const res = await fetch(`/machining/daily-schedule/incoming-wip?date=${encodeURIComponent(date)}`, {
      headers: { 'Accept': 'application/json' }
    });

    if (!res.ok) {
      // kalau endpoint error, jangan ganggu UI (tetap hidden)
      alertEl.classList.add('d-none');
      return;
    }

    const json = await res.json();

    if (!json.status) {
      alertEl.classList.add('d-none');
      return;
    }

    const data = json.data || [];
    if (data.length === 0) {
      alertEl.classList.add('d-none');
      return;
    }

    // hitung total qty (qty atau avail)
    let totalQty = 0;
    data.forEach(x => {
      const q = parseInt(x.qty ?? x.avail ?? 0, 10);
      if (!isNaN(q)) totalQty += q;
    });

    countEl.textContent = String(data.length);
    qtyEl.textContent   = totalQty.toLocaleString('id-ID');

    // tampilkan alert
    alertEl.classList.remove('d-none');
    alertEl.classList.add('d-flex');

  } catch (e) {
    console.error('checkAwaitingWipMachining error', e);
    alertEl.classList.add('d-none');
  }
}

// tombol refresh
document.getElementById('btnRefreshAwaitingWip')?.addEventListener('click', () => {
  checkAwaitingWipMachining();
});

// auto check saat load
checkAwaitingWipMachining();
</script>

<?= $this->endSection() ?>
