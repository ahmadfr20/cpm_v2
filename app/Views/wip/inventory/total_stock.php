<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0 fw-bold">TOTAL STOCK PER PRODUCT</h4>
        <small class="text-muted">Akumulasi stock dari seluruh station proses (Per <?= esc($titleDate) ?>)</small>
    </div>
    
    <div>
        <a href="<?= base_url('wip/inventory?date='.$date) ?>" class="btn btn-outline-secondary btn-sm fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke WIP Shift
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
        <?php if (!empty($isAdmin) && $isAdmin): ?>
            <form method="get" class="d-flex gap-2 align-items-center mb-0">
                <label class="form-label mb-0 small fw-bold">Pilih Tanggal:</label>
                <input type="date" name="date" value="<?= esc($date) ?>" class="form-control form-control-sm w-auto">
                <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Tampilkan</button>
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
                        <th style="width: 60px;" class="text-center">No</th>
                        <th style="width: 200px;">Part Number</th>
                        <th>Part Name</th>
                        <th style="width: 350px;">Rincian Posisi Stock</th>
                        <th style="width: 150px;" class="text-end">Total Stock Keseluruhan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($productData)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                Tidak ada stock tersedia.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach($productData as $pid => $data): ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td class="fw-bold"><?= esc($data['part_no']) ?></td>
                                <td><?= esc($data['part_name']) ?></td>
                                <td>
                                    <?php foreach($data['details'] as $detail): ?>
                                        <div class="d-flex justify-content-between border-bottom border-light pb-1 mb-1">
                                            <span class="text-muted small"><?= esc($detail['process']) ?></span>
                                            <span class="fw-semibold small text-primary"><?= number_format($detail['qty']) ?> pcs</span>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-success fs-6 px-3 py-2">
                                        <?= number_format($data['total_stock']) ?>
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