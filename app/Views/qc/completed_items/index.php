<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
  .card-box {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
  }
  .stat-card {
    text-align: center;
    padding: 15px;
    border-radius: 10px;
    color: white;
  }
  .stat-card h3 {
    margin: 0;
    font-size: 24px;
    font-weight: 900;
  }
  .stat-card p {
    margin: 0;
    font-size: 13px;
    font-weight: 600;
    opacity: 0.9;
  }
  .table-custom th {
    background-color: #f8fafc;
    color: #475569;
    font-weight: 800;
    text-transform: uppercase;
    font-size: 12px;
  }
  .table-custom td {
    vertical-align: middle;
    font-size: 14px;
  }
  .ng-badge {
    background-color: #fee2e2;
    color: #991b1b;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 12px;
  }
  .ok-badge {
    background-color: #dcfce7;
    color: #166534;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 12px;
  }
  
  @media print {
      body * {
          visibility: hidden;
      }
      .card-box, .card-box *, .table-custom, .table-custom *, .stat-card, .stat-card *, h4, h4 *, small, small * {
          visibility: visible;
      }
      .card-box, .stat-card {
          position: static !important;
          box-shadow: none !important;
      }
      form, button, a.btn {
          display: none !important;
      }
      .row {
          display: flex;
          flex-wrap: wrap;
      }
      .col-md-3 {
          flex: 0 0 25%;
          max-width: 25%;
      }
  }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0 text-dark"><i class="bi bi-shield-check me-2 text-primary"></i>QC Completed Items</h4>
        <small class="text-muted fw-bold">History item yang telah diinspeksi oleh tim QC</small>
    </div>
    <div class="d-flex gap-2">
        <a href="/qc/completed-items/export?filter_type=<?= esc($filterType) ?>&filter_value=<?= esc($filterValue) ?>&shift_id=<?= $shiftId ?>" class="btn btn-outline-success btn-sm fw-bold shadow-sm">
            <i class="bi bi-file-earmark-excel"></i> Export Excel
        </a>
        <button class="btn btn-outline-danger btn-sm fw-bold shadow-sm" onclick="window.print()">
            <i class="bi bi-file-earmark-pdf"></i> Export PDF
        </button>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
            <h3><?= number_format($totalInspected) ?></h3>
            <p>TOTAL INSPECTED</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #10b981, #059669);">
            <h3><?= number_format($totalOk) ?></h3>
            <p>TOTAL PASS (OK)</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <h3><?= number_format($totalNg) ?></h3>
            <p>TOTAL NG</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <h3><?= $ngPercentage ?>%</h3>
            <p>PERSENTASE NG</p>
        </div>
    </div>
</div>

<div class="card-box mb-4">
    <form method="get" class="row gx-2 gy-2 align-items-end" id="filterForm">
        <div class="col-md-2">
            <label class="form-label text-muted fw-bold small">Tipe Filter</label>
            <select name="filter_type" id="filterTypeSelect" class="form-select form-select-sm" onchange="updateFilterInput()">
                <option value="day" <?= $filterType == 'day' ? 'selected' : '' ?>>Hari</option>
                <option value="week" <?= $filterType == 'week' ? 'selected' : '' ?>>Minggu</option>
                <option value="month" <?= $filterType == 'month' ? 'selected' : '' ?>>Bulan</option>
                <option value="year" <?= $filterType == 'year' ? 'selected' : '' ?>>Tahun</option>
            </select>
        </div>
        <div class="col-md-3" id="filterValueContainer">
            <label class="form-label text-muted fw-bold small">Pilih Waktu</label>
            <input type="month" name="filter_value" id="filterValueInput" class="form-control form-control-sm" value="<?= esc($filterValue) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label text-muted fw-bold small">Shift Produksi</label>
            <select name="shift_id" class="form-select form-select-sm">
                <option value="">-- Semua Shift --</option>
                <?php foreach($shifts as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $shiftId == $s['id'] ? 'selected' : '' ?>><?= esc($s['shift_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-auto">
            <button type="submit" class="btn btn-primary btn-sm fw-bold px-4"><i class="bi bi-funnel me-1"></i> Filter</button>
            <a href="/qc/completed-items" class="btn btn-light btn-sm fw-bold border px-3">Reset</a>
        </div>
    </form>
</div>

<div class="card-box">
    <div class="table-responsive">
        <table class="table table-hover table-custom" id="dataTable">
            <thead>
                <tr>
                    <th>Part Number</th>
                    <th>Part Name</th>
                    <th class="text-center">Qty Inspected</th>
                    <th class="text-center">Qty OK (PASS)</th>
                    <th class="text-center">Qty NG</th>
                    <th class="text-center">NG %</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($completedItems)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Belum ada produk untuk ditampilkan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($completedItems as $item): ?>
                        <?php $itemNgPerc = $item['qty_in'] > 0 ? round(($item['qty_ng'] / $item['qty_in']) * 100, 2) : 0; ?>
                        <tr>
                            <td class="fw-bold text-primary"><?= esc($item['part_no']) ?></td>
                            <td class="text-muted fw-bold" style="font-size:12px;"><?= esc($item['part_name']) ?></td>
                            <td class="text-center fw-bold"><?= number_format($item['qty_in']) ?></td>
                            <td class="text-center"><span class="ok-badge"><?= number_format($item['qty_ok']) ?></span></td>
                            <td class="text-center"><span class="<?= $item['qty_ng'] > 0 ? 'ng-badge' : 'text-muted fw-bold' ?>"><?= number_format($item['qty_ng']) ?></span></td>
                            <td class="text-center fw-bold text-danger"><?= $itemNgPerc ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function updateFilterInput() {
    const type = document.getElementById('filterTypeSelect').value;
    const input = document.getElementById('filterValueInput');
    
    // Clear current value if switching types dynamically
    // The previous value might be invalid for the new type
    
    if (type === 'day') {
        input.type = 'date';
    } else if (type === 'week') {
        input.type = 'week';
    } else if (type === 'year') {
        input.type = 'number';
        input.min = '2000';
        input.max = '2099';
        input.placeholder = 'YYYY';
    } else {
        input.type = 'month';
    }
}

// Initial call to set correct type on page load
updateFilterInput();
</script>

<?= $this->endSection() ?>
