<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<div class="row mb-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="mb-0 text-primary fw-bold"><i class="bi bi-tools me-2"></i>Dandori Report Dashboard</h4>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-success btn-sm fw-bold" onclick="exportGenericExcel()">
                    <i class="bi bi-file-earmark-excel"></i> Export Excel
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm fw-bold" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print / PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Filter Panel -->
<div class="card shadow-sm border-0 rounded-4 mb-3">
    <div class="card-body py-3">
        <form method="get" id="filterForm" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="fw-bold mb-1 small text-muted">RENTANG WAKTU</label>
                <select name="date_range" id="dateRangeSelect" class="form-select form-select-sm" onchange="handleRangeChange(this)">
                    <option value="today"  <?= $date_range === 'today'  ? 'selected' : '' ?>>Hari Ini</option>
                    <option value="last5"  <?= $date_range === 'last5'  ? 'selected' : '' ?>>5 Hari Terakhir</option>
                    <option value="last7"  <?= $date_range === 'last7'  ? 'selected' : '' ?>>1 Minggu Terakhir (7 Hari)</option>
                    <option value="last14" <?= $date_range === 'last14' ? 'selected' : '' ?>>2 Minggu Terakhir (14 Hari)</option>
                    <option value="last30" <?= $date_range === 'last30' ? 'selected' : '' ?>>1 Bulan Terakhir (30 Hari)</option>
                    <option value="custom" <?= $date_range === 'custom' ? 'selected' : '' ?>>Custom Tanggal</option>
                </select>
            </div>

            <div class="col-md-2 custom-date-wrap <?= $date_range !== 'custom' ? 'd-none' : '' ?>">
                <label class="fw-bold mb-1 small text-muted">DARI TANGGAL</label>
                <input type="date" name="date_from" id="dateFrom" class="form-control form-control-sm"
                       value="<?= esc($date_from) ?>">
            </div>

            <div class="col-md-2 custom-date-wrap <?= $date_range !== 'custom' ? 'd-none' : '' ?>">
                <label class="fw-bold mb-1 small text-muted">SAMPAI TANGGAL</label>
                <input type="date" name="date_to" id="dateTo" class="form-control form-control-sm"
                       value="<?= esc($date_to) ?>">
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm fw-bold w-100">
                    <i class="bi bi-search me-1"></i> Tampilkan
                </button>
            </div>

            <div class="col-md-3 text-end text-muted small">
                <?php if ($date_range === 'today'): ?>
                    Menampilkan data: <strong><?= date('d M Y', strtotime($date_from)) ?></strong>
                <?php else: ?>
                    Menampilkan: <strong><?= date('d M Y', strtotime($date_from)) ?></strong>
                    s/d <strong><?= date('d M Y', strtotime($date_to)) ?></strong>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Data -->
<div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0 text-center">
                <thead class="table-dark">
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Section</th>
                        <th>Shift</th>
                        <th>Mesin / Line</th>
                        <th class="text-start">Part Name</th>
                        <th>Waktu (Slot)</th>
                        <th>Dandori Menit</th>
                        <th>Activity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data)): ?>
                        <tr>
                            <td colspan="9" class="text-muted py-4">Tidak ada data Dandori untuk rentang tanggal ini.</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; $prevDate = ''; foreach ($data as $d): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <?php $tgl = $d['dandori_date'] ?? ''; ?>
                                    <?php if ($tgl && $tgl !== $prevDate): ?>
                                        <span class="badge bg-secondary"><?= date('d M Y', strtotime($tgl)) ?></span>
                                        <?php $prevDate = $tgl; ?>
                                    <?php else: ?>
                                        <span class="text-muted small"><?= $tgl ? date('d/m', strtotime($tgl)) : '-' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $d['section'] === 'Die Casting' ? 'bg-primary' : 'bg-info text-dark' ?>">
                                        <?= esc($d['section']) ?>
                                    </span>
                                </td>
                                <td><span class="fw-bold"><?= esc($d['shift_name']) ?></span></td>
                                <td><?= esc($d['machine_code']) ?> <?= $d['section'] === 'Machining' && !empty($d['line_position']) ? '('.esc($d['line_position']).')' : '' ?></td>
                                <td class="text-start text-dark fw-bold"><?= esc($d['part_no']) ?> <br> <small class="text-muted fw-normal"><?= esc($d['part_name']) ?></small></td>
                                <td>
                                    <?php if (!empty($d['time_start']) && !empty($d['time_end'])): ?>
                                        <span class="badge bg-light text-dark border"><i class="bi bi-clock me-1"></i> <?= esc(substr($d['time_start'], 0, 5)) ?> - <?= esc(substr($d['time_end'], 0, 5)) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="fw-bold text-danger fs-6"><?= (int)$d['dandori_minute'] ?> Min</span></td>
                                <td><span class="badge bg-warning text-dark"><i class="bi bi-tools"></i> <?= esc($d['activity']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function handleRangeChange(sel) {
    const customWraps = document.querySelectorAll('.custom-date-wrap');
    if (sel.value === 'custom') {
        customWraps.forEach(el => el.classList.remove('d-none'));
    } else {
        customWraps.forEach(el => el.classList.add('d-none'));
        document.getElementById('filterForm').submit();
    }
}
</script>

<?= $this->endSection() ?>
