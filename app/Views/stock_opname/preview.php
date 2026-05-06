<?= $this->extend('layout/layout') ?>
<?= $this->section('custom_css') ?>
<style>
.sto-input-table {
    min-width: 2500px;
}
.sto-input-table th, .sto-input-table td {
    vertical-align: middle;
}
.sto-input-table .part-no {
    font-weight: 600;
    font-size: 0.82rem;
    white-space: nowrap;
}
.sto-input-table .part-name {
    font-size: 0.82rem;
    color: #444;
}
.qty-wip  { border-left: 3px solid #0d6efd !important; }
.qty-store{ border-left: 3px solid #198754 !important; }
.qty-ng   { border-left: 3px solid #dc3545 !important; }

.qty-input {
    width: 100%;
    min-width: 60px;
    text-align: right;
    padding-right: 5px;
}
.qty-input::-webkit-outer-spin-button,
.qty-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.qty-input[type=number] {
    -moz-appearance: textfield;
}

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

.highlight-row td { background-color: #fffbe6 !important; }
.excel-badge { background: linear-gradient(135deg, #217346, #33a867); font-size: 0.7rem; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="container-fluid py-3">
    <!-- Page Header -->
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h4 class="mb-0 fw-bold"><i class="bi bi-table text-success me-2"></i>Preview Import STO</h4>
            <p class="text-muted mb-0 small">
                Tanggal: <strong><?= esc($date) ?></strong> &mdash;
                <span class="badge excel-badge"><?= count($products) ?> produk ditemukan dari Excel</span>
            </p>
        </div>
        <div class="col-auto">
            <a href="/sto/import" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Upload Ulang</a>
        </div>
    </div>

    <?php if (!empty($unmappedRows)): ?>
    <div class="alert alert-warning py-2 mb-3">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong><?= count($unmappedRows) ?></strong> baris dari Excel tidak dapat dipetakan (Part Name / Section tidak ditemukan di master).
        <a href="#" data-bs-toggle="collapse" data-bs-target="#unmappedDetail" class="ms-2 small">Lihat detail...</a>
        <div id="unmappedDetail" class="collapse mt-2">
            <div class="table-responsive border rounded" style="max-height:200px">
                <table class="table table-sm table-striped mb-0" style="font-size:0.8rem">
                    <thead class="table-light"><tr><th>Part Name</th><th>Section</th><th>Category</th><th class="text-end">Qty</th><th>Problem</th></tr></thead>
                    <tbody>
                    <?php foreach($unmappedRows as $u): ?>
                        <tr>
                            <td class="fw-bold"><?= esc($u['part_name']) ?></td>
                            <td><?= esc($u['section']) ?></td>
                            <td><?= esc($u['category']) ?></td>
                            <td class="text-end"><?= number_format($u['qty']) ?></td>
                            <td>
                                <?php if(empty($u['product_id'])): ?><span class="badge bg-danger">Part Not Found</span><?php endif; ?>
                                <?php if(empty($u['to_process_id'])): ?><span class="badge bg-warning text-dark">Section Unmapped</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($products)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle me-2"></i>Tidak ada data valid yang ditemukan dari file Excel. Pastikan format dan nama part sesuai master data.
        </div>
    <?php else: ?>

    <form action="/sto/store" method="post" id="stoImportForm">
        <?= csrf_field() ?>
        <input type="hidden" name="production_date" value="<?= esc($date) ?>">

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">

                <!-- Summary Bar -->
                <div class="p-3 border-bottom bg-light">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <input type="text" class="form-control form-control-sm" id="searchProduct" placeholder="🔍 Cari part no / nama...">
                        </div>
                        <div class="col-md-8 text-end">
                            <span class="badge bg-primary me-1" id="summaryWip">WIP: 0</span>
                            <span class="badge bg-success me-1" id="summaryStore">STORE: 0</span>
                            <span class="badge bg-danger me-1" id="summaryNg">NG: 0</span>
                            <span class="badge bg-warning text-dark me-2" id="summaryShip">SHIP: 0</span>
                            <button type="submit" class="btn btn-success btn-sm fw-bold px-4 ms-2">
                                <i class="bi bi-check-circle me-1"></i>Konfirmasi & Simpan
                            </button>
                        </div>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 70vh;">
                    <table class="table table-bordered table-hover mb-0 sto-input-table sticky-header" id="stoTable">
                        <thead class="table-dark">
                            <tr>
                                <th rowspan="2" class="align-middle text-center sticky-col sticky-no" style="width:50px">#</th>
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
                                <td class="text-center sticky-col sticky-no"><?= $i + 1 ?></td>
                                <td class="part-no sticky-col sticky-partno"><?= esc($pd['part_no']) ?></td>
                                <td class="part-name sticky-col sticky-partname"><?= esc($pd['part_name']) ?></td>
                                <?php foreach($processes as $pr): ?>
                                    <?php
                                        // Excel data (imported values)
                                        $valWip   = $matrixData[$pd['id']][$pr['id']]['wip'] ?? '';
                                        $valStore = $matrixData[$pd['id']][$pr['id']]['store'] ?? '';
                                        $valNg    = $matrixData[$pd['id']][$pr['id']]['ng'] ?? '';
                                        $valShip  = $matrixData[$pd['id']][$pr['id']]['ship'] ?? '';

                                        // Existing STO data for comparison
                                        $exWip   = $existingSto[$pd['id']][$pr['id']]['wip'] ?? 0;
                                        $exStore = $existingSto[$pd['id']][$pr['id']]['store'] ?? 0;
                                        $exNg    = $existingSto[$pd['id']][$pr['id']]['ng'] ?? 0;
                                        $exShip  = $existingSto[$pd['id']][$pr['id']]['ship'] ?? 0;
                                    ?>
                                    <?php if($pr['id'] == 12): ?>
                                        <td class="p-1 border-start border-secondary-subtle bg-light">
                                            <input type="number" class="form-control form-control-sm qty-input qty-ship"
                                                   name="sto[<?= $pd['id'] ?>][<?= $pr['id'] ?>][ship]"
                                                   min="0" step="1" value="<?= esc($valShip) ?>"
                                                   placeholder="0" data-type="ship"
                                                   <?= ($valShip && $valShip != $exShip) ? 'style="background-color:#fff3cd"' : '' ?>>
                                        </td>
                                        <td class="p-1 bg-light">
                                            <input type="number" class="form-control form-control-sm qty-input qty-store"
                                                   name="sto[<?= $pd['id'] ?>][<?= $pr['id'] ?>][store]"
                                                   min="0" step="1" value="<?= esc($valStore) ?>"
                                                   placeholder="0" data-type="store"
                                                   <?= ($valStore && $valStore != $exStore) ? 'style="background-color:#fff3cd"' : '' ?>>
                                        </td>
                                    <?php else: ?>
                                        <td class="p-1 border-start border-secondary-subtle">
                                            <input type="number" class="form-control form-control-sm qty-input qty-wip"
                                                   name="sto[<?= $pd['id'] ?>][<?= $pr['id'] ?>][wip]"
                                                   min="0" step="1" value="<?= esc($valWip) ?>"
                                                   placeholder="0" data-type="wip"
                                                   <?= ($valWip && $valWip != $exWip) ? 'style="background-color:#fff3cd"' : '' ?>>
                                        </td>
                                        <td class="p-1">
                                            <input type="number" class="form-control form-control-sm qty-input qty-store"
                                                   name="sto[<?= $pd['id'] ?>][<?= $pr['id'] ?>][store]"
                                                   min="0" step="1" value="<?= esc($valStore) ?>"
                                                   placeholder="0" data-type="store"
                                                   <?= ($valStore && $valStore != $exStore) ? 'style="background-color:#fff3cd"' : '' ?>>
                                        </td>
                                        <td class="p-1">
                                            <input type="number" class="form-control form-control-sm qty-input qty-ng"
                                                   name="sto[<?= $pd['id'] ?>][<?= $pr['id'] ?>][ng]"
                                                   min="0" step="1" value="<?= esc($valNg) ?>"
                                                   placeholder="0" data-type="ng"
                                                   <?= ($valNg && $valNg != $exNg) ? 'style="background-color:#fff3cd"' : '' ?>>
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

        <div class="mt-2 text-muted small fst-italic">
            <i class="bi bi-info-circle me-1"></i> Kolom dengan latar kuning menandakan data baru/berbeda dari yang sudah tersimpan. Anda dapat mengubah angka sebelum menyimpan.
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // ---- Search Filter ----
    const searchInput = document.getElementById('searchProduct');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            document.querySelectorAll('.product-row').forEach(row => {
                const match = !q || row.dataset.partNo.includes(q) || row.dataset.partName.includes(q);
                row.style.display = match ? '' : 'none';
            });
        });
    }

    // ---- Live Summary ----
    function updateSummary() {
        let wip = 0, store = 0, ng = 0, ship = 0;
        document.querySelectorAll('.qty-input').forEach(input => {
            const v = parseFloat(input.value) || 0;
            const t = input.dataset.type;
            if (t === 'wip') wip += v;
            else if (t === 'store') store += v;
            else if (t === 'ng') ng += v;
            else if (t === 'ship') ship += v;
        });
        document.getElementById('summaryWip').textContent = 'WIP: ' + wip.toLocaleString();
        document.getElementById('summaryStore').textContent = 'STORE: ' + store.toLocaleString();
        document.getElementById('summaryNg').textContent = 'NG: ' + ng.toLocaleString();
        document.getElementById('summaryShip').textContent = 'SHIP: ' + ship.toLocaleString();
    }

    document.querySelectorAll('.qty-input').forEach(i => i.addEventListener('input', updateSummary));
    updateSummary();

    // ---- Remove empty inputs before submit to avoid max_input_vars ----
    document.getElementById('stoImportForm').addEventListener('submit', function(e) {
        document.querySelectorAll('.qty-input').forEach(input => {
            if (!input.value || parseFloat(input.value) <= 0) {
                input.removeAttribute('name');
            }
        });
    });
});
</script>

<?= $this->endSection() ?>
