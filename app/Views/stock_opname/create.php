<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
.sto-input-table thead th {
    vertical-align: middle;
    text-align: center;
    font-size: 0.82rem;
    white-space: nowrap;
}
.sto-input-table tbody td {
    vertical-align: middle;
    padding: 0.35rem 0.5rem;
}
.sto-input-table input[type="number"] {
    text-align: right;
    font-weight: 600;
    min-width: 80px;
}
.sto-input-table input[type="number"]:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13,110,253,.15);
}
.sto-input-table .row-no {
    font-size: 0.8rem;
    color: #888;
    text-align: center;
    width: 40px;
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

.badge-header-wip   { background: #0d6efd; }
.badge-header-store { background: #198754; }
.badge-header-ng    { background: #dc3545; }

#searchProduct {
    max-width: 320px;
}
.highlight-row td { background-color: #fffbe6 !important; }

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
    background-color: #212529; /* Warna gelap sesuai thead table-dark */
    z-index: 11;
    border-right: 1px solid #495057;
}
.sto-input-table tbody .sticky-col {
    border-right: 2px solid #dee2e6;
}
.sticky-no { left: 0; min-width: 50px; }
.sticky-partno { left: 50px; min-width: 120px; }
.sticky-partname { left: 170px; min-width: 250px; }
</style>

<div class="container-fluid py-3">
    <!-- Page Header -->
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h4 class="mb-0 fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Input STO Manual</h4>
            <p class="text-muted mb-0 small">Isi qty pada kolom yang sesuai, kolom kosong tidak akan disimpan.</p>
        </div>
        <div class="col-auto">
            <a href="/sto" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        </div>
    </div>

    <?php if (session()->getFlashdata('error')) : ?>
        <div class="alert alert-danger fw-bold"><i class="bi bi-exclamation-circle me-2"></i><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <form action="/sto/storeManual" method="post" id="stoForm">
        <?= csrf_field() ?>

        <!-- Settings Bar -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body py-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold mb-1">Tanggal STO <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="production_date" id="production_date" required value="<?= esc($date) ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold mb-1">Cari Produk</label>
                        <input type="text" class="form-control" id="searchProduct" placeholder="Ketik part no / nama...">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnResetAll" title="Reset semua qty">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm fw-bold flex-fill" id="btnSave">
                            <i class="bi bi-save me-1"></i> Simpan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Bar -->
        <div class="d-flex gap-3 mb-2 align-items-center flex-wrap">
            <span class="badge rounded-pill bg-secondary fs-6" id="countFilled">0 produk diisi</span>
            <span class="small text-muted">
                WIP: <strong class="text-primary" id="totalWip">0</strong> &nbsp;|&nbsp;
                STORE: <strong class="text-success" id="totalStore">0</strong> &nbsp;|&nbsp;
                NG: <strong class="text-danger" id="totalNg">0</strong> &nbsp;|&nbsp;
                SHIP: <strong class="text-warning text-dark" id="totalShip">0</strong>
            </span>
            <div class="ms-auto">
                <button type="button" class="btn btn-xs btn-outline-primary btn-sm" id="btnShowFilledOnly">
                    <i class="bi bi-funnel me-1"></i>Tampilkan yang diisi saja
                </button>
            </div>
        </div>

        <!-- Product Table -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive sticky-header" style="max-height: 70vh; overflow-y: auto;">
                    <table class="table table-bordered table-hover mb-0 sto-input-table" id="stoTable">
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
                                        $valWip   = $stoData[$pd['id']][$pr['id']]['wip'] ?? '';
                                        $valStore = $stoData[$pd['id']][$pr['id']]['store'] ?? '';
                                        $valNg    = $stoData[$pd['id']][$pr['id']]['ng'] ?? '';
                                        $valShip  = $stoData[$pd['id']][$pr['id']]['ship'] ?? '';
                                    ?>
                                    <?php if($pr['id'] == 12): ?>
                                        <td class="p-1 border-start border-secondary-subtle bg-light">
                                            <input type="number" class="form-control form-control-sm qty-ship qty-input"
                                                   name="sto[<?= $pd['id'] ?>][<?= $pr['id'] ?>][ship]"
                                                   min="0" step="1" value="<?= esc($valShip) ?>"
                                                   placeholder="0"
                                                   data-type="ship">
                                        </td>
                                        <td class="p-1 bg-light">
                                            <input type="number" class="form-control form-control-sm qty-store qty-input"
                                                   name="sto[<?= $pd['id'] ?>][<?= $pr['id'] ?>][store]"
                                                   min="0" step="1" value="<?= esc($valStore) ?>"
                                                   placeholder="0"
                                                   data-type="store">
                                        </td>
                                    <?php else: ?>
                                        <td class="p-1 border-start border-secondary-subtle">
                                            <input type="number" class="form-control form-control-sm qty-wip qty-input"
                                                   name="sto[<?= $pd['id'] ?>][<?= $pr['id'] ?>][wip]"
                                                   min="0" step="1" value="<?= esc($valWip) ?>"
                                                   placeholder="0"
                                                   data-type="wip">
                                        </td>
                                        <td class="p-1">
                                            <input type="number" class="form-control form-control-sm qty-store qty-input"
                                                   name="sto[<?= $pd['id'] ?>][<?= $pr['id'] ?>][store]"
                                                   min="0" step="1" value="<?= esc($valStore) ?>"
                                                   placeholder="0"
                                                   data-type="store">
                                        </td>
                                        <td class="p-1">
                                            <input type="number" class="form-control form-control-sm qty-ng qty-input"
                                                   name="sto[<?= $pd['id'] ?>][<?= $pr['id'] ?>][ng]"
                                                   min="0" step="1" value="<?= esc($valNg) ?>"
                                                   placeholder="0"
                                                   data-type="ng">
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

        <!-- Bottom Submit -->
        <div class="d-flex justify-content-between align-items-center mt-3">
            <span class="text-muted small">Total <?= count($products) ?> produk ditampilkan.</span>
            <button type="submit" class="btn btn-primary fw-bold px-4" id="btnSaveBottom">
                <i class="bi bi-save me-1"></i>Simpan STO Manual
            </button>
        </div>

    </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const searchInput   = document.getElementById('searchProduct');
    const tbody         = document.getElementById('productTableBody');
    const rows          = tbody.querySelectorAll('.product-row');
    const countFilled   = document.getElementById('countFilled');
    const totalWip      = document.getElementById('totalWip');
    const totalStore    = document.getElementById('totalStore');
    const totalNg       = document.getElementById('totalNg');
    const totalShip     = document.getElementById('totalShip');
    const btnReset      = document.getElementById('btnResetAll');
    const btnFilter     = document.getElementById('btnShowFilledOnly');
    let showFilledOnly  = false;

    // ---- Search Filter ----
    searchInput.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        rows.forEach(row => {
            if (showFilledOnly && !rowHasValue(row)) {
                row.style.display = 'none';
                return;
            }
            const partNo   = row.dataset.partNo   || '';
            const partName = row.dataset.partName || '';
            row.style.display = (!q || partNo.includes(q) || partName.includes(q)) ? '' : 'none';
        });
    });

    // ---- Show Filled Only Toggle ----
    btnFilter.addEventListener('click', function () {
        showFilledOnly = !showFilledOnly;
        this.classList.toggle('btn-primary', showFilledOnly);
        this.classList.toggle('btn-outline-primary', !showFilledOnly);
        this.innerHTML = showFilledOnly
            ? '<i class="bi bi-funnel-fill me-1"></i>Tampilkan semua'
            : '<i class="bi bi-funnel me-1"></i>Tampilkan yang diisi saja';
        applyFilter();
    });

    function rowHasValue(row) {
        return [...row.querySelectorAll('.qty-input')].some(i => parseFloat(i.value) > 0);
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

    // ---- Reload on Date Change ----
    document.getElementById('production_date').addEventListener('change', function() {
        if (this.value) {
            window.location.href = '?date=' + this.value;
        }
    });

    // ---- Live Summary ----
    function updateSummary() {
        let filled = 0, wip = 0, store = 0, ng = 0, ship = 0;
        rows.forEach(row => {
            let rowFilled = false;
            row.querySelectorAll('.qty-input').forEach(input => {
                const val = parseFloat(input.value) || 0;
                if (val > 0) {
                    rowFilled = true;
                    if (input.dataset.type === 'wip') wip += val;
                    if (input.dataset.type === 'store') store += val;
                    if (input.dataset.type === 'ng') ng += val;
                    if (input.dataset.type === 'ship') ship += val;
                }
            });
            if (rowFilled) filled++;

            // Highlight rows with value
            row.classList.toggle('highlight-row', rowFilled);
        });
        countFilled.textContent = filled + ' produk diisi';
        countFilled.className   = 'badge rounded-pill fs-6 ' + (filled > 0 ? 'bg-primary' : 'bg-secondary');
        totalWip.textContent    = wip.toLocaleString('id-ID');
        totalStore.textContent  = store.toLocaleString('id-ID');
        totalNg.textContent     = ng.toLocaleString('id-ID');
        if(totalShip) totalShip.textContent   = ship.toLocaleString('id-ID');
    }

    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('input', updateSummary);

        // Enter key moves to next field in same column
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const col   = this.dataset.type;
                const allInCol = [...document.querySelectorAll(`.qty-${col}`)];
                const idx   = allInCol.indexOf(this);
                if (idx < allInCol.length - 1) {
                    allInCol[idx + 1].focus();
                    allInCol[idx + 1].select();
                }
            }
        });
    });

    // ---- Reset All ----
    btnReset.addEventListener('click', function () {
        if (!confirm('Reset semua qty ke 0?')) return;
        document.querySelectorAll('.qty-input').forEach(i => i.value = '');
        updateSummary();
        if (showFilledOnly) applyFilter();
    });

    // ---- Form Validation ----
    document.getElementById('stoForm').addEventListener('submit', function (e) {
        const date      = document.getElementById('production_date').value;
        if (!date) {
            e.preventDefault();
            alert('Tanggal harus diisi terlebih dahulu!');
            return;
        }
        const anyFilled = [...document.querySelectorAll('.qty-input')].some(i => parseFloat(i.value) > 0);
        // We removed the 'anyFilled' validation block here so users can clear inputs if they want.

        // Tweak: Prevent 'Input variables exceeded 1000' limit in PHP
        // Remove the name attribute from empty or zero inputs so they are not sent in the POST request
        document.querySelectorAll('.qty-input').forEach(input => {
            if (!input.value || parseFloat(input.value) <= 0) {
                input.removeAttribute('name');
            }
        });
    });

    updateSummary();
});
</script>

<?= $this->endSection() ?>
