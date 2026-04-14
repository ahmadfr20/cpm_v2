<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
    .bg-gradient-fg { background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%); }
    .fg-card { border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1); margin-bottom: 24px; overflow: hidden; }
    .fg-card-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
    .fg-card-title { font-weight: 800; font-size: 1.125rem; color: #1e293b; margin: 0; }
    .fg-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .fg-table th { background: #f8fafc; color: #64748b; font-weight: 700; text-transform: uppercase; font-size: .72rem; letter-spacing: .05em; padding: .85rem 1.2rem; border-bottom: 2px solid #e2e8f0; }
    .fg-table td { padding: .85rem 1.2rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: .875rem; font-weight: 500; }
    .fg-table tr:hover { background-color: #f8fafc; }
    .badge-stock { background: #d1fae5; color: #059669; padding: .35em .65em; font-weight: 700; border-radius: 6px; }
    .badge-stock.zero { background: #fee2e2; color: #dc2626; }
    .badge-invoice { background: #ede9fe; color: #6d28d9; padding: .35em .65em; font-weight: 700; border-radius: 6px; }
    .item-row { animation: fadeIn .3s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: none; } }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1" style="font-weight:900;color:#0f172a;">Finished Good Delivery</h3>
        <p class="text-muted mb-0" style="font-weight:500;">Pengiriman barang jadi ke Customer.</p>
    </div>
    <form method="get" class="d-flex gap-2">
        <input type="date" name="date" class="form-control form-control-sm fw-bold" value="<?= esc($date) ?>">
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

<!-- ════════════════════════════════════════════
     SECTION 1 — STOCK OVERVIEW
     ════════════════════════════════════════════ -->
<div class="fg-card bg-white">
    <div class="fg-card-header bg-gradient-fg text-white">
        <h4 class="fg-card-title text-white"><i class="bi bi-box-seam-fill me-2"></i> Stock Finished Good Tersedia</h4>
    </div>
    <div class="table-responsive">
        <table class="fg-table">
            <thead>
                <tr>
                    <th>Part No</th>
                    <th>Part Name</th>
                    <th class="text-center">Stock FG</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    $hasStock = false;
                    foreach ($products as $p):
                        $pid = (int)$p['id'];
                        $stock = $availableMap[$pid] ?? 0;
                        if ($stock <= 0) continue;
                        $hasStock = true;
                ?>
                    <tr>
                        <td class="fw-bold text-dark"><?= esc($p['part_no']) ?></td>
                        <td><?= esc($p['part_name']) ?></td>
                        <td class="text-center"><span class="badge-stock fs-6"><?= number_format($stock) ?> Pcs</span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$hasStock): ?>
                    <tr>
                        <td colspan="3" class="text-center py-4 text-muted fw-bold">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            Tidak ada stock Finished Good saat ini.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ════════════════════════════════════════════
     SECTION 2 — DELIVERY FORM
     ════════════════════════════════════════════ -->
<div class="fg-card bg-white">
    <div class="fg-card-header">
        <h4 class="fg-card-title"><i class="bi bi-truck text-primary me-2"></i> Buat Pengiriman Baru</h4>
        <button type="button" class="btn btn-primary btn-sm fw-bold shadow-sm" id="btnAddRow">
            <i class="bi bi-plus-lg me-1"></i> Tambah Item
        </button>
    </div>
    <form method="post" action="<?= site_url('/finished-good/delivery/store') ?>" id="deliveryForm">
        <?= csrf_field() ?>
        <input type="hidden" name="delivery_date" value="<?= esc($date) ?>">
        <div class="table-responsive">
            <table class="fg-table" id="deliveryTable">
                <thead>
                    <tr>
                        <th style="width:40px">No</th>
                        <th style="width:220px">Customer</th>
                        <th>Part No & Name</th>
                        <th style="width:140px" class="text-center">DO Number</th>
                        <th style="width:110px" class="text-center">Ready FG</th>
                        <th style="width:130px" class="text-center">Qty Kirim</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody id="deliveryBody">
                    <tr class="empty-row text-muted">
                        <td colspan="7" class="py-4 text-center fw-bold">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            Klik <strong>"Tambah Item"</strong> untuk menambah baris pengiriman.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="p-3 bg-light d-flex justify-content-end" id="deliveryActions" style="display:none!important;">
            <button type="submit" class="btn btn-success fw-bold shadow px-4">
                <i class="bi bi-truck me-1"></i> Simpan & Kirim
            </button>
        </div>
    </form>
</div>

<!-- ════════════════════════════════════════════
     SECTION 3 — DELIVERY HISTORY
     ════════════════════════════════════════════ -->
<div class="fg-card bg-white">
    <div class="fg-card-header">
        <h4 class="fg-card-title"><i class="bi bi-clock-history text-secondary me-2"></i> Riwayat Pengiriman (<?= date('d/m/Y', strtotime($date)) ?>)</h4>
        <a href="<?= site_url('/finished-good/delivery/export?from=' . date('Y-m-01', strtotime($date)) . '&to=' . $date) ?>" class="btn btn-outline-success btn-sm fw-bold">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
        </a>
    </div>
    <div class="table-responsive">
        <table class="fg-table">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Waktu</th>
                    <th>Oleh</th>
                    <th class="text-center">Total Item</th>
                    <th class="text-center">Total Qty</th>
                    <th>Detail</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted fw-bold">Belum ada pengiriman hari ini.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td><span class="badge-invoice"><?= esc($h['invoice_no']) ?></span></td>
                            <td><?= date('H:i', strtotime($h['created_at'])) ?></td>
                            <td class="fw-bold"><?= esc($h['created_by'] ?? '-') ?></td>
                            <td class="text-center fw-bold"><?= (int)$h['total_items'] ?></td>
                            <td class="text-center fw-bold"><?= number_format((int)$h['total_qty']) ?></td>
                            <td>
                                <ul class="list-unstyled mb-0 small">
                                    <?php foreach ($h['items'] as $it): ?>
                                        <li class="mb-1">
                                            <span class="fw-bold"><?= esc($it['part_no'] ?? '-') ?></span>
                                            → <?= esc($it['customer_name'] ?? '-') ?>
                                            <span class="text-success fw-bold">(<?= number_format((int)$it['qty']) ?> pcs)</span>
                                            <?php if (!empty($it['do_number'])): ?>
                                                <span class="text-muted">DO: <?= esc($it['do_number']) ?></span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <td class="text-center">
                                <a href="<?= site_url('/finished-good/delivery/invoice/' . $h['id']) ?>" target="_blank" class="btn btn-sm btn-outline-primary fw-bold">
                                    <i class="bi bi-printer me-1"></i> Cetak Hasil
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ════════════════════════════════════════════
     SECTION 4 — EXPORT RANGE MODAL
     ════════════════════════════════════════════ -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-3">
      <div class="modal-header bg-success text-white border-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-spreadsheet me-2"></i> Export Data</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="get" action="<?= site_url('/finished-good/delivery/export') ?>">
        <div class="modal-body">
            <label class="form-label fw-bold">Dari Tanggal</label>
            <input type="date" name="from" class="form-control fw-bold mb-3" value="<?= date('Y-m-01') ?>">
            <label class="form-label fw-bold">Sampai Tanggal</label>
            <input type="date" name="to" class="form-control fw-bold" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="modal-footer border-0">
            <button type="submit" class="btn btn-success fw-bold w-100"><i class="bi bi-download me-1"></i> Download CSV</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const products     = <?= json_encode($products) ?>;
const customers    = <?= json_encode($customers) ?>;
const availableMap = <?= json_encode($availableMap) ?>;
let rowCount = 0;

document.getElementById('btnAddRow').addEventListener('click', addRow);

function addRow() {
    rowCount++;
    const tbody = document.getElementById('deliveryBody');
    const emptyRow = tbody.querySelector('.empty-row');
    if (emptyRow) emptyRow.remove();

    let custOpts = '<option value="">-- Customer --</option>';
    customers.forEach(c => {
        custOpts += `<option value="${c.id}">${c.customer_name || c.id}</option>`;
    });

    let prodOpts = '<option value="">-- Produk --</option>';
    products.forEach(p => {
        const stock = availableMap[p.id] || 0;
        if (stock > 0) {
            prodOpts += `<option value="${p.id}" data-stock="${stock}">${p.part_no} - ${p.part_name}</option>`;
        }
    });

    const tr = document.createElement('tr');
    tr.className = 'item-row';
    tr.innerHTML = `
        <td class="row-no fw-bold">${rowCount}</td>
        <td><select name="items[${rowCount}][customer_id]" class="form-select form-select-sm fw-bold" required>${custOpts}</select></td>
        <td><select name="items[${rowCount}][product_id]" class="form-select form-select-sm product-select fw-bold" required>${prodOpts}</select></td>
        <td class="text-center"><span class="badge bg-light text-secondary border px-2 py-1 w-100 fs-7">Auto Generate</span></td>
        <td class="ready-fg text-center fw-bold">-</td>
        <td><input type="number" name="items[${rowCount}][qty]" class="form-control form-control-sm text-center qty-input fw-bold" min="1" placeholder="0" required readonly></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row"><i class="bi bi-x-lg"></i></button></td>
    `;
    tbody.appendChild(tr);
    updateRowNumbers();
    showActions();

    const prodSelect = tr.querySelector('.product-select');
    const inputQty   = tr.querySelector('.qty-input');
    const readyTd    = tr.querySelector('.ready-fg');

    prodSelect.addEventListener('change', function() {
        const opt = this.selectedOptions[0];
        if (!opt || !opt.value) {
            readyTd.innerHTML = '-';
            inputQty.readOnly = true;
            inputQty.value = '';
            return;
        }
        const stock = parseInt(opt.dataset.stock || '0');
        readyTd.innerHTML = `<span class="badge-stock ${stock<=0?'zero':''}">${stock.toLocaleString()} Pcs</span>`;
        if (stock > 0) {
            inputQty.readOnly = false;
            inputQty.setAttribute('max', stock);
            inputQty.dataset.max = stock;
        } else {
            inputQty.readOnly = true;
            inputQty.value = '';
        }
    });

    inputQty.addEventListener('input', function() {
        const max = parseInt(this.dataset.max || '0');
        let v = parseInt(this.value || '0');
        if (v > max) this.value = max;
        if (v <= 0 && this.value !== '') this.value = 1;
    });

    tr.querySelector('.btn-remove-row').addEventListener('click', function() {
        tr.remove();
        updateRowNumbers();
        if (!document.querySelectorAll('#deliveryBody .item-row').length) {
            document.getElementById('deliveryBody').innerHTML = `
                <tr class="empty-row text-muted">
                    <td colspan="7" class="py-4 text-center fw-bold"><i class="bi bi-inbox fs-3 d-block mb-2"></i>Klik <strong>"Tambah Item"</strong> untuk menambah baris pengiriman.</td>
                </tr>`;
            hideActions();
        }
    });
}

function updateRowNumbers() {
    document.querySelectorAll('#deliveryBody .item-row').forEach((r, i) => {
        r.querySelector('.row-no').textContent = i + 1;
    });
}
function showActions() { document.getElementById('deliveryActions').style.display = 'flex'; document.getElementById('deliveryActions').classList.remove('d-none'); }
function hideActions() { document.getElementById('deliveryActions').style.display = 'none'; }
</script>

<?= $this->endSection() ?>
