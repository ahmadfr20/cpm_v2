<?= $this->extend('layout/layout') ?>
<?= $this->section('custom_css') ?>
<style>
    .sto-input-table {
        min-width: 2500px;
    }
    .sto-input-table th, .sto-input-table td {
        vertical-align: middle;
    }
    .row-no { width: 50px; text-align: center; }
    .part-no { width: 120px; font-weight: 600; font-size: 0.82rem; white-space: nowrap; }
    .part-name { width: 250px; font-size: 0.82rem; color: #444; }
    
    .sticky-header thead {
        position: sticky;
        top: 0;
        z-index: 10;
    }

    /* Sticky Columns */
    .sticky-col {
        position: sticky;
        background-color: #fff;
        z-index: 2;
    }
    .sto-input-table thead .sticky-col {
        background-color: #212529;
        z-index: 11;
        border-right: 1px solid #495057;
    }
    .sto-input-table tbody .sticky-col {
        border-right: 2px solid #dee2e6;
    }
    .sticky-no { left: 0; min-width: 50px; }
    .sticky-partno { left: 50px; min-width: 120px; }
    .sticky-partname { left: 170px; min-width: 250px; }
    
    .val-cell { text-align: right; font-weight: 500; font-size: 0.85rem; padding-right: 10px !important; }
    .val-wip { border-left: 3px solid #0d6efd !important; }
    .val-store { border-left: 3px solid #198754 !important; }
    .val-ng { border-left: 3px solid #dc3545 !important; }
    .val-ship { border-left: 3px solid #ffc107 !important; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="container-fluid py-3">
    <!-- Page Header -->
    <div class="row mb-3 align-items-center">
        <div class="col-md-6">
            <h3 class="mb-0 fw-bold"><i class="bi bi-journal-check text-primary me-2"></i>Riwayat Stock Opname (STO)</h3>
            <p class="text-muted mb-0">Matriks data penyesuaian stok yang tersimpan</p>
        </div>
        <div class="col-md-6 text-end">
            <a href="/sto/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Input Manual</a>
            <a href="/sto/import" class="btn btn-success"><i class="bi bi-file-earmark-excel me-1"></i> Import Excel</a>
        </div>
    </div>

    <?php if (session()->getFlashdata('success')) : ?>
        <div class="alert alert-success fw-bold"><i class="bi bi-check-circle me-2"></i><?= esc(session()->getFlashdata('success')) ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')) : ?>
        <div class="alert alert-danger fw-bold"><i class="bi bi-exclamation-circle me-2"></i><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <!-- Filter Bar -->
            <div class="p-3 border-bottom bg-light">
                <div class="row g-3 align-items-center">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold mb-1">Pilih Tanggal STO</label>
                        <input type="date" class="form-control" id="filter_date" value="<?= esc($date) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold mb-1">Cari Produk</label>
                        <input type="text" class="form-control" id="searchProduct" placeholder="Ketik part no / nama...">
                    </div>
                    <div class="col-md-6 text-end align-self-end">
                        <button type="button" class="btn btn-outline-primary btn-sm me-2" id="btnShowFilledOnly">
                            <i class="bi bi-funnel me-1"></i>Tampilkan yang terisi saja
                        </button>
                        <a href="/sto/export?date=<?= esc($date) ?>" class="btn btn-success btn-sm" id="btnExportExcel">
                            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
                        </a>
                    </div>
                </div>
            </div>

            <div class="table-responsive" style="max-height: 70vh;">
                <table class="table table-bordered table-hover mb-0 sto-input-table sticky-header" id="stoTable">
                    <thead class="table-dark">
                        <tr>
                            <th rowspan="2" class="align-middle row-no sticky-col sticky-no">#</th>
                            <th rowspan="2" class="align-middle sticky-col sticky-partno" style="min-width:120px">Part No</th>
                            <th rowspan="2" class="align-middle sticky-col sticky-partname" style="min-width:250px">Part Name</th>
                            <?php foreach($processes as $pr): ?>
                                <?php if($pr['id'] == 12): ?>
                                    <th colspan="2" class="text-center align-middle border-start border-light" style="font-size:0.8rem;"><?= esc($pr['process_name']) ?></th>
                                <?php else: ?>
                                    <th colspan="3" class="text-center align-middle border-start border-light" style="font-size:0.8rem;"><?= esc($pr['process_name']) ?></th>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach($processes as $pr): ?>
                                <?php if($pr['id'] == 12): ?>
                                    <th class="text-center border-start border-light" style="min-width:70px; font-size:0.75rem;"><span class="text-warning">SHIP</span></th>
                                    <th class="text-center" style="min-width:70px; font-size:0.75rem;"><span class="text-success">STORE</span></th>
                                <?php else: ?>
                                    <th class="text-center border-start border-light" style="min-width:70px; font-size:0.75rem;"><span class="text-info">WIP</span></th>
                                    <th class="text-center" style="min-width:70px; font-size:0.75rem;"><span class="text-success">STORE</span></th>
                                    <th class="text-center" style="min-width:70px; font-size:0.75rem;"><span class="text-danger">NG</span></th>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody id="productTableBody">
                        <?php foreach($products as $i => $pd): ?>
                        <tr class="product-row" 
                            data-part-no="<?= strtolower(esc($pd['part_no'])) ?>"
                            data-part-name="<?= strtolower(esc($pd['part_name'])) ?>">
                            <td class="row-no sticky-col sticky-no"><?= $i + 1 ?></td>
                            <td class="part-no sticky-col sticky-partno"><?= esc($pd['part_no']) ?></td>
                            <td class="part-name sticky-col sticky-partname"><?= esc($pd['part_name']) ?></td>
                            <?php foreach($processes as $pr): ?>
                                <?php 
                                    $valWip   = $stoData[$pd['id']][$pr['id']]['wip'] ?? 0;
                                    $valStore = $stoData[$pd['id']][$pr['id']]['store'] ?? 0;
                                    $valNg    = $stoData[$pd['id']][$pr['id']]['ng'] ?? 0;
                                    $valShip  = $stoData[$pd['id']][$pr['id']]['ship'] ?? 0;
                                ?>
                                <?php if($pr['id'] == 12): ?>
                                    <td class="val-cell val-ship border-start border-secondary-subtle bg-light" data-val="<?= $valShip ?>">
                                        <?= $valShip > 0 ? '<span class="text-dark fw-bold">'.number_format($valShip).'</span>' : '<span class="text-muted opacity-25">-</span>' ?>
                                    </td>
                                    <td class="val-cell val-store bg-light" data-val="<?= $valStore ?>">
                                        <?= $valStore > 0 ? '<span class="text-success fw-bold">'.number_format($valStore).'</span>' : '<span class="text-muted opacity-25">-</span>' ?>
                                    </td>
                                <?php else: ?>
                                    <td class="val-cell val-wip border-start border-secondary-subtle" data-val="<?= $valWip ?>">
                                        <?= $valWip > 0 ? '<span class="text-primary fw-bold">'.number_format($valWip).'</span>' : '<span class="text-muted opacity-25">-</span>' ?>
                                    </td>
                                    <td class="val-cell val-store" data-val="<?= $valStore ?>">
                                        <?= $valStore > 0 ? '<span class="text-success fw-bold">'.number_format($valStore).'</span>' : '<span class="text-muted opacity-25">-</span>' ?>
                                    </td>
                                    <td class="val-cell val-ng" data-val="<?= $valNg ?>">
                                        <?= $valNg > 0 ? '<span class="text-danger fw-bold">'.number_format($valNg).'</span>' : '<span class="text-muted opacity-25">-</span>' ?>
                                    </td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchInput   = document.getElementById('searchProduct');
    const tbody         = document.getElementById('productTableBody');
    const rows          = tbody.querySelectorAll('.product-row');
    const btnFilter     = document.getElementById('btnShowFilledOnly');
    const dateFilter    = document.getElementById('filter_date');
    let showFilledOnly  = false;

    // ---- Date Filter Reload ----
    dateFilter.addEventListener('change', function() {
        if (this.value) {
            window.location.href = '?date=' + this.value;
        }
    });

    // ---- Search Filter ----
    searchInput.addEventListener('input', applyFilter);

    // ---- Show Filled Only Toggle ----
    btnFilter.addEventListener('click', function () {
        showFilledOnly = !showFilledOnly;
        this.classList.toggle('btn-primary', showFilledOnly);
        this.classList.toggle('btn-outline-primary', !showFilledOnly);
        this.innerHTML = showFilledOnly
            ? '<i class="bi bi-funnel-fill me-1"></i>Tampilkan semua'
            : '<i class="bi bi-funnel me-1"></i>Tampilkan yang terisi saja';
        applyFilter();
    });

    function rowHasValue(row) {
        return [...row.querySelectorAll('.val-cell')].some(td => parseFloat(td.dataset.val) > 0);
    }

    function applyFilter() {
        const q = searchInput.value.toLowerCase().trim();
        rows.forEach(row => {
            let visible = true;
            if (showFilledOnly && !rowHasValue(row)) visible = false;
            if (visible && q && !row.dataset.partNo.includes(q) && !row.dataset.partName.includes(q)) visible = false;
            row.style.display = visible ? '' : 'none';
        });
    }
});
</script>

<?= $this->endSection() ?>
