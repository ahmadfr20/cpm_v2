<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
.stock-display {
    font-size: 2.2rem;
    font-weight: 900;
    letter-spacing: -1px;
}
.stock-ok { color: #16a34a; }
.stock-low { color: #d97706; }
.stock-empty { color: #dc2626; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">RAW MATERIAL</h4>
        <h5 class="mb-0 text-muted">REQUEST INGOT</h5>
        <small class="text-secondary">Setiap request akan langsung mengurangi stok Ingot (Kg)</small>
    </div>
    <div class="text-end">
        <div class="text-muted small fw-bold mb-1">STOK INGOT SAAT INI</div>
        <div class="stock-display <?= $currentStockKg > 500 ? 'stock-ok' : ($currentStockKg > 100 ? 'stock-low' : 'stock-empty') ?>" id="stockDisplay">
            <?= number_format($currentStockKg, 3) ?> <span style="font-size:1rem;" class="text-muted">Kg</span>
        </div>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill me-2"></i><?= esc(session()->getFlashdata('success')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= esc(session()->getFlashdata('error')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif ?>

<!-- FORM REQUEST -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-primary text-white fw-bold py-3">
        <i class="bi bi-plus-circle me-2"></i>Form Request Ingot
    </div>
    <div class="card-body">
        <form method="post" action="<?= site_url('/raw-material/request-ingot/store') ?>" id="requestForm">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="fw-bold mb-1">Tanggal Request</label>
                    <input type="date" name="request_date" class="form-control" value="<?= esc($date) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="fw-bold mb-1">Shift</label>
                    <select name="shift_id" class="form-select">
                        <option value="">-- Pilih Shift --</option>
                        <?php foreach ($shifts as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= esc($s['shift_name']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="fw-bold mb-1">Mesin</label>
                    <select name="machine_id" class="form-select">
                        <option value="">-- Pilih Mesin --</option>
                        <?php foreach ($machines as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= esc($m['machine_code']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="fw-bold mb-1 text-danger">Berat Ingot (Kg) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" name="weight_kg" id="weightKgInput"
                               class="form-control form-control-lg fw-bold text-center text-danger"
                               min="0.001" step="0.001" placeholder="0.000" required
                               onchange="validateStock(this)">
                        <span class="input-group-text fw-bold">Kg</span>
                    </div>
                    <small id="stockWarning" class="text-danger d-none"><i class="bi bi-exclamation-triangle"></i> Melebihi stok!</small>
                </div>
                <div class="col-md-2">
                    <label class="fw-bold mb-1">Diminta Oleh</label>
                    <input type="text" name="requested_by" class="form-control" placeholder="Nama operator/leader" maxlength="100">
                </div>
                <div class="col-md-2">
                    <label class="fw-bold mb-1">Catatan</label>
                    <input type="text" name="notes" class="form-control" placeholder="Opsional..." maxlength="255">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-danger btn-lg fw-bold px-5" id="btnSubmit">
                        <i class="bi bi-dash-circle me-2"></i>Kurangi Stok & Simpan Request
                    </button>
                    <small class="text-muted ms-3">Stok Ingot akan langsung berkurang sesuai berat yang dimasukkan.</small>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Request Hari Ini -->
<?php if (!empty($todayRequests)): ?>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-warning text-dark fw-bold py-2">
        <i class="bi bi-clock me-1"></i> Request Hari Ini (<?= date('d M Y', strtotime($date)) ?>) — Total: 
        <strong><?= number_format(array_sum(array_column($todayRequests, 'weight_kg')), 3) ?> Kg</strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0 text-center align-middle">
                <thead class="table-light">
                    <tr>
                        <th>No</th><th>Shift</th><th>Mesin</th>
                        <th class="text-danger">Berat (Kg)</th>
                        <th>Diminta Oleh</th><th>Catatan</th><th>Waktu Input</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todayRequests as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= esc($r['shift_name'] ?? '-') ?></td>
                        <td><?= esc($r['machine_code'] ?? '-') ?></td>
                        <td class="fw-bold text-danger"><?= number_format((float)$r['weight_kg'], 3) ?></td>
                        <td><?= esc($r['requested_by'] ?? '-') ?></td>
                        <td class="text-muted small"><?= esc($r['notes'] ?? '-') ?></td>
                        <td class="text-muted small"><?= date('H:i:s', strtotime($r['created_at'])) ?></td>
                    </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif ?>

<!-- History -->
<h5 class="mt-4 mb-3 fw-bold border-bottom pb-2">
    <i class="bi bi-clock-history me-2"></i>History Request Ingot (50 Terakhir)
</h5>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-striped mb-0 align-middle text-center">
                <thead class="table-secondary">
                    <tr>
                        <th>No</th><th>Tanggal</th><th>Shift</th><th>Mesin</th>
                        <th class="text-danger">Berat (Kg)</th>
                        <th>Diminta Oleh</th><th>Catatan</th><th>Waktu Input</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr><td colspan="8" class="text-muted py-4">Belum ada history request.</td></tr>
                    <?php else: ?>
                        <?php foreach ($history as $i => $h): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= date('d M Y', strtotime($h['request_date'])) ?></strong></td>
                            <td><?= esc($h['shift_name'] ?? '-') ?></td>
                            <td><?= esc($h['machine_code'] ?? '-') ?></td>
                            <td class="fw-bold text-danger"><?= number_format((float)$h['weight_kg'], 3) ?></td>
                            <td><?= esc($h['requested_by'] ?? '-') ?></td>
                            <td class="text-muted small"><?= esc($h['notes'] ?? '-') ?></td>
                            <td class="text-muted small"><?= date('d/m H:i', strtotime($h['created_at'])) ?></td>
                        </tr>
                        <?php endforeach ?>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const currentStockKg = <?= (float)$currentStockKg ?>;

function validateStock(input) {
    const val = parseFloat(input.value) || 0;
    const warning = document.getElementById('stockWarning');
    const btn = document.getElementById('btnSubmit');
    if (val > currentStockKg) {
        warning.classList.remove('d-none');
        btn.disabled = true;
    } else {
        warning.classList.add('d-none');
        btn.disabled = false;
    }
}

document.getElementById('requestForm').addEventListener('submit', function(e) {
    const val = parseFloat(document.getElementById('weightKgInput').value) || 0;
    if (val <= 0) {
        e.preventDefault();
        alert('Berat Ingot harus lebih dari 0 Kg.');
        return;
    }
    if (val > currentStockKg) {
        e.preventDefault();
        alert('Berat yang diminta (' + val.toFixed(3) + ' Kg) melebihi stok yang tersedia (' + currentStockKg.toFixed(3) + ' Kg).');
        return;
    }
    if (!confirm('Konfirmasi: Kurangi stok Ingot sebesar ' + val.toFixed(3) + ' Kg?')) {
        e.preventDefault();
    }
});
</script>

<?= $this->endSection() ?>
