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
      <h4 class="mb-0 text-dark fw-bold">DAILY SCHEDULE &ndash; <?= strtoupper(esc($category)) ?></h4>
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
      <a href="<?= base_url('machining/daily-schedule/inventory?category='.urlencode($category).'&date='.$date) ?>" class="btn btn-primary fw-bold btn-sm rounded-pill px-3">
        <i class="bi bi-box-seam me-1"></i> Stock Area <?= esc($category) ?>
      </a>
  </div>
</div>

<?= $this->include('layout/schedule_unified_ui') ?>

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
                <input type="hidden" name="category" value="<?= esc($category) ?>">
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
    <input type="hidden" name="category" value="<?= esc($category) ?>">

    <?php foreach ($shifts as $shift): ?>
      <div class="card shadow-sm border-0 mb-4">
          <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
              <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-clock-history me-2"></i><?= esc($shift['shift_name']) ?></h5>
          </div>
          
          <div class="card-body bg-light border-bottom py-2">
             <div class="d-flex align-items-center gap-3">
                 <label class="fw-bold mb-0 text-dark"><i class="bi bi-info-circle"></i> Info Target Produksi:</label>
                 <div class="text-muted ms-2 small">Target Planning Harian (Pcs) akan menyesuaikan dengan durasi <b>Waktu Berakhir</b> yang dipilih pada masing-masing mesin.</div>
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
                        <th style="min-width:200px;">Waktu Produksi Spesifik</th>
                        <th style="min-width:140px;">Dandori &amp; Waktu</th>
                        <th style="width:60px">Hapus</th>
                      </tr>
                    </thead>

                    <tbody id="tbody-shift-<?= $shift['id'] ?>">
                    <?php 
                    foreach ($machines as $m):
                      $rows = $planMap[$shift['id']][$m['id']] ?? [[]]; 
                      
                      foreach ($rows as $idx => $plan):
                          $uuid = uniqid('row_'); 
                          $prodId = $plan['product_id'] ?? '';
                          $actKey  = $shift['id'].'_'.$m['id'].'_'.$prodId;
                          $actual  = $actualMap[$actKey]['act'] ?? 0;
                          
                          // Ambil data dandori dari map
                          $dandoriData = $dandoriMap[$shift['id']][$m['id']][$prodId] ?? null;
                          $isDandori = $dandoriData !== null;
                          $dandoriTimeSlotIds = $isDandori ? ($dandoriData['time_slot_ids'] ?? []) : [];
                          $dandoriSlotMinutes = $isDandori ? ($dandoriData['slot_minutes'] ?? []) : [];
                    ?>
                      <tr class="schedule-row" data-shift="<?= (int)$shift['id'] ?>">
                        <td class="text-muted fw-bold td-line"><?= esc($m['line_position']) ?></td>

                        <td>
                          <select class="form-select form-select-sm machine-select fw-bold" name="items[<?= $uuid ?>][machine_id]">
                            <?php foreach ($machines as $mach): ?>
                              <option value="<?= $mach['id'] ?>" data-line="<?= esc($mach['line_position']) ?>" <?= ($mach['id'] == $m['id']) ? 'selected' : '' ?>>
                                <?= esc($mach['machine_code']) ?>
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
                                 value="<?= esc($plan['target_per_shift'] ?? '') ?>"
                                 data-saved-plan="<?= esc($plan['target_per_shift'] ?? '') ?>">
                        </td>

                        <td>
                          <input type="text" class="form-control text-center actual-display bg-light border-0" value="<?= esc($actual) ?>" readonly>
                        </td>

                        <?php
                          $savedActiveSlots = $activeSlotMap[$shift['id']][$m['id']][$idx] ?? null;
                          $hasSpecificSlots = ($savedActiveSlots !== null);
                          $savedCustomTimes = $slotCustomTimesMap[$shift['id']][$m['id']][$idx] ?? null;
                          $customTimesMap = [];
                          if ($savedCustomTimes) {
                              foreach (explode(',', $savedCustomTimes) as $entry) {
                                  $parts = explode(':', $entry, 2);
                                  if (count($parts) === 2) $customTimesMap[(int)$parts[0]] = (int)$parts[1];
                              }
                          }
                        ?>
                        <td>
                          <div class="form-check form-switch d-flex align-items-center gap-2 mb-1">
                            <input class="form-check-input slot-toggle" type="checkbox" role="switch"
                                   id="slot-toggle-<?= $uuid ?>"
                                   <?= $hasSpecificSlots ? 'checked' : '' ?>
                                   onchange="mcToggleSlotPanel(this,'<?= $uuid ?>')"
                                   style="cursor:pointer;">
                            <label class="form-check-label small fw-bold" for="slot-toggle-<?= $uuid ?>" style="cursor:pointer;">
                              <?= $hasSpecificSlots ? '<span class="text-primary">Slot Spesifik</span>' : '<span class="text-muted">Semua Slot (Maks)</span>' ?>
                            </label>
                          </div>
                          <div class="slot-panel <?= $hasSpecificSlots ? '' : 'd-none' ?>" id="slot-panel-<?= $uuid ?>">
                            <input type="hidden" name="items[<?= $uuid ?>][active_slot_ids]" class="active-slot-ids-input" id="asi-<?= $uuid ?>" value="<?= $hasSpecificSlots ? esc(implode(',', $savedActiveSlots)) : '' ?>">
                            <input type="hidden" name="items[<?= $uuid ?>][slot_custom_times]" class="slot-custom-times-input" id="sct-<?= $uuid ?>" value="<?= esc($savedCustomTimes ?? '') ?>">
                            <table class="table table-sm table-bordered mb-0" style="font-size:11px;">
                              <thead class="table-light text-center"><tr><th style="width:20px;">✓</th><th>Slot Waktu</th><th style="width:55px;">Menit</th></tr></thead>
                              <tbody>
                                <?php foreach ($shiftSlots[$shift['id']] as $slot):
                                  if (!empty($slot['is_break'])) continue;
                                  $isActive = $hasSpecificSlots && in_array((int)$slot['time_slot_id'], $savedActiveSlots);
                                  $defaultMins = (int)floor($slot['seconds'] / 60);
                                  $customMins = $customTimesMap[(int)$slot['time_slot_id']] ?? $defaultMins;
                                ?>
                                <tr>
                                  <td class="text-center">
                                    <input type="checkbox" class="form-check-input slot-cb"
                                           data-uuid="<?= $uuid ?>"
                                           data-slot-seconds="<?= (int)$slot['seconds'] ?>"
                                           data-default-minutes="<?= $defaultMins ?>"
                                           value="<?= $slot['time_slot_id'] ?>"
                                           <?= $isActive ? 'checked' : '' ?>
                                           onchange="mcSyncSlotIds('<?= $uuid ?>')"
                                           style="width:14px;height:14px;">
                                  </td>
                                  <td style="white-space:nowrap"><?= esc($slot['label']) ?></td>
                                  <td>
                                    <input type="number" class="form-control form-control-sm text-center slot-custom-min p-0"
                                           data-uuid="<?= $uuid ?>" data-slot-id="<?= $slot['time_slot_id'] ?>"
                                           data-default="<?= $defaultMins ?>"
                                           value="<?= $isActive ? $customMins : $defaultMins ?>"
                                           min="1" max="<?= $defaultMins ?>"
                                           onchange="mcSyncSlotIds('<?= $uuid ?>')"
                                           style="font-size:11px;width:50px;" <?= $isActive ? '' : 'disabled' ?>>
                                  </td>
                                </tr>
                                <?php endforeach; ?>
                              </tbody>
                            </table>
                          </div>
                        </td>

                        <td>
                          <div class="form-check form-switch d-flex justify-content-center mb-1">
                            <input class="form-check-input border-warning dandori-toggle" type="checkbox" role="switch" 
                                   name="items[<?= $uuid ?>][is_dandori]" value="1" <?= $isDandori ? 'checked' : '' ?>>
                          </div>
                          
                          <div class="dandori-time-container <?= $isDandori ? '' : 'd-none' ?>">
                              <table class="table table-sm table-bordered mb-0" style="font-size:11px;">
                                <thead class="table-light text-center">
                                  <tr><th style="width:24px;">&#10003;</th><th>Waktu</th><th style="width:70px;">Menit</th></tr>
                                </thead>
                                <tbody>
                                  <?php foreach ($shiftSlots[$shift['id']] as $slot):
                                    if (!empty($slot['is_break'])) continue;
                                    $slotId = $slot['time_slot_id'];
                                    $isCheckedSlot = in_array($slotId, $dandoriTimeSlotIds);
                                    $savedMin = 0;
                                    foreach ($dandoriSlotMinutes as $sm) {
                                        if ((int)$sm['slot_id'] === (int)$slotId) { $savedMin = (int)$sm['minute']; break; }
                                    }
                                  ?>
                                  <tr>
                                    <td class="text-center">
                                      <input type="checkbox" class="form-check-input"
                                             name="items[<?= $uuid ?>][slot_data][<?= $slotId ?>][selected]"
                                             value="1" <?= $isCheckedSlot ? 'checked' : '' ?>
                                             style="width:14px;height:14px;">
                                    </td>
                                    <td style="white-space:nowrap"><?= esc($slot['label']) ?></td>
                                    <td>
                                      <input type="number" class="form-control form-control-sm text-center p-0"
                                             name="items[<?= $uuid ?>][slot_data][<?= $slotId ?>][minute]"
                                             value="<?= $isCheckedSlot ? $savedMin : 0 ?>" min="0" placeholder="mnt">
                                    </td>
                                  </tr>
                                  <?php endforeach; ?>
                                </tbody>
                              </table>
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

<script>
// Consolidate all items[] inputs into a single JSON field to avoid max_input_vars limit
document.querySelector('form.mc-form').addEventListener('submit', function(e) {
    const form = this;
    const items = {};
    const toDisable = [];

    form.querySelectorAll('[name^="items["]').forEach(function(el) {
        if (el.disabled) return;
        const name = el.name;
        const val = (el.type === 'checkbox') ? (el.checked ? el.value : '') : el.value;
        if (el.type === 'checkbox' && !el.checked) return;

        const keys = [];
        const regex = /\[([^\]]*)\]/g;
        let m;
        while ((m = regex.exec(name)) !== null) keys.push(m[1]);

        let obj = items;
        for (let i = 0; i < keys.length - 1; i++) {
            if (!obj[keys[i]]) obj[keys[i]] = {};
            obj = obj[keys[i]];
        }
        obj[keys[keys.length - 1]] = val;
        toDisable.push(el);
    });

    let jsonInput = form.querySelector('input[name="items_json"]');
    if (!jsonInput) {
        jsonInput = document.createElement('input');
        jsonInput.type = 'hidden';
        jsonInput.name = 'items_json';
        form.appendChild(jsonInput);
    }
    jsonInput.value = JSON.stringify(items);

    toDisable.forEach(function(el) { el.disabled = true; });
});
</script>

</form>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
const productUrl = "/machining/daily-schedule/product-target";
const scheduleDate = "<?= esc($date) ?>";
const scheduleCategory = "<?= esc($category) ?>";

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
    const res  = await fetch(`${productUrl}?machine_id=${machineId}&shift_id=${shiftId}&date=${encodeURIComponent(scheduleDate)}&category=${encodeURIComponent(scheduleCategory)}`);
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
      const savedPlan = planEl ? (planEl.dataset.savedPlan || planEl.value) : '';
      if (planEl) planEl.dataset.manual = "1";
      
      $(selectEl).val(String(selectedId)).trigger('change');
      
      // Restore saved plan value (change handler may have overwritten it)
      if (planEl && savedPlan) {
          planEl.value = savedPlan;
          planEl.dataset.manual = "1";
      }
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
    if (showAlert) alert('Stok Area ' + scheduleCategory + ' Kosong. Lakukan Part Transfer terlebih dahulu.');
    planEl.value = 0;
    return false;
  }

  if (planVal > stockReady) {
    if (showAlert) alert(`Jadwal tidak boleh melebihi stok yang ada di Area ${scheduleCategory} (${stockReady}).`);
    planEl.value = stockReady;
    return false;
  }

  return true;
}

function mcToggleSlotPanel(toggleEl, uuid) {
    const panel = document.getElementById('slot-panel-' + uuid);
    const labelEl = toggleEl.nextElementSibling;
    if (toggleEl.checked) {
        panel.classList.remove('d-none');
        if (labelEl) labelEl.innerHTML = '<span class="text-primary">Slot Spesifik</span>';
    } else {
        panel.classList.add('d-none');
        if (labelEl) labelEl.innerHTML = '<span class="text-muted">Semua Slot (Maks)</span>';
        panel.querySelectorAll('.slot-cb').forEach(cb => cb.checked = false);
        const hidInput = document.getElementById('asi-' + uuid);
        if (hidInput) hidInput.value = '';
        const tr = toggleEl.closest('tr');
        tr.dataset.netMin = '';
        const planEl = tr.querySelector('.plan-input');
        if (planEl) planEl.dataset.manual = '';
        calculatePlan(tr);
    }
}

function mcSyncSlotIds(uuid) {
    const panel = document.getElementById('slot-panel-' + uuid);
    const checkedCbs = Array.from(panel.querySelectorAll('.slot-cb:checked'));
    const checked = checkedCbs.map(cb => cb.value);
    const hidInput = document.getElementById('asi-' + uuid);
    if (hidInput) hidInput.value = checked.join(',');

    // Enable/disable custom minute inputs based on checkbox state
    panel.querySelectorAll('.slot-cb').forEach(cb => {
        const minInput = panel.querySelector(`.slot-custom-min[data-slot-id="${cb.value}"]`);
        if (minInput) {
            minInput.disabled = !cb.checked;
            if (!cb.checked) minInput.value = minInput.dataset.default || 60;
        }
    });

    // Build slot_custom_times string and calculate total minutes
    let totalMins = 0;
    let customParts = [];
    checkedCbs.forEach(cb => {
        const minInput = panel.querySelector(`.slot-custom-min[data-slot-id="${cb.value}"]`);
        const defaultMin = parseInt(cb.dataset.defaultMinutes || Math.floor((parseInt(cb.dataset.slotSeconds)||0)/60));
        const customMin = minInput ? parseInt(minInput.value || defaultMin) : defaultMin;
        totalMins += customMin;
        if (customMin !== defaultMin) {
            customParts.push(cb.value + ':' + customMin);
        }
    });

    const sctInput = document.getElementById('sct-' + uuid);
    if (sctInput) sctInput.value = customParts.join(',');

    const tr = panel.closest('tr');
    tr.dataset.netMin = totalMins;
    const planEl = tr.querySelector('.plan-input');
    // Only reset manual flag if no saved plan (user is actively changing slots)
    if (planEl && !planEl.dataset.savedPlan) planEl.dataset.manual = '';
    calculatePlan(tr);
}

function calculatePlan(row) {
    const shiftId = row.dataset.shift;
    
    // Ambil Menit Netto: dari slot spesifik (jika toggle ON) atau hitung dari semua slot
    let netMins = parseInt(row.dataset.netMin || 0);
    
    // Jika netMin belum di-set (toggle OFF / belum ada), hitung dari semua slot
    if (!row.dataset.netMin) {
        let totalSecs = 0;
        row.querySelectorAll('.slot-cb').forEach(cb => {
            totalSecs += parseInt(cb.dataset.slotSeconds || 0);
        });
        netMins = Math.floor(totalSecs / 60);
        // Jangan simpan ke dataset netMin jika toggle OFF, agar tetap fallback di kalkulasi selanjutnya
    }
    
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
    alert('Stok di Area ' + scheduleCategory + ' Kosong. Tidak bisa menjadwalkan part ini.');
    planEl.value = 0;
    return;
  }
  // ---------------------------------------------------------------

  if (!planEl.value || planEl.value == 0) {
    planEl.dataset.manual = ""; 
  }
  // User actively changed product — clear saved plan so auto-calc works for new product
  delete planEl.dataset.savedPlan;
  
  calculatePlan(row);
});

$(document).on('input', '.plan-input', function() {
  this.dataset.manual = "1";
  delete this.dataset.savedPlan; // User manually edited — clear saved plan
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
    if (this.checked) {
        container.removeClass('d-none');
    } else {
        container.addClass('d-none');
        container.find('input[type="checkbox"]').prop('checked', false);
        container.find('input[type="number"]').val(0);
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
    if(dContainer) {
        dContainer.classList.add('d-none');
        dContainer.querySelectorAll('input[type="checkbox"]').forEach(el => el.checked = false);
        dContainer.querySelectorAll('input[type="number"]').forEach(el => el.value = 0);
    }
    // Reset slot panel di row baru
    const slotToggle = newRow.querySelector('.slot-toggle');
    if (slotToggle) {
        slotToggle.checked = false;
        slotToggle.id = 'slot-toggle-' + uuid;
        slotToggle.setAttribute('onchange', `mcToggleSlotPanel(this,'${uuid}')`);
    }
    const slotToggleLabel = slotToggle ? slotToggle.parentElement.querySelector('label') : null;
    if (slotToggleLabel && slotToggle) {
        slotToggleLabel.setAttribute('for', 'slot-toggle-' + uuid);
        slotToggleLabel.innerHTML = '<span class="text-muted">Semua Slot (Maks)</span>';
    }
    const slotPanel = newRow.querySelector('.slot-panel');
    if (slotPanel) {
        slotPanel.id = 'slot-panel-' + uuid;
        slotPanel.classList.add('d-none');
        slotPanel.querySelectorAll('.slot-cb').forEach(el => {
            el.checked = false;
            el.dataset.uuid = uuid;
            el.setAttribute('onchange', `mcSyncSlotIds('${uuid}')`);
        });
    }
    const asiInput = newRow.querySelector('.active-slot-ids-input');
    if (asiInput) {
        asiInput.id = 'asi-' + uuid;
        asiInput.value = '';
    }
    const sctInput = newRow.querySelector('.slot-custom-times-input');
    if (sctInput) {
        sctInput.id = 'sct-' + uuid;
        sctInput.value = '';
    }
    // Reset custom minute inputs
    newRow.querySelectorAll('.slot-custom-min').forEach(el => {
        el.dataset.uuid = uuid;
        el.value = el.dataset.default || 60;
        el.disabled = true;
        el.setAttribute('onchange', `mcSyncSlotIds('${uuid}')`);
    });

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
    // Init slot panels yang sudah tersimpan
    document.querySelectorAll('tr.schedule-row').forEach(tr => {
        const slotToggle = tr.querySelector('.slot-toggle');
        if (slotToggle && slotToggle.checked) {
            const uuid = slotToggle.id.replace('slot-toggle-', '');
            mcSyncSlotIds(uuid);
        }
    });

    document.querySelectorAll('.product-select').forEach(selectEl => {
      initSelect2(selectEl);
      loadProducts(selectEl);
    });
    
    $('.dandori-time-select:not(.select2-hidden-accessible)').select2({ width: '100%', placeholder: '-- Set Waktu --', allowClear: true, closeOnSelect: false });
});
</script>

<?= $this->endSection() ?>