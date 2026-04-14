<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
  .select2-container .select2-selection--single {
    height: 34px;
    padding: 3px 6px;
    border: 1px solid #ced4da;
    border-radius: .25rem;
  }
  .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 26px;
    padding-left: 2px;
  }
  .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 32px;
  }

  .badge-stock {
    font-size: 13px;
    padding: 5px 10px;
    border-radius: 6px;
    background: #eef2ff;
    color: #1e3a8a;
    font-weight: 700;
    display: inline-block;
  }
  .badge-stock.zero {
    background: #fee2e2;
    color: #991b1b;
  }
  
  .ng-badge {
    font-size: 11px;
    padding: 4px 6px;
    margin-top: 5px;
    display: inline-block;
    cursor: help;
  }
</style>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 gap-2">
  <div>
      <h4 class="mb-0 text-dark fw-bold">DAILY SCHEDULE – MACHINING</h4>
<div class="d-flex justify-content-end mb-3 gap-2 d-print-none">
    <button type="button" class="btn btn-outline-success btn-sm fw-bold" onclick="exportGenericExcel()">
        <i class="bi bi-file-earmark-excel"></i> Export Excel
    </button>
    <button type="button" class="btn btn-outline-danger btn-sm fw-bold" onclick="window.print()">
        <i class="bi bi-printer"></i> Print / PDF
    </button>
</div>
      <small class="text-muted">Mengambil data dari WIP Transfer Machining</small>
  </div>
  <div>
      <a href="<?= base_url('machining/daily-schedule/inventory?date='.$date) ?>" class="btn btn-primary fw-bold btn-sm rounded-pill px-3">
        <i class="bi bi-box-seam me-1"></i> Stock Area MC
      </a>
  </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success shadow-sm"><i class="bi bi-check-circle-fill me-2"></i><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="get" class="row align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold text-muted small">PILIH TANGGAL JADWAL</label>
                <input type="date" name="date" value="<?= esc($date) ?>" class="form-control form-control-sm" onchange="this.form.submit()">
            </div>

            <?php if (isset($isAdmin) && $isAdmin): ?>
            <div class="col-md-4 ms-auto text-end">
                <div class="form-check form-switch d-inline-block text-start" style="transform: scale(1.1); transform-origin: right;">
                    <input class="form-check-input bg-danger border-danger" type="checkbox" id="bypassStockToggle" style="cursor: pointer;">
                    <label class="form-check-label fw-bold text-danger ms-1" for="bypassStockToggle" style="cursor: pointer;">
                        <i class="bi bi-unlock-fill"></i> Admin: Bypass Validasi Stok
                    </label>
                </div>
            </div>
            <?php endif; ?>
            </form>
    </div>
</div>

<form method="post" action="/machining/daily-schedule/store" class="mc-form">
    <?= csrf_field() ?>
    <input type="hidden" name="date" value="<?= esc($date) ?>">

    <?php foreach ($shifts as $shift): ?>
      <div class="card shadow-sm border-0 mb-4">
          <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
              <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-clock-history me-2"></i><?= esc($shift['shift_name']) ?></h5>
          </div>
          
          <div class="card-body bg-light border-bottom py-2">
             <div class="d-flex align-items-center gap-3">
                 <label class="fw-bold mb-0 text-dark"><i class="bi bi-stop-circle"></i> Waktu Berakhir Shift:</label>
                 <div style="min-width: 250px;">
                     <select class="form-select form-select-sm shift-end-select" name="shift_end_slots[<?= $shift['id'] ?>]" data-shift="<?= (int)$shift['id'] ?>">
                        <?php 
                          $savedEndId = $shiftEndSlots[$shift['id']] ?? null;
                          $lastSlotId = !empty($shiftSlots[$shift['id']]) ? end($shiftSlots[$shift['id']])['time_slot_id'] : null;
                          $activeEndId = $savedEndId ?: $lastSlotId;
                        ?>
                        <?php foreach ($shiftSlots[$shift['id']] as $slot): ?>
                          <?php $sel = ($activeEndId == $slot['time_slot_id']) ? 'selected' : ''; ?>
                          <option value="<?= $slot['time_slot_id'] ?>" data-seconds="<?= $slot['seconds'] ?>" <?= $sel ?>><?= $slot['label'] ?></option>
                        <?php endforeach; ?>
                     </select>
                 </div>
                 <div class="ms-auto fs-6 text-end">
                     <span class="text-muted">Waktu Produksi:</span> 
                     <span class="shift-net-minutes text-primary fw-bold fs-5 ms-1" data-shift="<?= (int)$shift['id'] ?>" data-net="0">
                         0 Menit
                     </span>
                 </div>
             </div>
          </div>

          <div class="card-body p-0">
              <div class="table-responsive">
                  <table class="table table-hover table-bordered mb-0 align-middle text-center" id="table-shift-<?= $shift['id'] ?>">
                    <thead class="table-light">
                      <tr>
                        <th style="width:60px">Line</th>
                        <th style="width:160px">Mesin</th>
                        <th style="width:350px" class="text-start">Part Details</th>
                        <th style="width:80px">CT</th>
                        <th style="width:130px">Stock Ready</th>
                        <th style="width:130px" class="text-primary">Planning</th>
                        <th style="width:90px">Actual</th>
                        <th style="min-width:140px;">Dandori & Waktu</th>
                        <th style="width:60px">Hapus</th>
                      </tr>
                    </thead>

                    <tbody id="tbody-shift-<?= $shift['id'] ?>">
                    <?php 
                    foreach ($machines as $m):
                      $rows = $planMap[$shift['id']][$m['id']] ?? [[]]; 
                      
                      foreach ($rows as $plan):
                          $uuid = uniqid('row_'); 
                          $prodId = $plan['product_id'] ?? '';
                          $actKey  = $shift['id'].'_'.$m['id'].'_'.$prodId;
                          $actual  = $actualMap[$actKey]['act'] ?? 0;
                          
                          // Ambil data dandori dari map
                          $dandoriData = $dandoriMap[$shift['id']][$m['id']][$prodId] ?? null;
                          $isDandori = $dandoriData !== null;
                          $dandoriTimeSlotId = $isDandori ? $dandoriData : '';
                    ?>
                      <tr class="schedule-row" data-shift="<?= (int)$shift['id'] ?>">
                        <td class="text-muted fw-bold td-line"><?= esc($m['line_position']) ?></td>

                        <td>
                          <select class="form-select form-select-sm machine-select fw-bold" name="items[<?= $uuid ?>][machine_id]">
                            <?php foreach ($machines as $mach): ?>
                              <option value="<?= $mach['id'] ?>" data-line="<?= esc($mach['line_position']) ?>" <?= ($mach['id'] == $m['id']) ? 'selected' : '' ?>>
                                <?= esc($mach['line_position'] . ' - ' . $mach['machine_code']) ?>
                              </option>
                            <?php endforeach ?>
                          </select>
                        </td>

                        <td class="text-start">
                          <select class="form-select form-select-sm product-select w-100"
                                  data-machine="<?= (int)$m['id'] ?>"
                                  data-shift="<?= (int)$shift['id'] ?>"
                                  data-selected="<?= esc($prodId) ?>"
                                  name="items[<?= $uuid ?>][product_id]">
                            <option value="">-- Cari Part Number --</option>
                          </select>
                        </td>

                        <td>
                          <input type="text" class="form-control form-control-sm text-center cycle-time border-0 bg-transparent" value="<?= esc($plan['cycle_time'] ?? '') ?>" readonly>
                        </td>

                        <td class="text-center">
                          <div class="stock-container">
                              <span class="badge-stock stock-badge" data-val="0">0</span>
                              <div class="ng-badge-container"></div>
                          </div>
                        </td>

                        <td>
                          <input type="number"
                                 class="form-control text-center plan-input fw-bold text-primary"
                                 name="items[<?= $uuid ?>][plan]"
                                 min="0"
                                 max="1200"
                                 value="<?= esc($plan['target_per_shift'] ?? '') ?>">
                        </td>

                        <td>
                          <input type="text" class="form-control text-center actual-display bg-light border-0" value="<?= esc($actual) ?>" readonly>
                        </td>

                        <td>
                          <div class="form-check form-switch d-flex justify-content-center mb-1">
                            <input class="form-check-input border-warning dandori-toggle" type="checkbox" role="switch" 
                                   name="items[<?= $uuid ?>][is_dandori]" value="1" <?= $isDandori ? 'checked' : '' ?>>
                          </div>
                          
                          <div class="dandori-time-container <?= $isDandori ? '' : 'd-none' ?>">
                              <select class="form-select form-select-sm dandori-time-select bg-warning-subtle" name="items[<?= $uuid ?>][dandori_time_slot_id]">
                                  <option value="">-- Set Waktu --</option>
                                  <?php foreach ($shiftSlots[$shift['id']] as $slot): ?>
                                      <option value="<?= $slot['time_slot_id'] ?>" <?= ($dandoriTimeSlotId == $slot['time_slot_id']) ? 'selected' : '' ?>>
                                          <?= $slot['label'] ?>
                                      </option>
                                  <?php endforeach; ?>
                              </select>
                          </div>
                        </td>

                        <td>
                          <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row" title="Hapus Baris">
                            <i class="bi bi-trash"></i>
                          </button>
                        </td>

                        <input type="hidden" name="items[<?= $uuid ?>][shift_id]" value="<?= (int)$shift['id'] ?>">
                      </tr>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
              </div>
          </div>
          <div class="card-footer bg-white text-start">
             <button type="button" class="btn btn-sm btn-outline-primary fw-bold btn-add-machine" data-shift="<?= $shift['id'] ?>">
                <i class="bi bi-plus-circle"></i> Tambah Mesin di Shift Ini
             </button>
          </div>
      </div>
    <?php endforeach ?>
    
    <div class="position-sticky bottom-0 bg-white p-3 border-top shadow-sm d-flex justify-content-end mt-3 z-3">
        <button type="submit" class="btn btn-primary px-4 rounded-pill">
          <i class="bi bi-save me-1"></i> Simpan Jadwal Keseluruhan
        </button>
    </div>
</form>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
const productUrl = "/machining/daily-schedule/product-target";
const scheduleDate = "<?= esc($date) ?>";

function initSelect2(selectEl) {
  const $el = $(selectEl);
  if ($el.data('select2')) return;

  $el.select2({
    width: '100%',
    placeholder: '-- Cari & Pilih Part --',
    allowClear: true
  });
}

function updateStockDisplay(row, stockVal, ngVal, ngListHtml){
  const badge = row.querySelector('.stock-badge');
  const ngContainer = row.querySelector('.ng-badge-container');
  
  const stock = parseInt(stockVal || '0', 10);
  const ng = parseInt(ngVal || '0', 10);
  
  badge.dataset.val = String(stock);
  badge.textContent = stock.toLocaleString('id-ID');
  badge.classList.toggle('zero', stock <= 0);

  if(ng > 0) {
      ngContainer.innerHTML = `<span class="badge bg-danger ng-badge shadow-sm" 
                                     data-bs-toggle="popover" 
                                     data-bs-trigger="hover focus" 
                                     data-bs-placement="top" 
                                     data-bs-html="true" 
                                     title="Rincian Reject (NG)" 
                                     data-bs-content="${ngListHtml}">
                                 <i class="bi bi-exclamation-octagon me-1"></i> NG Before MC: ${ng}
                               </span>`;
                               
      const popoverTriggerList = row.querySelectorAll('[data-bs-toggle="popover"]')
      const popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl))
  } else {
      ngContainer.innerHTML = '';
  }
}

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

      opt.dataset.ct = p.cycle_time_used || '';
      opt.dataset.cavity = p.cavity || 0;
      opt.dataset.eff = p.efficiency_rate || 100;
      opt.dataset.stockReady = p.stock_ready || 0; 
      opt.dataset.ngBefore = p.ng_before_total || 0;     
      opt.dataset.ngList = p.ng_before_list || '';     

      selectEl.appendChild(opt);
    });

    initSelect2(selectEl);

    if (selectedId) {
      const row = selectEl.closest('tr');
      const planEl = row.querySelector('.plan-input');
      if (planEl) planEl.dataset.manual = "1";
      
      $(selectEl).val(String(selectedId)).trigger('change');
    } else {
      $(selectEl).trigger('change');
    }

  } catch (e) {
    console.error("Gagal load product machining", e);
    initSelect2(selectEl);
  }
}

function validatePlanAgainstStock(row, showAlert = true) {
  const selectEl = row.querySelector('.product-select');
  const planEl   = row.querySelector('.plan-input');

  if (!selectEl || !planEl) return true;
  if (!selectEl.value) return true;

  // --- CEK TOGGLE BYPASS ADMIN ---
  const bypassToggle = document.getElementById('bypassStockToggle');
  if (bypassToggle && bypassToggle.checked) {
      return true; // Langsung lolos tanpa memotong / memvalidasi stok
  }
  // -------------------------------

  const opt = selectEl.selectedOptions[0];
  const stockReady = parseInt(planEl.dataset.stockReady || opt.dataset.stockReady || '0', 10);
  let planVal = parseInt(planEl.value || '0', 10);

  if (stockReady <= 0 && planVal > 0) {
    if (showAlert) alert('Stok Area Machining Kosong. Lakukan Part Transfer terlebih dahulu.');
    planEl.value = 0;
    return false;
  }

  if (planVal > stockReady) {
    if (showAlert) alert(`Jadwal tidak boleh melebihi stok yang ada di Area Machining (${stockReady}).`);
    planEl.value = stockReady;
    return false;
  }

  return true;
}

function calculatePlan(row) {
    const shiftId = row.dataset.shift;
    const netSpan = document.querySelector(`.shift-net-minutes[data-shift="${shiftId}"]`);
    
    const netMins = parseInt(netSpan?.dataset.net || 0);
    const netSecs = netMins * 60; 

    const selectEl = row.querySelector('.product-select');
    const planEl   = row.querySelector('.plan-input');
    const opt = selectEl ? selectEl.selectedOptions[0] : null;

    if (opt && opt.value) {
        const cycle = parseInt(opt.dataset.ct || 0);
        const cavity = parseInt(opt.dataset.cavity || 0);
        const eff = parseFloat(opt.dataset.eff || 100) / 100;

        if (cycle > 0) {
            const autoTarget = Math.floor((netSecs / cycle) * cavity * eff);
            if (!planEl.dataset.manual) {
                planEl.value = autoTarget;
            }
        }
    }
    
    validatePlanAgainstStock(row, false);
}

$(document).on('change', '.shift-end-select', function() {
    const shiftId = this.dataset.shift;
    let totalMins = 0;
    let found = false;

    Array.from(this.options).forEach(opt => {
        if (found) return;
        const secs = parseInt(opt.dataset.seconds || 0);
        totalMins += Math.floor(secs / 60); 
        if (opt.selected) found = true; 
    });

    const netSpan = document.querySelector(`.shift-net-minutes[data-shift="${shiftId}"]`);
    if (netSpan) {
        netSpan.dataset.net = totalMins;
        netSpan.innerText = `${totalMins} Menit`;
    }

    document.querySelectorAll(`tr.schedule-row[data-shift="${shiftId}"]`).forEach(tr => {
        const planEl = tr.querySelector('.plan-input');
        if (planEl) planEl.dataset.manual = ""; 
        calculatePlan(tr);
    });
});

$(document).on('change', '.product-select', function() {
  const selectEl = this;
  const opt = selectEl.selectedOptions[0];
  const row = selectEl.closest('tr');

  const ctEl   = row.querySelector('.cycle-time');
  const planEl = row.querySelector('.plan-input');

  if (!opt || !selectEl.value) {
    if (ctEl) ctEl.value = '';
    if (planEl) {
        planEl.removeAttribute('data-stock-ready');
        planEl.value = '';
    }
    updateStockDisplay(row, 0, 0, '');
    return;
  }

  ctEl.value = opt.dataset.ct || '';

  const stockReady = parseInt(opt.dataset.stockReady || '0', 10);
  const ngBefore   = parseInt(opt.dataset.ngBefore || '0', 10);
  const ngList     = opt.dataset.ngList || '';
  
  planEl.dataset.stockReady = String(stockReady);
  updateStockDisplay(row, stockReady, ngBefore, ngList);

  // --- TAMBAHAN CEK BYPASS UNTUK ALERT ALERT SAAT CHANGE PRODUK ---
  const bypassToggle = document.getElementById('bypassStockToggle');
  const isBypass = bypassToggle && bypassToggle.checked;

  if (stockReady <= 0 && !isBypass) {
    alert('Stok di Area Machining Kosong. Tidak bisa menjadwalkan part ini.');
    planEl.value = 0;
    return;
  }
  // ---------------------------------------------------------------

  if (!planEl.value || planEl.value == 0) {
    planEl.dataset.manual = ""; 
  }
  
  calculatePlan(row);
});

$(document).on('input', '.plan-input', function() {
  this.dataset.manual = "1";
  const row = this.closest('tr');
  validatePlanAgainstStock(row, true);
});

// Aksi ketika Toggle Bypass dinyalakan/dimatikan
$(document).on('change', '#bypassStockToggle', function() {
    if (!this.checked) {
        // Jika Bypass dimatikan, jalankan validasi ulang di semua baris
        document.querySelectorAll('tr.schedule-row').forEach(row => {
            validatePlanAgainstStock(row, false);
        });
    }
});

// Aksi Toggle Dandori
$(document).on('change', '.dandori-toggle', function() {
    const container = $(this).closest('td').find('.dandori-time-container');
    const selectBox = container.find('.dandori-time-select');
    
    if (this.checked) {
        container.removeClass('d-none');
        selectBox.prop('required', true); 
    } else {
        container.addClass('d-none');
        selectBox.val('');
        selectBox.prop('required', false);
    }
});

$(document).on('click', '.btn-remove-row', function() {
  const tbody = this.closest('tbody');
  if (tbody.querySelectorAll('tr.schedule-row').length > 1) {
      this.closest('tr').remove();
  } else {
      alert('Minimal harus ada 1 baris mesin di dalam shift ini.');
  }
});

$(document).on('click', '.btn-add-machine', function() {
    const shiftId = $(this).data('shift');
    const tbody = document.getElementById(`tbody-shift-${shiftId}`);
    const firstRow = tbody.querySelector('.schedule-row'); 

    $(firstRow).find('.select2-hidden-accessible').select2('destroy');

    const newRow = firstRow.cloneNode(true);
    const uuid = 'row_' + Math.random().toString(36).substr(2, 9);

    newRow.querySelectorAll('[name]').forEach(el => {
        el.name = el.name.replace(/\[row_.*?\]/, `[${uuid}]`);
    });

    newRow.querySelectorAll('input[type="text"], input[type="number"], input[type="hidden"]').forEach(el => {
        if(el.name.indexOf('[shift_id]') === -1 && el.name.indexOf('[date]') === -1) {
            el.value = '';
        }
    });

    const qtyPInput = newRow.querySelector('.plan-input');
    if(qtyPInput) {
        qtyPInput.value = 0;
        qtyPInput.dataset.manual = ""; 
    }
    
    const actualInput = newRow.querySelector('.actual-display');
    if(actualInput) actualInput.value = 0;
    
    const pSelect = newRow.querySelector('.product-select');
    if(pSelect) pSelect.innerHTML = '<option value="">-- Cari Part Number --</option>'; 
    
    const badge = newRow.querySelector('.stock-badge');
    if(badge) {
        badge.dataset.val = '0';
        badge.textContent = '0';
        badge.classList.add('zero');
    }
    const ngContainer = newRow.querySelector('.ng-badge-container');
    if(ngContainer) ngContainer.innerHTML = '';
    
    // Reset Dandori Elements
    const cb = newRow.querySelector('.dandori-toggle');
    if(cb) cb.checked = false;
    const dContainer = newRow.querySelector('.dandori-time-container');
    if(dContainer) dContainer.classList.add('d-none');
    const dSelect = newRow.querySelector('.dandori-time-select');
    if(dSelect) {
        dSelect.value = '';
        dSelect.required = false;
    }

    tbody.appendChild(newRow);

    initSelect2(firstRow.querySelector('.product-select'));
    initSelect2(newRow.querySelector('.product-select'));
    
    $(newRow.querySelector('.machine-select')).trigger('change');
});

$(document).on('change', '.machine-select', function() {
    const sel = this.closest('tr').querySelector('.product-select');
    const opt = this.selectedOptions[0];
    const machineId = this.value;
    const lineTd = this.closest('tr').querySelector('.td-line');
    const shiftId = sel.dataset.shift;
    
    if(!machineId) return;
    
    if (lineTd && opt.dataset.line) {
        lineTd.innerText = opt.dataset.line;
    }
    
    sel.dataset.machine = machineId;
    loadProducts(sel);
});

$(document).on('submit', '.mc-form', function(e){
  const rows = this.querySelectorAll('tbody tr.schedule-row');
  for (const row of rows) {
    const isDandoriChecked = row.querySelector('.dandori-toggle').checked;
    
    // Saat disubmit, pastikan validasinya berjalan jika tidak bypass
    const ok = validatePlanAgainstStock(row, !isDandoriChecked); 
    
    if (!ok && !isDandoriChecked) {
      e.preventDefault();
      return false;
    }
  }
  return true;
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.shift-end-select').forEach(sel => {
        $(sel).trigger('change');
    });

    document.querySelectorAll('.product-select').forEach(selectEl => {
      initSelect2(selectEl);
      loadProducts(selectEl);
    });
});
</script>

<?= $this->endSection() ?>