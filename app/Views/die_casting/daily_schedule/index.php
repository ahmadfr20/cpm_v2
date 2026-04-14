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
           <label class="fw-bold mb-0 text-dark"><i class="bi bi-stop-circle"></i> Waktu Berakhir Shift:</label>
           <div style="min-width: 250px;">
               <select class="form-select form-select-sm shift-end-select" name="shift_end_slots[<?= $shift['id'] ?>]" data-shift="<?= (int)$shift['id'] ?>">
                  <?php 
                    $savedEndId = $shiftEndSlots[$shift['id']] ?? null;
                    $lastSlotId = !empty($shiftSlots[$shift['id']]) ? end($shiftSlots[$shift['id']])['time_slot_id'] : null;
                    $activeEndId = $savedEndId ?: $lastSlotId; // Jika kosong, set ke slot terakhir
                  ?>
                  <?php foreach ($shiftSlots[$shift['id']] as $slot): ?>
                    <?php $sel = ($activeEndId == $slot['time_slot_id']) ? 'selected' : ''; ?>
                    <option value="<?= $slot['time_slot_id'] ?>" data-minutes="<?= $slot['minutes'] ?>" <?= $sel ?>><?= $slot['label'] ?></option>
                  <?php endforeach; ?>
               </select>
           </div>
           <div class="ms-auto fs-6 text-end">
               <span class="text-muted">Netto Produksi:</span> 
               <span class="shift-net-minutes text-primary fw-bold fs-4 ms-1" data-shift="<?= (int)$shift['id'] ?>" data-net="<?= (int)$shift['total_minute'] ?>">
                   <?= (int)$shift['total_minute'] ?>
               </span> <small class="text-muted">Menit</small>
           </div>
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
            <th style="min-width:150px;" title="Tandai masuk proses setup cetakan">Dandori & Waktu</th>
            <th>Hapus</th>
          </tr>
        </thead>
        <tbody id="tbody-shift-<?= $shift['id'] ?>">

        <?php 
        foreach ($machines as $m):
          $rows = $map[$shift['id']][$m['id']] ?? [[]]; 
          
          foreach ($rows as $p):
              $uuid = uniqid('row_'); 
              $prodId = $p['product_id'] ?? '';
              
              // Ambil data dandori dari map
              $dandoriData = $dandoriMap[$shift['id']][$m['id']][$prodId] ?? null;
              $isDandori = $dandoriData !== null;
              $dandoriTimeSlotId = $isDandori ? $dandoriData : '';
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

</form>

<script>
const productUrl = "<?= site_url('die-casting/daily-schedule/getProductAndTarget') ?>";

function initSelectUI(container) {
    $(container).find('.product-select:not(.select2-hidden-accessible)').select2({ width: '100%', placeholder: '-- pilih --', allowClear: true });
}

// Master Kalkulator (Hitung Target -> Update Hidden Input Berat)
function calculate(tr){
  const shiftId = tr.dataset.shift;

  // 1. Ambil Menit Netto
  const netSpan = document.querySelector(`.shift-net-minutes[data-shift="${shiftId}"]`);
  const netMin = parseInt(netSpan?.dataset.net || 0);

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
  tr.querySelector('.qty-p').dataset.manual = ""; // reset manual flag
  
  calculate(tr);
}

// Event Listeners
$(document).on('input', '.qty-p', function() {
    this.dataset.manual = "1";
    calculate(this.closest('tr'));
});

// Event Handler untuk memunculkan/menyembunyikan Dropdown Waktu Dandori
$(document).on('change', '.dandori-toggle', function() {
    const container = $(this).closest('td').find('.dandori-time-container');
    const selectBox = container.find('.dandori-time-select');
    
    if (this.checked) {
        container.removeClass('d-none');
        selectBox.prop('required', true); // Jadikan wajib diisi jika toggle nyala
    } else {
        container.addClass('d-none');
        selectBox.val('');
        selectBox.prop('required', false);
    }
});

// Menghitung Ulang Netto Menit berdasarkan Dropdown "Waktu Berakhir"
$(document).on('change', '.shift-end-select', function() {
    const shiftId = this.dataset.shift;
    let netMin = 0;
    let found = false;

    // Jumlahkan menit dari opsi pertama sampai opsi yang dipilih
    Array.from(this.options).forEach(opt => {
        if (found) return;
        netMin += parseInt(opt.dataset.minutes || 0);
        if (opt.selected) found = true; // Berhenti menjumlah setelah menyentuh slot ini
    });

    const netSpan = document.querySelector(`.shift-net-minutes[data-shift="${shiftId}"]`);
    if (netSpan) {
        netSpan.innerText = netMin;
        netSpan.dataset.net = netMin; 
    }

    // Kalkulasi ulang semua row di shift ini (hapus flag manual agar update massal)
    document.querySelectorAll(`tr.schedule-row[data-shift="${shiftId}"]`).forEach(tr => {
        const qtyP = tr.querySelector('.qty-p');
        if (qtyP) qtyP.dataset.manual = ""; 
        calculate(tr);
    });
});

$(document).on('change', '.product-select', function() {
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
    
    // Reset status elemen Dandori di row baru
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
    
    // Trigger perhitungan netto awal saat halaman dimuat
    document.querySelectorAll('.shift-end-select').forEach(sel => {
        $(sel).trigger('change');
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
             tr.querySelector('.qty-p').dataset.manual = "1";
             applySelectedOptionToRow(sel);
          } else {
             calculate(tr);
          }
        });
    });
});
</script>

<?= $this->endSection() ?>