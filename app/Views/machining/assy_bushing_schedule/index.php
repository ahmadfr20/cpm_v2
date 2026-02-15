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
</style>

<h4 class="mb-3">DAILY PRODUCTION SCHEDULE – ASSY BUSHING</h4>

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

<?php foreach ($shifts as $shift): ?>
  <hr>
  <h5 class="mt-4 mb-3"><?= esc($shift['shift_name']) ?> – Assy Bushing</h5>

  <form method="post" action="/machining/assy-bushing/schedule/store" class="ab-form">
    <?= csrf_field() ?>
    <input type="hidden" name="date" value="<?= esc($date) ?>">

    <table class="table table-bordered table-sm align-middle text-center">
      <thead class="table-secondary">
        <tr>
          <th style="width:60px">Line</th>
          <th style="width:120px">Kode Mesin</th>
          <th>Mesin</th>
          <th style="width:380px">Part</th>
          <th style="width:90px">CT (AB)</th>
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
            <!-- ✅ sesuai controller store: target_per_shift -->
            <input type="number"
                   class="form-control form-control-sm text-center plan-input"
                   name="items[<?= $idx ?>][target_per_shift]"
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
      <i class="bi bi-save"></i> Simpan <?= esc($shift['shift_name']) ?> – Assy Bushing
    </button>
  </form>
<?php endforeach ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
const productUrl   = "/machining/assy-bushing/schedule/product-target";
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

function setStockBadge(row, stockVal){
  const badge = row.querySelector('.stock-badge');
  const v = parseInt(stockVal || '0', 10);
  badge.dataset.val = String(v);
  badge.textContent = v.toLocaleString('id-ID');
  badge.classList.toggle('zero', v <= 0);
}

async function loadProducts(selectEl) {
  const machineId  = selectEl.dataset.machine;
  const shiftId    = selectEl.dataset.shift;
  const selectedId = selectEl.dataset.selected;

  try {
    const url = `${productUrl}?machine_id=${encodeURIComponent(machineId)}&shift_id=${encodeURIComponent(shiftId)}&date=${encodeURIComponent(scheduleDate)}`;
    const res = await fetch(url, { headers: { 'Accept': 'application/json' }});
    const data = await res.json();

    selectEl.innerHTML = '<option value=""></option>';

    (data || []).forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = `${p.part_no} - ${p.part_name}`;

      opt.dataset.ct = p.cycle_time_used || '';
      opt.dataset.targetShift = p.target_per_shift || 0;
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
    console.error("Gagal load product assy bushing", e);
    initSelect2(selectEl);
  }
}

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

$(document).on('change', '.product-select', function() {
  const selectEl = this;
  const opt = selectEl.selectedOptions[0];
  const row = selectEl.closest('tr');

  const ctEl   = row.querySelector('.cycle-time');
  const planEl = row.querySelector('.plan-input');

  if (!opt || !selectEl.value) {
    if (ctEl) ctEl.value = '';
    if (planEl) planEl.removeAttribute('data-stock-prev');
    setStockBadge(row, 0);
    return;
  }

  ctEl.value = opt.dataset.ct || '';

  const stockPrev = parseInt(opt.dataset.stockPrev || '0', 10);
  planEl.dataset.stockPrev = String(stockPrev);
  setStockBadge(row, stockPrev);

  if (stockPrev <= 0) {
    alert('Stock kosong pada proses sebelumnya. Tidak bisa scheduling product ini.');
    planEl.value = 0;
    return;
  }

  if (!planEl.value || planEl.value == 0) {
    planEl.value = parseInt(opt.dataset.targetShift || '0', 10);
  }

  validatePlanAgainstStock(row, true);
});

$(document).on('input', '.plan-input', function() {
  const row = this.closest('tr');
  validatePlanAgainstStock(row, true);
});

$(document).on('submit', '.ab-form', function(e){
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

document.querySelectorAll('.product-select').forEach(selectEl => {
  initSelect2(selectEl);
  loadProducts(selectEl);
});
</script>

<?= $this->endSection() ?>
