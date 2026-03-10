<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
    /* Styling tabel agar responsif di layar kecil */
    .table-transfer {
        min-width: 1000px; /* Paksa tabel memiliki lebar minimum agar bisa di-scroll horizontal di HP */
    }
    .table-transfer th {
        vertical-align: middle;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
    }
    .table-transfer td {
        vertical-align: middle;
    }
    
    /* Lebar minimum per kolom untuk mencegah form squished (penyet) */
    .col-part    { min-width: 250px; white-space: normal; }
    .col-process { min-width: 130px; text-align: center; }
    .col-stock   { min-width: 120px; text-align: center; }
    .col-qty     { min-width: 150px; text-align: center; }
    .col-ng      { min-width: 320px; }
    .col-total   { min-width: 120px; text-align: center; }

    /* Input Styling */
    .input-qty {
        width: 100px !important;
        font-size: 1.1rem;
        font-weight: bold;
        text-align: center;
        margin: 0 auto;
    }
    .input-ok {
        background-color: #f1fdf6;
        border-color: #198754;
        color: #198754;
    }
    .input-ok:focus {
        box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
        border-color: #198754;
    }
    .stock-badge {
        font-size: 1.1rem;
        padding: 0.4em 0.8em;
    }
    
    /* Tombol NG & Sticky Footer */
    .btn-add-ng {
        border-style: dashed;
        font-size: 0.85rem;
    }
    .sticky-action-bar {
        position: sticky;
        bottom: 0;
        z-index: 1020;
        background-color: #f8f9fa;
        box-shadow: 0 -4px 10px rgba(0,0,0,0.05);
    }
</style>

<div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between mb-3">
    <div>
        <h4 class="mb-1 text-dark fw-bold">Part Transfer to Machining</h4>
        <p class="text-muted mb-0">Tarik WIP dari alur produksi sebelumnya. Input FG (OK) & NG secara massal.</p>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success alert-dismissible fade show shadow-sm">
    <i class="bi bi-check-circle-fill me-2"></i> <?= esc(session()->getFlashdata('success')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger alert-dismissible fade show shadow-sm">
    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= esc(session()->getFlashdata('error')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<form action="/production/transfer-machining/store" method="post" id="formTransfer">
    <?= csrf_field() ?>
    
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-bottom py-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-3 col-sm-6 col-6">
                    <label class="form-label fw-bold text-muted small mb-1">TANGGAL TRANSFER</label>
                    <input type="date" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-3 col-sm-6 col-6">
                    <label class="form-label fw-bold text-muted small mb-1">PILIH SHIFT</label>
                    <select name="shift_id" class="form-select" required>
                        <option value="">-- Pilih Shift --</option>
                        <?php foreach ($shifts as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= esc($s['shift_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 d-none d-md-block text-end">
                    <span class="badge bg-primary px-3 py-2 fs-6 rounded-pill">
                        <i class="bi bi-gear-wide-connected me-1"></i> Destinasi: Machining
                    </span>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive overflow-x-auto">
                <table class="table table-hover table-transfer mb-0">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="col-part text-start ps-4">Detail Part</th>
                            <th class="col-process">Dari Proses</th>
                            <th class="col-stock">Stok Tersedia</th>
                            <th class="col-qty text-success">Qty Masuk (OK)</th>
                            <th class="col-ng text-danger text-start">Reject (NG)</th>
                            <th class="col-total">Total Tarik</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($eligibleTransfers)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        Tidak ada Part di proses sebelumnya yang siap ditransfer ke Machining.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($eligibleTransfers as $row): ?>
                            <tr data-product-id="<?= $row['product_id'] ?>" data-stock="<?= $row['stock'] ?>">
                                <td class="col-part ps-4">
                                    <input type="hidden" name="transfers[<?= $row['product_id'] ?>][wip_id]" value="<?= $row['wip_id'] ?>">
                                    <input type="hidden" name="transfers[<?= $row['product_id'] ?>][prev_process_id]" value="<?= $row['prev_process_id'] ?>">
                                    <div class="fw-bolder text-primary fs-6 text-wrap"><?= esc($row['part_no']) ?></div>
                                    <div class="text-secondary small text-wrap"><?= esc($row['part_name']) ?></div>
                                </td>
                                
                                <td class="col-process">
                                    <span class="badge bg-secondary rounded-pill px-3"><?= esc($row['prev_process_name']) ?></span>
                                </td>
                                
                                <td class="col-stock">
                                    <span class="badge bg-info text-dark stock-badge rounded-pill"><?= number_format($row['stock']) ?></span>
                                </td>
                                
                                <td class="col-qty">
                                    <input type="number" name="transfers[<?= $row['product_id'] ?>][qty_ok]" 
                                           class="form-control input-qty input-ok qty-ok" 
                                           min="0" value="0">
                                </td>
                                
                                <td class="col-ng">
                                    <div class="ng-container"></div>
                                    <button type="button" class="btn btn-sm btn-outline-danger w-100 btn-add-ng rounded-3 mt-1">
                                        <i class="bi bi-plus-circle me-1"></i> Tambah NG
                                    </button>
                                </td>
                                
                                <td class="col-total bg-light">
                                    <input type="text" class="form-control input-qty bg-transparent border-0 total-pull" readonly value="0">
                                    <div class="text-danger error-stock fw-bold d-none mt-1" style="font-size:12px;">
                                        <i class="bi bi-x-circle"></i> Stok Kurang!
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if (!empty($eligibleTransfers)): ?>
        <div class="card-footer p-3 sticky-action-bar border-top d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <span class="text-muted small text-center text-md-start">
                <i class="bi bi-info-circle"></i> Pastikan total tarik tidak melebihi stok tersedia.
            </span>
            <div class="d-flex gap-2 w-100 w-md-auto justify-content-end">
                <button type="button" class="btn btn-light border px-4 rounded-pill" onclick="resetForm()">Reset</button>
                <button type="submit" class="btn btn-primary px-4 px-md-5 fw-bold rounded-pill w-100 w-md-auto" id="btnSubmit">
                    <i class="bi bi-send-check me-1"></i> Konfirmasi Transfer
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</form>

<template id="ng-row-template">
    <div class="input-group input-group-sm mb-2 ng-row shadow-sm">
        <select class="form-select ng-category" required style="min-width: 140px;">
            <option value="">-- Pilih NG --</option>
            <?php foreach($ngCategories as $ng): ?>
                <option value="<?= $ng['id'] ?>"><?= esc($ng['ng_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="number" class="form-control text-center ng-qty" style="max-width: 70px;" min="1" value="1" placeholder="Qty" required>
        <button type="button" class="btn btn-danger btn-remove-ng" title="Hapus NG"><i class="bi bi-trash3"></i></button>
    </div>
</template>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    
    // Fungsi Menghitung Total per Baris
    function calculateRow(tr) {
        let stock = parseInt(tr.data('stock')) || 0;
        let qtyOk = parseInt(tr.find('.qty-ok').val()) || 0;
        let qtyNgTotal = 0;
        
        tr.find('.ng-qty').each(function() {
            qtyNgTotal += parseInt($(this).val()) || 0;
        });

        let totalPull = qtyOk + qtyNgTotal;
        let totalInput = tr.find('.total-pull');
        
        totalInput.val(totalPull);

        // Validasi Stok
        if (totalPull > stock) {
            totalInput.addClass('text-danger');
            tr.find('.error-stock').removeClass('d-none');
            return false;
        } else {
            totalInput.removeClass('text-danger');
            tr.find('.error-stock').addClass('d-none');
            return true;
        }
    }

    // Fungsi Validasi Semua Form (Enable/Disable Submit)
    window.calculateAll = function() {
        let allValid = true;
        let hasInput = false;

        $('tbody tr').each(function() {
            let isValid = calculateRow($(this));
            let total = parseInt($(this).find('.total-pull').val()) || 0;
            
            if (!isValid) allValid = false;
            if (total > 0) hasInput = true;
        });

        $('#btnSubmit').prop('disabled', !(allValid && hasInput));
    }

    // Event Input Qty OK dan NG
    $(document).on('keyup change', '.qty-ok, .ng-qty', function() {
        if($(this).val() < 0) $(this).val(0); // Cegah input negatif
        calculateAll();
    });

    // Tambah Baris NG
    $('.btn-add-ng').click(function() {
        let tr = $(this).closest('tr');
        let productId = tr.data('product-id');
        let template = $('#ng-row-template').html();
        let newRow = $(template);
        
        newRow.find('.ng-category').attr('name', `transfers[${productId}][ng][category][]`);
        newRow.find('.ng-qty').attr('name', `transfers[${productId}][ng][qty][]`);

        tr.find('.ng-container').append(newRow);
        calculateAll();
    });

    // Hapus Baris NG
    $(document).on('click', '.btn-remove-ng', function() {
        $(this).closest('.ng-row').remove();
        calculateAll();
    });

    // Reset Form
    window.resetForm = function() {
        if(confirm('Reset semua inputan angka ke 0?')) {
            $('.qty-ok').val(0);
            $('.ng-container').empty();
            calculateAll();
        }
    }

    // Inisialisasi
    calculateAll();
});
</script>

<?= $this->endSection() ?>