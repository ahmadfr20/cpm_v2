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
      <small class="text-muted">Mengambil data dari WIP Transfer Machining</small>
  </div>
  <div>
      <button class="btn btn-warning fw-bold btn-sm rounded-pill px-3 me-1 text-dark" onclick="openApprovalModal()">
          <i class="bi bi-check-all me-1"></i> Approval Stok Harian
      </button>
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
        </form>
    </div>
</div>

<?php foreach ($shifts as $shift): ?>
  <div class="card shadow-sm border-0 mb-4">
      <div class="card-header bg-white py-3 border-bottom">
          <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-clock-history me-2"></i><?= esc($shift['shift_name']) ?></h5>
      </div>
      <div class="card-body p-0">
          <form method="post" action="/machining/daily-schedule/store" class="mc-form">
            <?= csrf_field() ?>
            <input type="hidden" name="date" value="<?= esc($date) ?>">

            <div class="table-responsive">
                <table class="table table-hover table-bordered mb-0 align-middle text-center">
                  <thead class="table-light">
                    <tr>
                      <th style="width:60px">Line</th>
                      <th style="width:120px">Mesin</th>
                      <th style="width:350px" class="text-start">Part Details</th>
                      <th style="width:80px">CT</th>
                      <th style="width:150px">Stock Ready (MC)</th>
                      <th style="width:140px" class="text-primary">Planning (Target)</th>
                      <th style="width:100px">Actual</th>
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
                      <td class="text-muted fw-bold"><?= esc($machine['line_position']) ?></td>

                      <td>
                          <div class="fw-bold text-dark"><?= esc($machine['machine_code']) ?></div>
                          <div class="small text-muted" style="font-size: 11px;"><?= esc($machine['machine_name']) ?></div>
                      </td>

                      <td class="text-start">
                        <select class="form-select form-select-sm product-select w-100"
                                data-machine="<?= (int)$machine['id'] ?>"
                                data-shift="<?= (int)$shift['id'] ?>"
                                data-selected="<?= esc($plan['product_id'] ?? '') ?>"
                                name="items[<?= $idx ?>][product_id]">
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
                               name="items[<?= $idx ?>][plan]"
                               min="0"
                               max="1200"
                               value="<?= esc($plan['target_per_shift'] ?? '') ?>">
                      </td>

                      <td>
                        <input type="text" class="form-control text-center bg-light border-0" value="<?= esc($actual) ?>" readonly>
                      </td>

                      <input type="hidden" name="items[<?= $idx ?>][machine_id]" value="<?= (int)$machine['id'] ?>">
                      <input type="hidden" name="items[<?= $idx ?>][shift_id]" value="<?= (int)$shift['id'] ?>">
                    </tr>
                  <?php endforeach ?>
                  </tbody>
                </table>
            </div>

            <div class="p-3 bg-light border-top text-end">
                <button type="submit" class="btn btn-primary px-4 rounded-pill">
                  <i class="bi bi-save me-1"></i> Simpan Jadwal <?= esc($shift['shift_name']) ?>
                </button>
            </div>
          </form>
      </div>
  </div>
<?php endforeach ?>


<div class="modal fade" id="modalApproval" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold"><i class="bi bi-clipboard-check text-success me-2"></i> Approval Stok & Reject Harian</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                
                <div class="alert alert-info d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold mb-1">Tanggal Transfer: <span id="appDate">Memuat...</span></h6>
                        <div id="appStatus">Memuat Status...</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-success"><i class="bi bi-box-seam"></i> Part Masuk (OK)</h6>
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr><th>Part No</th><th class="text-end">Qty</th></tr>
                            </thead>
                            <tbody id="appTblFg">
                                <tr><td colspan="2" class="text-center">Memuat...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="fw-bold text-danger"><i class="bi bi-x-octagon"></i> Reject Sebelum MC (NG)</h6>
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr><th>Part No</th><th>Kategori NG</th><th class="text-end">Qty</th></tr>
                            </thead>
                            <tbody id="appTblNg">
                                <tr><td colspan="3" class="text-center">Memuat...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <form action="/machining/daily-schedule/approve" method="post">
                    <?= csrf_field() ?>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-success fw-bold" id="btnApprove"><i class="bi bi-check-lg"></i> Approve Data Ini</button>
                </form>
            </div>
        </div>
    </div>
</div>


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

  // Tampilkan Label NG menggunakan atribut Data-BS-Toggle untuk Popover Bootstrap
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
                               
      // Inisialisasi popover untuk elemen yang baru dirender
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
      opt.dataset.targetShift = p.target_per_shift || 0;
      opt.dataset.stockReady = p.stock_ready || 0; 
      opt.dataset.ngBefore = p.ng_before_total || 0;     
      opt.dataset.ngList = p.ng_before_list || '';     

      selectEl.appendChild(opt);
    });

    initSelect2(selectEl);

    if (selectedId) {
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

$(document).on('change', '.product-select', function() {
  const selectEl = this;
  const opt = selectEl.selectedOptions[0];
  const row = selectEl.closest('tr');

  const ctEl   = row.querySelector('.cycle-time');
  const planEl = row.querySelector('.plan-input');

  if (!opt || !selectEl.value) {
    if (ctEl) ctEl.value = '';
    if (planEl) planEl.removeAttribute('data-stock-ready');
    updateStockDisplay(row, 0, 0, '');
    return;
  }

  ctEl.value = opt.dataset.ct || '';

  const stockReady = parseInt(opt.dataset.stockReady || '0', 10);
  const ngBefore   = parseInt(opt.dataset.ngBefore || '0', 10);
  const ngList     = opt.dataset.ngList || '';
  
  planEl.dataset.stockReady = String(stockReady);

  // Update UI Stock dan Hover NG List
  updateStockDisplay(row, stockReady, ngBefore, ngList);

  if (stockReady <= 0) {
    alert('Stok di Area Machining Kosong. Tidak bisa menjadwalkan part ini.');
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

$(document).on('submit', '.mc-form', function(e){
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

// LOGIC MODAL APPROVAL
function openApprovalModal() {
    $('#modalApproval').modal('show');
    $('#appTblFg').html('<tr><td colspan="2" class="text-center"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>');
    $('#appTblNg').html('<tr><td colspan="3" class="text-center"><div class="spinner-border spinner-border-sm text-danger"></div></td></tr>');

    $.get('/machining/daily-schedule/approval-data', function(res) {
        $('#appDate').text(res.date);
        
        if(res.is_approved) {
            $('#appStatus').html(`<span class="badge bg-success"><i class="bi bi-check2-circle"></i> Sudah di-approve oleh ${res.approved_by}</span>`);
            $('#btnApprove').hide();
        } else {
            $('#appStatus').html(`<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> Menunggu Approval</span>`);
            $('#btnApprove').show();
        }

        // Render FG
        let fgHtml = '';
        if(res.fg_data.length === 0) {
            fgHtml = '<tr><td colspan="2" class="text-center text-muted">Belum ada transfer masuk hari ini.</td></tr>';
        } else {
            res.fg_data.forEach(item => {
                fgHtml += `<tr><td><div class="fw-bold">${item.part_no}</div><small class="text-muted">${item.part_name}</small></td><td class="text-end fw-bold text-success">${item.total_fg}</td></tr>`;
            });
        }
        $('#appTblFg').html(fgHtml);

        // Render NG
        let ngHtml = '';
        if(res.ng_data.length === 0) {
            ngHtml = '<tr><td colspan="3" class="text-center text-muted">Tidak ada Reject (NG) hari ini.</td></tr>';
        } else {
            res.ng_data.forEach(item => {
                ngHtml += `<tr>
                            <td><div class="fw-bold">${item.part_no}</div><small class="text-muted">${item.part_name}</small></td>
                            <td>${item.ng_name}</td>
                            <td class="text-end fw-bold text-danger">${item.total_ng}</td>
                           </tr>`;
            });
        }
        $('#appTblNg').html(ngHtml);
    });
}

document.querySelectorAll('.product-select').forEach(selectEl => {
  initSelect2(selectEl);
  loadProducts(selectEl);
});
</script>

<?= $this->endSection() ?>