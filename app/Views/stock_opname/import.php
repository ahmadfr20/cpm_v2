<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h4 class="fw-bold mb-1"><i class="bi bi-file-earmark-excel me-2 text-success"></i> Import Stock Opname</h4>
        <p class="text-muted mb-4 small">Silakan unggah file Excel Stock Opname (.xls/.xlsx). Pastikan format baris 6 untuk Section dan baris 7 untuk Sub-kategori sesuai dengan standar.</p>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i> <?= esc(session()->getFlashdata('error')) ?>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i> <?= esc(session()->getFlashdata('success')) ?>
            </div>
        <?php endif; ?>

        <form action="/sto/preview" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row g-4">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Tanggal Stok Opname</label>
                    <input type="date" class="form-control" name="production_date" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-bold">Upload File Excel</label>
                    <div class="input-group">
                        <input type="file" class="form-control" name="excel_file" accept=".xls,.xlsx" required>
                        <button type="submit" class="btn btn-primary fw-bold px-4">
                            <i class="bi bi-eye me-1"></i> Preview Data
                        </button>
                    </div>
                    <small class="text-danger mt-2 d-block">* Baris mulai data Part Name diletakkan di Kolom C, mulai Baris ke 8.</small>
                </div>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
