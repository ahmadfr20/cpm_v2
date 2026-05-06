<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="m-0"><i class="bi bi-box-seam me-2"></i> Inventory Stock Raw Material</h4>
</div>

<div class="row g-4 mb-4">
    <?php foreach ($stocks as $stock): ?>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white bg-primary bg-gradient me-3" style="width: 60px; height: 60px;">
                        <?php if ($stock['material_type'] === 'INGOT'): ?>
                            <i class="bi bi-layers-half fs-3"></i>
                        <?php else: ?>
                            <i class="bi bi-recycle fs-3"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h6 class="text-uppercase text-muted mb-1 fw-bold"><?= esc($stock['material_type']) ?> Stok (<?= esc($stock['unit']) ?>)</h6>
                        <h3 class="mb-0 fw-bolder text-dark">
                            <?= number_format($stock['total_qty'], 2, ',', '.') ?>
                        </h3>
                        <small class="text-secondary">Update: <?= date('d M Y H:i', strtotime($stock['updated_at'])) ?></small>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($stocks)): ?>
        <div class="col-12">
            <div class="alert alert-warning">Belum ada data stok material yang tercatat.</div>
        </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pt-4 pb-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-1"></i> Log Penerimaan Scrap Terbaru</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" style="font-size: 0.9rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Shift</th>
                                <th>Aktual / Timbangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentScrap as $r): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($r['receive_date'])) ?></td>
                                    <td><?= esc($r['shift_name']) ?></td>
                                    <td class="fw-bold text-success">+<?= number_format($r['actual_scrap_received_kg'], 2, ',', '.') ?> Kg</td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if(empty($recentScrap)): ?>
                                <tr><td colspan="3" class="text-center text-muted">Belum ada historical scrap</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pt-4 pb-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-1"></i> Log Penerimaan Ingot Terbaru</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" style="font-size: 0.9rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Shift</th>
                                <th>Ingot Masuk</th>
                                <th>Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentIngot as $r): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($r['receive_date'])) ?></td>
                                    <td><?= esc($r['shift_name']) ?></td>
                                    <td class="fw-bold text-primary">+<?= number_format($r['qty_ingot'], 2, ',', '.') ?></td>
                                    <td><?= esc($r['unit']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if(empty($recentIngot)): ?>
                                <tr><td colspan="4" class="text-center text-muted">Belum ada historical ingot</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
