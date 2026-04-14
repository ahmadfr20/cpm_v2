<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
    /* Styling according to web app design guidelines */
    .bg-gradient-fg { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: white; }
    .qc-card { border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); margin-bottom: 24px; overflow: hidden; }
    .qc-card-header { padding: 1.25rem 1.5rem; background-color: #fff; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
    .qc-card-title { font-weight: 800; font-size: 1.125rem; margin: 0; }
    
    .stat-card {
        background: #fff;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        margin-bottom: 24px;
        border: 1px solid #e2e8f0;
        transition: transform 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-5px);
    }
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin-right: 1.5rem;
    }
    .stat-info h3 { font-weight: 900; font-size: 1.8rem; margin: 0; color: #0f172a; }
    .stat-info p { margin: 0; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.8rem; }
    
    .qc-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .qc-table th { background: #f8fafc; color: #64748b; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; padding: 1rem 1.5rem; border-bottom: 2px solid #e2e8f0; }
    .qc-table td { padding: 1rem 1.5rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 0.875rem; font-weight: 500; }
    .qc-table tr:hover { background-color: #f8fafc; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1" style="font-weight: 900; color: #0f172a;">Finished Good Inventory</h3>
        <p class="text-muted mb-0" style="font-weight: 500;">Pantau ketersediaan stok produk jadi yang telah lulus Quality Control.</p>
    </div>
    <div>
        <a href="<?= base_url('inventory-fg/export') ?>" class="btn btn-success fw-bold shadow-sm rounded-pill px-4 py-2">
            <i class="bi bi-file-earmark-excel-fill me-2 fs-5 align-middle"></i> Export Excel (CSV)
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-icon bg-primary text-white bg-opacity-75">
                <i class="bi bi-box-seam"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($totalVarian) ?></h3>
                <p>Total Part Varian</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="stat-card">
            <div class="stat-icon bg-success text-white bg-opacity-75">
                <i class="bi bi-boxes"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($totalQty) ?></h3>
                <p>Total Stock (Pcs)</p>
            </div>
        </div>
    </div>
</div>

<div class="qc-card bg-white border border-light">
    <div class="qc-card-header border-bottom bg-light">
        <h4 class="qc-card-title text-dark"><i class="bi bi-list-ul me-2 text-primary"></i> Daftar Stock FG</h4>
    </div>
    <div class="table-responsive">
        <table class="qc-table table-hover">
            <thead>
                <tr>
                    <th>Part No</th>
                    <th>Part Name</th>
                    <th class="text-center">Total Masuk (Pcs)</th>
                    <th class="text-center">Telah Keluar / Delivery (Pcs)</th>
                    <th class="text-center">Stock Tersedia (Pcs)</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($inventory)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted fw-bold">
                            <i class="bi bi-inbox fs-2 d-block mb-3 opacity-50"></i>
                            Stok Finished Good kosong.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($inventory as $item): ?>
                        <tr>
                            <td><span class="fw-bold text-dark fs-6"><?= esc($item['part_no']) ?></span></td>
                            <td><span class="text-muted fw-bold"><?= esc($item['part_name']) ?></span></td>
                            <td class="text-center"><span class="badge bg-light text-dark border px-3 py-2 fs-6"><?= number_format($item['qty_total_in']) ?></span></td>
                            <td class="text-center"><span class="badge bg-secondary px-3 py-2 fs-6"><?= number_format($item['qty_delivered']) ?></span></td>
                            <td class="text-center"><span class="badge bg-success px-3 py-2 fs-6 shadow-sm"><?= number_format($item['qty_available']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
