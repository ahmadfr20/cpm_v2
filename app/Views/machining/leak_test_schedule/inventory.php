<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0 fw-bold text-primary">STOCK INVENTORY - LEAK TEST</h4>
        <small class="text-muted">Total Stock di station Leak Test (Per <?= esc($titleDate) ?>)</small>
    </div>
    
    <div>
        <a href="<?= base_url('machining/leak-test/schedule?date='.$date) ?>" class="btn btn-outline-secondary btn-sm fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Schedule
        </a>
    </div>
</div>

<div class="card shadow-sm border-0 border-top border-primary border-3">
    <div class="card-header bg-white py-3">
        <?php if (!empty($isAdmin) && $isAdmin): ?>
            <form method="get" class="d-flex gap-2 align-items-center mb-0">
                <label class="form-label mb-0 small fw-bold">Pilih Tanggal:</label>
                <input type="date" name="date" value="<?= esc($date) ?>" class="form-control form-control-sm w-auto">
                <button class="btn btn-primary btn-sm text-white"><i class="bi bi-search"></i> Filter</button>
            </form>
        <?php else: ?>
             <span class="badge bg-primary fs-6">Data Hari Ini</span>
        <?php endif; ?>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px;" class="text-center">No</th>
                        <th style="width: 250px;">Part Number</th>
                        <th>Part Name</th>
                        <th style="width: 200px;" class="text-end">Total Stock di Leak Test</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($productData)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                Tidak ada stock tersedia di area Leak Test.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach($productData as $data): ?>
                            <tr>
                                <td class="text-center fw-bold"><?= $no++ ?></td>
                                <td class="fw-bold text-dark"><?= esc($data['part_no']) ?></td>
                                <td class="text-secondary"><?= esc($data['part_name']) ?></td>
                                <td class="text-end">
                                    <span class="badge bg-success fs-6 px-3 py-2 shadow-sm">
                                        <?= number_format($data['total_stock']) ?> pcs
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>