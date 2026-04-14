<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
    /* Styling according to web app design guidelines */
    .bg-gradient-qc { background: linear-gradient(135deg, #10b981 0%, #047857 100%); color: white; }
    .qc-card { border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); margin-bottom: 24px; overflow: hidden; }
    .qc-card-header { padding: 1.25rem 1.5rem; background-color: #fff; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
    .qc-card-title { font-weight: 800; font-size: 1.125rem; color: #1e293b; margin: 0; }
    .qc-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .qc-table th { background: #f8fafc; color: #64748b; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; padding: 1rem 1.5rem; border-bottom: 2px solid #e2e8f0; }
    .qc-table td { padding: 1rem 1.5rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 0.875rem; font-weight: 500; }
    .qc-table tr:hover { background-color: #f8fafc; }
    
    .badge-soft-warning { background-color: #fef3c7; color: #d97706; padding: 0.35em 0.65em; font-weight: 700; border-radius: 6px; }
    .badge-soft-success { background-color: #d1fae5; color: #059669; padding: 0.35em 0.65em; font-weight: 700; border-radius: 6px; }
    
    .btn-inspect { background-color: #3b82f6; color: white; border: none; border-radius: 6px; padding: 0.5rem 1rem; font-weight: 600; font-size: 0.875rem; transition: background-color 0.2s; }
    .btn-inspect:hover { background-color: #2563eb; color: white; }
    
    /* Dynamic Form Styling */
    .ng-row { background: #fff5f5; border: 1px solid #fed7d7; border-radius: 8px; padding: 16px; margin-bottom: 12px; position: relative;}
    .btn-remove-ng { position: absolute; top: 12px; right: 12px; color: #e53e3e; cursor: pointer; background: transparent; border: none; }
    .btn-remove-ng:hover { color: #c53030; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1" style="font-weight: 900; color: #0f172a;">Quality Control Center</h3>
        <p class="text-muted mb-0" style="font-weight: 500;">Kelola evaluasi produk berdasarkan Production Flow.</p>
    </div>
    <form method="get" class="d-flex gap-2">
        <input type="date" name="date" class="form-control form-control-sm fw-bold" value="<?= esc($date) ?>">
        <button type="submit" class="btn btn-dark btn-sm fw-bold px-3">Filter</button>
    </form>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success fw-bold p-3 shadow-sm rounded-3 border-0 border-start border-4 border-success">
        <i class="bi bi-check-circle-fill me-2"></i><?= session()->getFlashdata('success') ?>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger fw-bold p-3 shadow-sm rounded-3 border-0 border-start border-4 border-danger">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= session()->getFlashdata('error') ?>
    </div>
<?php endif; ?>

<div class="row">
    <!-- COL 1: Waiting for Inspection -->
    <div class="col-xl-12 mb-4">
        <div class="qc-card bg-white">
            <div class="qc-card-header bg-gradient-qc">
                <h4 class="qc-card-title text-white"><i class="bi bi-hourglass-split me-2"></i> Schedule Inspeksi Hari Ini</h4>
            </div>
            <div class="table-responsive">
                <table class="qc-table table-hover">
                    <thead>
                        <tr>
                            <th>Tgl Schedule</th>
                            <th>Part No</th>
                            <th>Part Name</th>
                            <th>Dari Proses</th>
                            <th class="text-center">Maksimal Inspeksi</th>
                            <th>Progress</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($wips)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted fw-bold">
                                    <i class="bi bi-inbox fs-2 d-block mb-3"></i>
                                    Tidak ada QC schedule tersisa untuk hari ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($wips as $wip): ?>
                                <?php 
                                    $stock = (int)$wip['total_stock'];
                                    if ($stock <= 0) continue;
                                ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($wip['production_date'])) ?></td>
                                    <td class="fw-bold text-dark"><?= esc($wip['part_no']) ?></td>
                                    <td><?= esc($wip['part_name']) ?></td>
                                    <td>
                                        <span class="badge fw-bold" style="background-color: #e2e8f0; color: #475569; padding: 0.4em 0.6em; border-radius: 4px;">
                                            <i class="bi bi-diagram-3-fill me-1"></i> <?= esc($wip['from_process']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><span class="badge-soft-warning fs-6"><?= number_format($stock) ?> Pcs</span></td>
                                    <td>
                                        <div class="small fw-bold">
                                            Plan: <?= number_format((int)$wip['qty_plan']) ?><br>
                                            <span class="text-success">Done: <?= number_format((int)$wip['qty_inspected']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn-inspect shadow-sm" 
                                                onclick="openInspectionModal(<?= $wip['product_id'] ?>, <?= $wip['schedule_id'] ?>, '<?= esc($wip['part_no']) ?>', '<?= esc($wip['part_name']) ?>', <?= $stock ?>)">
                                            <i class="bi bi-search me-1"></i> Inspeksi
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- COL 2: Today's Inspected Goods -->
    <div class="col-xl-12">
        <div class="qc-card bg-white border border-light">
            <div class="qc-card-header">
                <h4 class="qc-card-title"><i class="bi bi-ui-checks-grid text-primary me-2"></i> Hasil Inspeksi (<?= esc(date('d/m/Y', strtotime($date))) ?>)</h4>
            </div>
            <div class="table-responsive">
                <table class="qc-table table-hover">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Shift / Checker</th>
                            <th>Barang</th>
                            <th class="text-success text-center">OK PASS</th>
                            <th class="text-danger text-center">NOT PASS</th>
                            <th>Detail NOT PASS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($inspections)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted fw-bold">Belum ada inspeksi yang dilakukan hari ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($inspections as $ins): ?>
                                <tr>
                                    <td><?= date('H:i', strtotime($ins['created_at'])) ?></td>
                                    <td>
                                        <span class="fw-bold d-block text-dark"><?= esc($ins['shift_name'] ?? '-') ?></span>
                                        <span class="small text-muted"><i class="bi bi-diagram-3-fill"></i> <?= esc($ins['process_name'] ?? 'WIP Lama') ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-bold d-block text-dark"><?= esc($ins['part_no']) ?></span>
                                        <span class="small text-muted"><?= esc($ins['part_name']) ?></span>
                                    </td>
                                    <td class="text-center text-success fw-bold fs-5"><?= number_format($ins['qty_ok']) ?></td>
                                    <td class="text-center text-danger fw-bold fs-5"><?= number_format($ins['qty_ng']) ?></td>
                                    <td>
                                        <?php if(isset($inspectionNgs[$ins['id']]) && !empty($inspectionNgs[$ins['id']])): ?>
                                            <ul class="list-unstyled mb-0 small">
                                                <?php foreach($inspectionNgs[$ins['id']] as $ng): ?>
                                                    <li class="mb-1 border-bottom pb-1">
                                                        <span class="fw-bold text-danger"><?= esc($ng['ng_code']) ?> - <?= esc($ng['ng_name']) ?></span> 
                                                        (<?= number_format($ng['qty']) ?> pcs)
                                                        <?php if(!empty($ng['image_path'])): ?>
                                                            <a href="<?= base_url($ng['image_path']) ?>" target="_blank" class="ms-2 badge bg-secondary text-decoration-none">
                                                                <i class="bi bi-image"></i> Lihat Foto
                                                            </a>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Inspection Modal -->
<div class="modal fade" id="inspectionModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <div class="modal-header bg-gradient-qc text-white border-0 py-3">
        <h5 class="modal-title fw-bold"><i class="bi bi-clipboard2-check me-2"></i> Form Quality Control</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="<?= base_url('qc/store') ?>" method="post" enctype="multipart/form-data" id="qcForm">
          <?= csrf_field() ?>
          <input type="hidden" name="product_id" id="modalProductId">
          <input type="hidden" name="schedule_id" id="modalScheduleId">
          <input type="hidden" name="production_date" value="<?= esc($date) ?>">
          
          <div class="modal-body p-4 bg-light">
              <!-- Item Summary -->
              <div class="card border-0 shadow-sm mb-4 rounded-3">
                  <div class="card-body">
                      <div class="row align-items-center">
                          <div class="col-md-7">
                              <h5 class="fw-bold text-dark mb-1" id="modalPartNo">Part No</h5>
                              <p class="text-muted mb-0" id="modalPartName">Part Name</p>
                          </div>
                          <div class="col-md-5 text-md-end mt-3 mt-md-0">
                              <span class="text-muted small d-block">Total Menunggu Diperiksa</span>
                              <h4 class="fw-black text-warning mb-0" id="modalRemainQty">0 Pcs</h4>
                          </div>
                      </div>
                  </div>
              </div>

              <div class="row g-3 mb-4">
                  <div class="col-md-12">
                      <label class="form-label fw-bold text-success">Barang OK PASS (Qty) <span class="text-danger">*</span></label>
                      <div class="input-group shadow-sm">
                          <span class="input-group-text bg-success text-white border-0"><i class="bi bi-check-circle-fill"></i></span>
                          <input type="number" name="qty_ok" id="inputQtyOk" class="form-control fw-bold form-control-lg text-success" min="0" value="0" required>
                      </div>
                      <small class="text-muted mt-1 d-block"><i class="bi bi-info-circle me-1"></i>Barang PASS otomatis masuk ke stock FG. <span class="fw-bold">Shift Inspeksi otomatis mengikuti Schedule.</span></small>
                  </div>
              </div>

              <hr class="border-secondary opacity-25">

              <div class="d-flex justify-content-between align-items-center mb-3">
                  <h6 class="fw-bold text-danger mb-0"><i class="bi bi-x-circle-fill me-1"></i> Rincian Barang NOT PASS (NG)</h6>
                  <button type="button" class="btn btn-outline-danger btn-sm fw-bold rounded-pill shadow-sm px-3" onclick="addNgRow()">
                      <i class="bi bi-plus-lg me-1"></i> Tambah Kategori NG
                  </button>
              </div>

              <div id="ngContainer">
                  <!-- Dynamic NG Rows Will Be Appended Here -->
              </div>
              <div id="emptyNgState" class="text-center py-4 rounded-3" style="background-color: #fce8e8; border: 1px dashed #fca5a5;">
                  <span class="text-danger fw-bold d-block"><i class="bi bi-shield-check fs-2 d-block mb-1"></i> Tidak Ada Temuan NG / Semua Lulus Uji</span>
              </div>
              
          </div>
          <div class="modal-footer border-0 py-3 bg-white d-flex justify-content-between">
              <span class="fw-bold text-dark border p-2 rounded-3" id="totalInspectedPreview">Total Inspeksi: 0 Pcs</span>
              <div>
                  <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Batal</button>
                  <button type="submit" class="btn btn-primary fw-bold shadow px-4">Simpan Inspeksi</button>
              </div>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Template for NG Row -->
<template id="ngRowTemplate">
    <div class="ng-row shadow-sm">
        <button type="button" class="btn-remove-ng" onclick="this.closest('.ng-row').remove(); calculateTotal(); updateEmptyState();"><i class="bi bi-x-circle-fill fs-5"></i></button>
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label fw-bold text-danger small">Kategori NG <span class="text-danger">*</span></label>
                <select name="ng_category_id[]" class="form-select form-select-sm fw-bold" required>
                    <option value="">-- Pilih NG --</option>
                    <?php foreach($ngCategories as $cat): ?>
                        <option value="<?= $cat['id'] ?>">[<?= esc($cat['ng_code']) ?>] <?= esc($cat['ng_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold text-danger small">Qty NG <span class="text-danger">*</span></label>
                <input type="number" name="ng_qty[]" class="form-control form-control-sm ng-qty-input fw-bold text-danger" min="1" value="1" required oninput="calculateTotal()">
            </div>
            <div class="col-md-12 mt-2">
                <label class="form-label fw-bold text-muted small"><i class="bi bi-camera me-1"></i> Foto Temuan (Opsional)</label>
                <input type="file" name="ng_image[]" class="form-control form-control-sm" accept="image/*">
            </div>
        </div>
    </div>
</template>

<script>
    let currentMaxRemain = 0;

    function openInspectionModal(prodId, schedId, partNo, partName, remainQ) {
        document.getElementById('modalProductId').value = prodId;
        document.getElementById('modalScheduleId').value = schedId;
        
        document.getElementById('modalPartNo').innerText = partNo;
        document.getElementById('modalPartName').innerText = partName;
        document.getElementById('modalRemainQty').innerText = remainQ + ' Pcs';
        
        currentMaxRemain = parseInt(remainQ);
        
        // Reset form
        document.getElementById('qcForm').reset();
        document.getElementById('ngContainer').innerHTML = '';
        updateEmptyState();
        calculateTotal();
        
        const myModal = new bootstrap.Modal(document.getElementById('inspectionModal'));
        myModal.show();
    }

    function addNgRow() {
        const template = document.getElementById('ngRowTemplate');
        const container = document.getElementById('ngContainer');
        const clone = template.content.cloneNode(true);
        container.appendChild(clone);
        
        updateEmptyState();
        calculateTotal();
    }
    
    function updateEmptyState() {
        const container = document.getElementById('ngContainer');
        const emptyState = document.getElementById('emptyNgState');
        if(container.children.length === 0) {
            emptyState.style.display = 'block';
        } else {
            emptyState.style.display = 'none';
        }
    }
    
    function calculateTotal() {
        const okQty = parseInt(document.getElementById('inputQtyOk').value) || 0;
        let ngQty = 0;
        document.querySelectorAll('.ng-qty-input').forEach(input => {
            ngQty += parseInt(input.value) || 0;
        });
        
        const total = okQty + ngQty;
        const totalTxt = document.getElementById('totalInspectedPreview');
        totalTxt.innerText = `Total Inspeksi: ${total} Pcs`;
        
        if(total > currentMaxRemain) {
            totalTxt.classList.add('text-danger', 'border-danger', 'bg-danger-subtle');
            totalTxt.classList.remove('text-dark', 'border');
        } else {
            totalTxt.classList.remove('text-danger', 'border-danger', 'bg-danger-subtle');
            totalTxt.classList.add('text-dark', 'border');
        }
    }
    
    document.getElementById('inputQtyOk').addEventListener('input', calculateTotal);
    
    // Validate on submit
    document.getElementById('qcForm').addEventListener('submit', function(e) {
        const okQty = parseInt(document.getElementById('inputQtyOk').value) || 0;
        let ngQty = 0;
        document.querySelectorAll('.ng-qty-input').forEach(input => {
            ngQty += parseInt(input.value) || 0;
        });
        
        const total = okQty + ngQty;
        if(total > currentMaxRemain) {
            e.preventDefault();
            alert(`Peringatan: Total barang yang diinspeksi (${total}) melebihi Qty Menunggu (${currentMaxRemain}). Silakan periksa kembali input Anda.`);
        }
    });

</script>

<?= $this->endSection() ?>
