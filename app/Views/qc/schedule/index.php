<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
    /* Styling according to web app design guidelines */
    .bg-gradient-schedule { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: white; }
    .qc-card { border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); margin-bottom: 24px; overflow: hidden; }
    .qc-card-header { padding: 1.25rem 1.5rem; background-color: #fff; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
    .qc-card-header.colored { padding: 1.25rem 1.5rem; }
    .qc-card-title { font-weight: 800; font-size: 1.125rem; margin: 0; }
    .qc-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .qc-table th { background: #f8fafc; color: #64748b; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; padding: 1rem 1.5rem; border-bottom: 2px solid #e2e8f0; }
    .qc-table td { padding: 1rem 1.5rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 0.875rem; font-weight: 500; }
    .qc-table tr:hover { background-color: #f8fafc; }
    
    .badge-soft-warning { background-color: #fef3c7; color: #d97706; padding: 0.35em 0.65em; font-weight: 700; border-radius: 6px; }
    .badge-soft-success { background-color: #d1fae5; color: #059669; padding: 0.35em 0.65em; font-weight: 700; border-radius: 6px; }
    .badge-soft-primary { background-color: #dbeafe; color: #1d4ed8; padding: 0.35em 0.65em; font-weight: 700; border-radius: 6px; }
    
    .process-badge { background-color: #e2e8f0; color: #475569; padding: 0.4em 0.6em; border-radius: 4px; font-weight: bold; border: 1px solid #cbd5e1; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1" style="font-weight: 900; color: #0f172a;">QC Schedule Management</h3>
        <p class="text-muted mb-0" style="font-weight: 500;">Rencanakan inspeksi Quality Control dan tarik stock WIP dari semua proses.</p>
    </div>
    <form method="get" class="d-flex gap-2" id="dateFilterForm">
        <input type="date" name="date" class="form-control form-control-sm fw-bold" value="<?= esc($date) ?>" id="filterDate">
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
    <!-- COL 1: Form Tambah Schedule -->
    <div class="col-xl-4 mb-4">
        <div class="qc-card bg-white">
            <div class="qc-card-header bg-gradient-schedule colored">
                <h4 class="qc-card-title text-white"><i class="bi bi-plus-circle me-2"></i> Buat Schedule Baru</h4>
            </div>
            <div class="card-body p-4">
                <form action="<?= base_url('ppc/qc-schedule/store') ?>" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="date" value="<?= esc($date) ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Pilih Product</label>
                        <select name="product_id" id="selectProduct" class="form-select border-2 shadow-sm fw-bold" required>
                            <option value="">-- Pilih Product --</option>
                            <?php foreach($products as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= esc($p['part_no']) ?> - <?= esc($p['part_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Proses Sumber (1 Tahap Sebelum QC)</label>
                        <select name="source_process_id" id="selectSourceProcess" class="form-select border-2 shadow-sm fw-bold" required disabled>
                            <option value="">-- Pilih Process Sumber --</option>
                            <!-- Options akan diisi via AJAX -->
                        </select>
                        <div id="stockInfo" class="mt-2 text-danger small fw-bold" style="display:none;"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Pilih Shift</label>
                        <select name="shift_id" class="form-select border-2 shadow-sm fw-bold" required>
                            <option value="">-- Pilih Shift --</option>
                            <?php foreach($shifts as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= esc($s['shift_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">Target Qty (Pcs)</label>
                        <input type="number" name="qty_plan" id="inputQtyPlan" class="form-control form-control-lg border-2 shadow-sm fw-bold text-primary" required min="1" disabled>
                    </div>

                    <button type="submit" id="btnSubmitSchedule" class="btn btn-primary w-100 fw-bold py-2 shadow-sm" disabled>
                        <i class="bi bi-save me-1"></i> Simpan Schedule
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- COL 2: Daftar Schedule -->
    <div class="col-xl-8 mb-4">
        <div class="qc-card bg-white h-100">
            <div class="qc-card-header border-bottom">
                <h4 class="qc-card-title text-dark"><i class="bi bi-card-checklist me-2 text-primary"></i> Daftar Schedule (<?= date('d/m/Y', strtotime($date)) ?>)</h4>
            </div>
            <div class="table-responsive">
                <table class="qc-table table-hover">
                    <thead>
                        <tr>
                            <th>Shift</th>
                            <th>Part No / Name</th>
                            <th>Resource (Asal)</th>
                            <th class="text-center">Target Qty</th>
                            <th class="text-center">Progress</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($schedules)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted fw-bold">
                                    <i class="bi bi-inbox fs-2 d-block mb-3 opacity-50"></i>
                                    Belum ada schedule QC untuk tanggal ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($schedules as $sched): ?>
                                <tr>
                                    <td><span class="fw-bold"><?= esc($sched['shift_name']) ?></span></td>
                                    <td>
                                        <span class="fw-bold text-dark d-block"><?= esc($sched['part_no']) ?></span>
                                        <span class="small text-muted"><?= esc($sched['part_name']) ?></span>
                                    </td>
                                    <td><span class="process-badge"><i class="bi bi-diagram-3-fill me-1 text-secondary"></i> <?= esc($sched['process_name']) ?></span></td>
                                    <td class="text-center"><span class="badge-soft-primary fs-6"><?= number_format($sched['qty_plan']) ?> Pcs</span></td>
                                    <td class="text-center">
                                        <?php if($sched['qty_inspected'] > 0): ?>
                                            <span class="badge-soft-success"><?= number_format($sched['qty_inspected']) ?> Insp.</span>
                                        <?php else: ?>
                                            <span class="badge-soft-warning">Belum Mulai</span>
                                        <?php endif; ?>
                                        <?php if($sched['status'] == 'COMPLETED'): ?>
                                            <i class="bi bi-check-circle-fill text-success ms-1"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if($sched['qty_inspected'] <= 0): ?>
                                            <form action="<?= base_url('ppc/qc-schedule/delete/' . $sched['id']) ?>" method="post" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus schedule ini?');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-outline-danger btn-sm border-0" title="Hapus"><i class="bi bi-trash"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-outline-secondary btn-sm border-0" disabled title="Sudah berjalan"><i class="bi bi-lock-fill"></i></button>
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

<div class="row">
    <!-- COL 3: Ready to Schedule Summary -->
    <div class="col-xl-12 mb-4">
        <div class="qc-card bg-white h-100 border border-light">
            <div class="qc-card-header border-bottom bg-light">
                <h4 class="qc-card-title text-dark"><i class="bi bi-box-seam me-2 text-primary"></i> Total Stock Siap QC (WIP 1 Flow Sebelum QC)</h4>
            </div>
            <div class="table-responsive">
                <table class="qc-table table-hover">
                    <thead>
                        <tr>
                            <th>Part No</th>
                            <th>Part Name</th>
                            <th>Posisi Proses</th>
                            <th class="text-center">Total WIP Tersedia</th>
                            <th class="text-center">Telah Dijadwalkan QC</th>
                            <th class="text-center">Sisa (Belum Dirilis Schedule)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($readyStocks)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted fw-bold">
                                    <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>
                                    Tidak ada stok WIP yang mengantri untuk QC.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($readyStocks as $rst): ?>
                                <tr>
                                    <td><span class="fw-bold text-dark"><?= esc($rst['part_no']) ?></span></td>
                                    <td><span class="small text-muted"><?= esc($rst['part_name']) ?></span></td>
                                    <td><span class="process-badge bg-white"><i class="bi bi-diagram-3-fill me-1 text-secondary"></i> <?= esc($rst['process_name']) ?></span></td>
                                    <td class="text-center"><span class="badge-soft-primary fs-6"><?= number_format($rst['total_wip']) ?> Pcs</span></td>
                                    <td class="text-center"><span class="badge-soft-success fw-bold"><?= number_format($rst['already_scheduled']) ?> Pcs</span></td>
                                    <td class="text-center">
                                        <?php if($rst['ready_to_schedule'] > 0): ?>
                                            <span class="badge bg-danger fs-6 rounded-pill px-3 shadow-sm"><?= number_format($rst['ready_to_schedule']) ?> Pcs Antri</span>
                                        <?php else: ?>
                                            <span class="badge bg-success fs-6 rounded-pill px-3 shadow-sm"><i class="bi bi-check-all"></i> Terjadwal Penuh</span>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectProduct = document.getElementById('selectProduct');
    const selectSourceProcess = document.getElementById('selectSourceProcess');
    const inputQtyPlan = document.getElementById('inputQtyPlan');
    const stockInfo = document.getElementById('stockInfo');
    const btnSubmitSchedule = document.getElementById('btnSubmitSchedule');
    const filterDate = document.getElementById('filterDate');

    let currentMaxStock = 0;

    selectProduct.addEventListener('change', function() {
        const productId = this.value;
        const date = filterDate.value;
        
        // Reset process list
        selectSourceProcess.innerHTML = '<option value="">-- Loading... --</option>';
        selectSourceProcess.disabled = true;
        inputQtyPlan.value = '';
        inputQtyPlan.disabled = true;
        stockInfo.style.display = 'none';
        btnSubmitSchedule.disabled = true;

        if (!productId) return;

        fetch(`<?= base_url('ppc/qc-schedule/available-stock') ?>?date=${date}&product_id=${productId}`)
            .then(response => response.json())
            .then(data => {
                selectSourceProcess.innerHTML = '<option value="">-- Pilih Process Sumber --</option>';
                
                if (data.length === 0) {
                    const noOption = document.createElement('option');
                    noOption.value = "";
                    noOption.text = "Tidak ada proses yang terdefinisi pada flow";
                    noOption.disabled = true;
                    selectSourceProcess.appendChild(noOption);
                } else {
                    data.forEach(item => {
                        const opt = document.createElement('option');
                        opt.value = item.process_id;
                        opt.dataset.maxStock = item.available_stock;
                        
                        if (parseInt(item.available_stock) <= 0) {
                            opt.text = `${item.process_name} (Stock WIP Kosong)`;
                        } else {
                            opt.text = `${item.process_name} (Tersedia: ${item.available_stock} Pcs)`;
                        }
                        selectSourceProcess.appendChild(opt);
                    });
                    selectSourceProcess.disabled = false;
                    
                    // Auto select the first process item and trigger change to apply constraints
                    if (data.length > 0) {
                        selectSourceProcess.selectedIndex = 1;
                        selectSourceProcess.dispatchEvent(new Event('change'));
                    }
                }
            })
            .catch(error => {
                console.error("Error fetching stock:", error);
                selectSourceProcess.innerHTML = '<option value="">Gagal memuat data</option>';
            });
    });

    selectSourceProcess.addEventListener('change', function() {
        const selectedOpt = this.options[this.selectedIndex];
        if (this.value && selectedOpt.dataset.maxStock !== undefined) {
            currentMaxStock = parseInt(selectedOpt.dataset.maxStock);
            
            if (currentMaxStock <= 0) {
                stockInfo.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i> Stock proses ini sedang kosong (0 Pcs)`;
                stockInfo.style.display = 'block';
                stockInfo.className = "mt-2 text-danger small fw-bold";
                
                inputQtyPlan.disabled = true;
                inputQtyPlan.value = '';
                btnSubmitSchedule.disabled = true;
            } else {
                stockInfo.innerHTML = `<i class="bi bi-info-circle me-1"></i> Stock maksimal dari proses ini: <strong>${currentMaxStock} Pcs</strong>`;
                stockInfo.style.display = 'block';
                stockInfo.className = "mt-2 text-primary small fw-bold";
                
                inputQtyPlan.disabled = false;
                inputQtyPlan.max = currentMaxStock;
                inputQtyPlan.value = currentMaxStock; // default to max
                btnSubmitSchedule.disabled = false;
            }
        } else {
            stockInfo.style.display = 'none';
            inputQtyPlan.disabled = true;
            inputQtyPlan.value = '';
            btnSubmitSchedule.disabled = true;
        }
    });

    inputQtyPlan.addEventListener('input', function() {
        if (parseInt(this.value) > currentMaxStock) {
            stockInfo.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i> Input melebihi stock maksimal (${currentMaxStock})`;
            stockInfo.className = "mt-2 text-danger small fw-bold";
            btnSubmitSchedule.disabled = true;
        } else if (parseInt(this.value) <= 0 || !this.value) {
            btnSubmitSchedule.disabled = true;
        } else {
            stockInfo.innerHTML = `<i class="bi bi-check-circle me-1"></i> Stock mencukupi (Maksimal: ${currentMaxStock})`;
            stockInfo.className = "mt-2 text-success small fw-bold";
            btnSubmitSchedule.disabled = false;
        }
    });
});
</script>

<?= $this->endSection() ?>
