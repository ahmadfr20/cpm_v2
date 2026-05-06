<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">DIE CASTING – DAILY PRODUCTION SCHEDULE</h4>
  <a href="<?= base_url('die-casting/daily-schedule/inventory?date='.$date) ?>" class="btn btn-outline-info fw-bold btn-sm">
    <i class="bi bi-box-seam me-1"></i> Lihat Stock Die Casting
  </a>
</div>
<div class="d-flex justify-content-end mb-3 gap-2 d-print-none">
    <button type="button" class="btn btn-outline-success btn-sm fw-bold" onclick="exportGenericExcel()">
        <i class="bi bi-file-earmark-excel"></i> Export Excel
    </button>
    <button type="button" class="btn btn-outline-danger btn-sm fw-bold" onclick="window.print()">
        <i class="bi bi-printer"></i> Print / PDF
    </button>
</div>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<form method="get" class="mb-3">
  <input type="date" name="date" value="<?= esc($date) ?>"
         class="form-control" style="max-width:240px" onchange="this.form.submit()">
</form>

<form method="post" action="<?= site_url('/die-casting/daily-schedule/store') ?>">
<?= csrf_field() ?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
  .select2-container .select2-selection--single { height: 31px; }
  .select2-container--default .select2-selection--single .select2-selection__rendered{ line-height: 31px; font-size: 12px; }
  .select2-container--default .select2-selection--single .select2-selection__arrow{ height: 31px; }
</style>

<?php foreach ($shifts as $shift): ?>
  <div class="card mb-4 shadow-sm border-0">
    <div class="card-header bg-white d-flex align-items-center justify-content-between border-bottom-0 pt-3">
      <h5 class="mb-0 text-primary fw-bold">
        <i class="bi bi-clock-history"></i> <?= esc($shift['shift_name']) ?>
      </h5>
    </div>
    
    <div class="card-body bg-light border-top border-bottom py-2">
       <div class="d-flex align-items-center gap-3">
           <label class="fw-bold mb-0 text-dark"><i class="bi bi-info-circle"></i> Info Target Produksi:</label>
           <div class="text-muted ms-2 small">Target Plan Harian (Pcs) akan menyesuaikan dengan durasi <b>Waktu Selesai</b> yang dipilih pada masing-masing baris.</div>
       </div>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered table-sm text-center align-middle mb-0" id="table-shift-<?= $shift['id'] ?>">
        <thead class="table-light">
          <tr>
            <th style="min-width:120px;">Mesin</th>
            <th style="min-width:320px;">Part Produk</th>
            <th style="width:90px;">Plan (Pcs)</th>
            <th>A (Actual)</th>
            <th>NG</th>
            <th style="min-width:100px;">Status</th>
            <th style="min-width:200px;" title="Pilih time slot aktif per mesin">Waktu Produksi Spesifik</th>
            <th style="min-width:150px;" title="Tandai masuk proses setup cetakan">Dandori &amp; Waktu</th>
            <th>Hapus</th>
          </tr>
        </thead>
        <tbody id="tbody-shift-<?= $shift['id'] ?>">

        <?php 
        foreach ($machines as $m):
          $rows = $map[$shift['id']][$m['id']] ?? [[]]; 
          
          foreach ($rows as $idx => $p):
              $uuid = uniqid('row_'); 
              $prodId = $p['product_id'] ?? '';
              
              // Ambil data dandori dari map
              $dandoriData = $dandoriMap[$shift['id']][$m['id']][$prodId] ?? null;
              $isDandori = $dandoriData !== null;
              $dandoriTimeSlotIds = $isDandori ? ($dandoriData['time_slot_ids'] ?? []) : [];
              $dandoriSlotMinutes = $isDandori ? ($dandoriData['slot_minutes'] ?? []) : [];
        ?>
          <tr data-shift="<?= (int)$shift['id'] ?>" class="schedule-row">
            <td>
              <select class="form-select form-select-sm machine-select" name="items[<?= $uuid ?>][machine_id]">
                <?php foreach ($machines as $mach): ?>
                  <option value="<?= $mach['id'] ?>" <?= ($mach['id'] == $m['id']) ? 'selected' : '' ?>>
                    <?= esc($mach['machine_code']) ?>
                  </option>
                <?php endforeach ?>
              </select>
            </td>

            <td class="text-start">
              <select class="form-select form-select-sm product product-select"
                      data-machine="<?= (int)$m['id'] ?>"
                      data-shift="<?= (int)$shift['id'] ?>"
                      data-selected="<?= $prodId ?>"
                      name="items[<?= $uuid ?>][product_id]">
                <option value="">-- pilih --</option>
              </select>

              <input type="hidden" name="items[<?= $uuid ?>][shift_id]" value="<?= (int)$shift['id'] ?>">
              <input type="hidden" name="items[<?= $uuid ?>][date]" value="<?= esc($date) ?>">

              <input type="hidden" class="wa" name="items[<?= $uuid ?>][weight_ascas]" value="<?= (float)($p['weight_ascas'] ?? 0) ?>">
              <input type="hidden" class="wr" name="items[<?= $uuid ?>][weight_runner]" value="<?= (float)($p['weight_runner'] ?? 0) ?>">
              <input type="hidden" class="qty-a" name="items[<?= $uuid ?>][qty_a]" value="<?= (int)($p['qty_a'] ?? 0) ?>">
              <input type="hidden" class="qty-ng" name="items[<?= $uuid ?>][qty_ng]" value="<?= (int)($p['qty_ng'] ?? 0) ?>">
            </td>

            <td>
              <input type="number" class="form-control form-control-sm qty-p text-end"
                     name="items[<?= $uuid ?>][qty_p]"
                     value="<?= (int)($p['qty_p'] ?? 0) ?>"
                     data-saved-plan="<?= (int)($p['qty_p'] ?? 0) ?>"
                     min="0">
            </td>

            <td>
              <input type="number" class="form-control form-control-sm text-end qty-a-display"
                     value="<?= (int)($p['qty_a'] ?? 0) ?>" readonly>
            </td>
            <td>
              <input type="number" class="form-control form-control-sm text-end qty-ng-display"
                     value="<?= (int)($p['qty_ng'] ?? 0) ?>" readonly>
            </td>

            <td>
              <select class="form-select form-select-sm" name="items[<?= $uuid ?>][status]">
                <?php foreach (['Normal','Recovery','Trial','OFF'] as $s): ?>
                  <option value="<?= esc($s) ?>" <?= (($p['status'] ?? 'Normal') === $s) ? 'selected' : '' ?>>
                    <?= esc($s) ?>
                  </option>
                <?php endforeach ?>
              </select>
            </td>

            <?php
              // Active slots + custom times untuk mesin ini
              $savedActiveSlots = $activeSlotMap[$shift['id']][$m['id']][$idx] ?? null;
              $hasSpecificSlots = ($savedActiveSlots !== null);
              $savedCustomTimes = $slotCustomTimesMap[$shift['id']][$m['id']][$idx] ?? null;
              // Parse saved custom times: "slotId:minutes,slotId:minutes"
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
                       onchange="toggleSlotPanel(this, '<?= $uuid ?>')"
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
                      $defaultMins = (int)$slot['minutes'];
                      $customMins = $customTimesMap[(int)$slot['time_slot_id']] ?? $defaultMins;
                    ?>
                    <tr>
                      <td class="text-center">
                        <input type="checkbox" class="form-check-input slot-cb"
                               data-uuid="<?= $uuid ?>"
                               data-slot-minutes="<?= $defaultMins ?>"
                               data-default-minutes="<?= $defaultMins ?>"
                               value="<?= $slot['time_slot_id'] ?>"
                               <?= $isActive ? 'checked' : '' ?>
                               onchange="syncSlotIds('<?= $uuid ?>')"
                               style="width:14px;height:14px;">
                      </td>
                      <td style="white-space:nowrap"><?= esc($slot['label']) ?></td>
                      <td>
                        <input type="number" class="form-control form-control-sm text-center slot-custom-min p-0"
                               data-uuid="<?= $uuid ?>" data-slot-id="<?= $slot['time_slot_id'] ?>"
                               data-default="<?= $defaultMins ?>"
                               value="<?= $isActive ? $customMins : $defaultMins ?>"
                               min="1" max="<?= $defaultMins ?>"
                               onchange="syncSlotIds('<?= $uuid ?>')"
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
                      <tr><th style="width:24px;">✓</th><th>Waktu</th><th style="width:70px;">Menit</th></tr>
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
          </tr>
        <?php endforeach; ?>
        <?php endforeach; ?>

        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white text-start">
       <button type="button" class="btn btn-sm btn-outline-primary fw-bold btn-add-machine" data-shift="<?= $shift['id'] ?>">
          <i class="bi bi-plus-circle"></i> Tambah Mesin di Shift Ini
       </button>
    </div>
  </div>
<?php endforeach ?>

<div class="mt-4 d-flex gap-2">
  <button class="btn btn-success px-4 fw-bold" type="submit"><i class="bi bi-save"></i> Simpan Jadwal</button>
  <a href="<?= site_url('/die-casting/daily-schedule/inventory?date=' . esc($date)) ?>"
     class="btn btn-outline-dark"><i class="bi bi-eye"></i> View Result (WIP)</a>
</div>

<script>
// Consolidate all items[] inputs into a single JSON field to avoid max_input_vars limit
document.querySelector('form[action*="daily-schedule/store"]').addEventListener('submit', function(e) {
    const form = this;
    const items = {};
    const toDisable = [];

    form.querySelectorAll('[name^="items["]').forEach(function(el) {
        if (el.disabled) return;
        const name = el.name;
        // Parse items[uuid][field] or items[uuid][slot_data][slotId][field]
        const val = (el.type === 'checkbox') ? (el.checked ? el.value : '') : el.value;
        if (el.type === 'checkbox' && !el.checked) return;

        // Build nested object from name
        const keys = [];
        const regex = /\[([^\]]*)\]/g;
        const base = name.substring(0, name.indexOf('['));
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

    // Create single JSON hidden input
    let jsonInput = form.querySelector('input[name="items_json"]');
    if (!jsonInput) {
        jsonInput = document.createElement('input');
        jsonInput.type = 'hidden';
        jsonInput.name = 'items_json';
        form.appendChild(jsonInput);
    }
    jsonInput.value = JSON.stringify(items);

    // Disable individual items inputs so they don't count toward max_input_vars
    toDisable.forEach(function(el) { el.disabled = true; });
});
</script>

</form>

<script>
const productUrl = "<?= site_url('die-casting/daily-schedule/getProductAndTarget') ?>";

function initSelectUI(container) {
    $(container).find('.product-select:not(.select2-hidden-accessible)').select2({ width: '100%', placeholder: '-- pilih --', allowClear: true });
}

// Toggle panel slot spesifik per mesin
function toggleSlotPanel(toggleEl, uuid) {
    const panel = document.getElementById('slot-panel-' + uuid);
    const labelEl = toggleEl.nextElementSibling;
    if (toggleEl.checked) {
        panel.classList.remove('d-none');
        if (labelEl) labelEl.innerHTML = '<span class="text-primary">Slot Spesifik</span>';
    } else {
        panel.classList.add('d-none');
        if (labelEl) labelEl.innerHTML = '<span class="text-muted">Semua Slot (Maks)</span>';
        // Clear semua checklist & hidden input
        panel.querySelectorAll('.slot-cb').forEach(cb => cb.checked = false);
        const hidInput = document.getElementById('asi-' + uuid);
        if (hidInput) hidInput.value = '';
        // Recalculate dengan semua slot
        const tr = toggleEl.closest('tr');
        tr.dataset.netMin = '';
        const qtyP = tr.querySelector('.qty-p');
        if (qtyP) qtyP.dataset.manual = '';
        calculate(tr);
    }
}

// Sync selected slot IDs ke hidden input, lalu kalkulasi ulang
function syncSlotIds(uuid) {
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
        const customMin = minInput ? parseInt(minInput.value || minInput.dataset.default || 0) : parseInt(cb.dataset.slotMinutes || 0);
        totalMins += customMin;
        const defaultMin = parseInt(cb.dataset.defaultMinutes || cb.dataset.slotMinutes || 0);
        if (customMin !== defaultMin) {
            customParts.push(cb.value + ':' + customMin);
        }
    });

    const sctInput = document.getElementById('sct-' + uuid);
    if (sctInput) sctInput.value = customParts.join(',');

    const tr = panel.closest('tr');
    tr.dataset.netMin = totalMins;
    const qtyP = tr.querySelector('.qty-p');
    // Only reset manual flag if no saved plan (user is actively changing slots)
    if (qtyP && !qtyP.dataset.savedPlan) qtyP.dataset.manual = '';
    calculate(tr);
}

// Master Kalkulator
function calculate(tr){
  // Ambil Menit Netto: dari slot spesifik (jika toggle ON) atau dari semua slot shift
  let netMin = parseInt(tr.dataset.netMin || 0);

  // Jika netMin 0 (toggle OFF), hitung total dari semua slot-cb di baris ini
  if (netMin === 0) {
      tr.querySelectorAll('.slot-cb').forEach(cb => {
          netMin += parseInt(cb.dataset.slotMinutes || 0);
      });
  }

  // 2. Auto-kalkulasi Plan
  const productSelect = tr.querySelector('.product-select');
  const qtyPInput = tr.querySelector('.qty-p');
  const opt = productSelect ? productSelect.selectedOptions[0] : null;

  if (opt && opt.value) {
      const cycle = parseInt(opt.dataset.cycle || 0);
      const cavity = parseInt(opt.dataset.cavity || 0);
      const eff = parseFloat(opt.dataset.eff || 100) / 100;

      if (cycle > 0) {
          const autoTarget = Math.floor(((netMin * 60) / cycle) * cavity * eff);
          if (!qtyPInput.dataset.manual) {
              qtyPInput.value = autoTarget;
          }
      }
  }

  // 3. Update hidden input (UI untuk berat sudah dihapus)
  const qtyA = parseInt(tr.querySelector('.qty-a-display')?.value || 0);
  const hidA = tr.querySelector('.qty-a');
  if (hidA) hidA.value = qtyA;
}

function applySelectedOptionToRow(sel){
  const tr = sel.closest('tr');
  const opt = sel.selectedOptions[0];
  if (!opt || !tr) return;

  tr.querySelector('.wa').value = opt.dataset.ascas || 0;
  tr.querySelector('.wr').value = opt.dataset.runner || 0;
  const qtyP = tr.querySelector('.qty-p');
  // Don't reset manual flag if saved plan exists (initial load)
  if (!qtyP.dataset.savedPlan) qtyP.dataset.manual = "";
  
  calculate(tr);
}

// Event Listeners
$(document).on('input', '.qty-p', function() {
    this.dataset.manual = "1";
    delete this.dataset.savedPlan; // User manually edited — clear saved plan
    calculate(this.closest('tr'));
});

// Event Handler untuk memunculkan/menyembunyikan Tabel Slot Dandori
$(document).on('change', '.dandori-toggle', function() {
    const container = $(this).closest('td').find('.dandori-time-container');
    if (this.checked) {
        container.removeClass('d-none');
    } else {
        container.addClass('d-none');
        // Reset semua checkbox & minute di dalam container
        container.find('input[type="checkbox"]').prop('checked', false);
        container.find('input[type="number"]').val(0);
    }
});

// (row-end-select removed — slot dipilih via checklist per mesin)

$(document).on('change', '.product-select', function() {
  // User actively changed product — clear saved plan so auto-calc works
  const qtyP = this.closest('tr').querySelector('.qty-p');
  if (qtyP) delete qtyP.dataset.savedPlan;
  applySelectedOptionToRow(this);
});

$(document).on('click', '.btn-remove-row', function() {
  const tr = this.closest('tr');
  const tbody = tr.parentElement;
  
  if (tbody.querySelectorAll('tr.schedule-row').length > 1) {
      tr.remove();
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

    const qtyPInput = newRow.querySelector('.qty-p');
    qtyPInput.value = 0;
    qtyPInput.dataset.manual = ""; 
    
    newRow.querySelector('.qty-a-display').value = 0;
    newRow.querySelector('.qty-ng-display').value = 0;
    
    const pSelect = newRow.querySelector('.product-select');
    pSelect.innerHTML = '<option value="">-- pilih --</option>'; 
    
    // Reset Dandori di row baru
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
        slotToggle.setAttribute('onchange', `toggleSlotPanel(this,'${uuid}')`);
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
            el.setAttribute('onchange', `syncSlotIds('${uuid}')`);
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
        el.setAttribute('onchange', `syncSlotIds('${uuid}')`);
    });

    tbody.appendChild(newRow);
    initSelectUI(firstRow);
    initSelectUI(newRow);
    $(newRow.querySelector('.machine-select')).trigger('change');
});

$(document).on('change', '.machine-select', function() {
    const sel = this.closest('tr').querySelector('.product-select');
    const machineId = this.value;
    const shiftId = sel.dataset.shift;
    
    if(!machineId) return;

    fetch(`${productUrl}?machine_id=${machineId}&shift_id=${shiftId}`)
      .then(r => r.json())
      .then(res => {
        sel.innerHTML = '<option value="">-- pilih --</option>';
        res.forEach(p => {
          const opt = document.createElement('option');
          opt.value = p.id;
          opt.textContent = `${p.part_prod} - ${p.part_name}`;
          opt.dataset.ascas  = p.weight_ascas || 0;
          opt.dataset.runner = p.weight_runner || 0;
          opt.dataset.cycle  = p.cycle_time || 0;
          opt.dataset.cavity = p.cavity || 0;
          opt.dataset.eff    = p.efficiency_rate || 100;
          sel.appendChild(opt);
        });
        calculate(sel.closest('tr'));
      });
});

document.addEventListener('DOMContentLoaded', () => {
    initSelectUI(document.body);

    // Init netMin dari slot checklist yang sudah tersimpan
    document.querySelectorAll('tr.schedule-row').forEach(tr => {
        const slotToggle = tr.querySelector('.slot-toggle');
        if (slotToggle && slotToggle.checked) {
            const uuid = slotToggle.id.replace('slot-toggle-', '');
            syncSlotIds(uuid);
        }
    });

    document.querySelectorAll('.product-select').forEach(sel => {
      const selected = sel.dataset.selected || '';
      const tr = sel.closest('tr');
      const machineSel = tr.querySelector('.machine-select');
      const machineId = machineSel ? machineSel.value : sel.dataset.machine;
      const shiftId = sel.dataset.shift;

      fetch(`${productUrl}?machine_id=${machineId}&shift_id=${shiftId}`)
        .then(r => r.json())
        .then(res => {
          sel.innerHTML = '<option value="">-- pilih --</option>';
          res.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = `${p.part_prod} - ${p.part_name}`;
            opt.dataset.ascas  = p.weight_ascas || 0;
            opt.dataset.runner = p.weight_runner || 0;
            opt.dataset.cycle  = p.cycle_time || 0;
            opt.dataset.cavity = p.cavity || 0;
            opt.dataset.eff    = p.efficiency_rate || 100;

            if (String(selected) === String(p.id)) opt.selected = true;
            sel.appendChild(opt);
          });
          
          if (selected) {
             const savedPlan = tr.querySelector('.qty-p').dataset.savedPlan || tr.querySelector('.qty-p').value;
             tr.querySelector('.qty-p').dataset.manual = "1";
             applySelectedOptionToRow(sel);
             // Restore saved plan value (applySelectedOptionToRow may have overwritten it)
             if (savedPlan && parseInt(savedPlan) > 0) {
                 tr.querySelector('.qty-p').value = savedPlan;
                 tr.querySelector('.qty-p').dataset.manual = "1";
             }
          } else {
             calculate(tr);
          }
        });
    });
});
</script>

<?= $this->endSection() ?>