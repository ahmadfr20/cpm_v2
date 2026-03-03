<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
    .asakai-table th, .asakai-table td {
        vertical-align: middle;
        text-align: center;
        padding: 10px 8px;
        white-space: nowrap;
    }
    .asakai-table thead th {
        background-color: #f8f9fa;
        font-weight: 700;
        border-bottom: 2px solid #dee2e6;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .asakai-table tbody td.section-name {
        text-align: center;
        font-weight: 900;
        background-color: #fdfdfe;
        vertical-align: middle;
    }
    .col-target { background-color: #f4f6f9; }
    .col-actual { background-color: #eef2f5; font-weight: 600; }
    .col-eff    { font-weight: 700; }
    
    .eff-success { color: #198754; }
    .eff-warning { color: #ffc107; }
    .eff-danger  { color: #dc3545; }
    
    .total-col {
        border-left: 2px solid #dee2e6 !important;
        background-color: #fff8e5;
    }
</style>

<div class="d-flex align-items-center mb-4">
    <div class="bg-primary text-white rounded p-2 me-3 shadow-sm">
        <i class="bi bi-sunrise fs-4"></i>
    </div>
    <div>
        <h3 class="mb-0 fw-bold">ASAKAI Dashboard</h3>
        <span class="text-muted">Daily Production Summary Overview (By Product & Shift)</span>
    </div>
</div>

<div class="card shadow-sm mb-4 border-0 rounded-3">
    <div class="card-body bg-light rounded-3 p-3">
        <form method="get" action="<?= site_url('dashboard/asakai') ?>" id="filterForm" class="row g-3 align-items-center">
            
            <div class="col-auto">
                <label class="form-label mb-0 fw-bold text-secondary" style="font-size:14px;">Date</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-calendar3"></i></span>
                    <input type="date" name="date" value="<?= esc($date) ?>" class="form-control fw-bold" onchange="document.getElementById('filterForm').submit()">
                </div>
            </div>

            <div class="col-auto">
                <label class="form-label mb-0 fw-bold text-secondary" style="font-size:14px;">Section</label>
                <select name="section" class="form-select form-select-sm fw-bold" onchange="document.getElementById('filterForm').submit()" style="min-width: 200px;">
                    <option value="">-- All Sections --</option>
                    <?php foreach ($sectionsList as $sec): ?>
                        <option value="<?= esc($sec) ?>" <?= $selectedSec === $sec ? 'selected' : '' ?>>
                            <?= esc($sec) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-auto ms-auto d-flex gap-2">
                <?php if ($selectedSec !== ''): ?>
                    <a href="<?= site_url('dashboard/asakai') ?>" class="btn btn-sm btn-outline-secondary fw-bold">
                        <i class="bi bi-x-circle"></i> Clear Filter
                    </a>
                <?php endif; ?>
                
                <button type="submit" name="export" value="excel" class="btn btn-sm btn-success fw-bold">
                    <i class="bi bi-file-earmark-excel"></i> Export Excel
                </button>
            </div>
            
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 70vh;">
            <table class="table table-bordered asakai-table mb-0">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 150px; position: sticky; left: 0; z-index: 15;">Section</th>
                        <th rowspan="2" style="width: 250px; position: sticky; left: 150px; z-index: 15;">Product Part</th>
                        <?php foreach ($shifts as $shift): ?>
                            <th colspan="3" class="text-primary"><?= esc($shift['shift_name']) ?></th>
                        <?php endforeach; ?>
                        <th colspan="3" class="total-col text-dark">TOTAL HARIAN</th>
                    </tr>
                    <tr>
                        <?php foreach ($shifts as $shift): ?>
                            <th class="col-target" style="width: 80px;">Target</th>
                            <th class="col-actual" style="width: 80px;">Actual</th>
                            <th style="width: 80px;">Eff (%)</th>
                        <?php endforeach; ?>
                        <th class="total-col col-target" style="width: 90px;">Target</th>
                        <th class="total-col col-actual" style="width: 90px;">Actual</th>
                        <th class="total-col" style="width: 100px;">Total Eff</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($summaryData)): ?>
                        <tr>
                            <td colspan="<?= (count($shifts) * 3) + 5 ?>" class="text-muted py-4 text-center">
                                Tidak ada data produksi yang ditemukan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($summaryData as $sectionName => $products): ?>
                            
                            <?php 
                                $rowspan = count($products) > 0 ? count($products) : 1; 
                                $firstRow = true;
                            ?>

                            <?php if (empty($products)): ?>
                                <tr>
                                    <td class="section-name" style="position: sticky; left: 0; z-index: 5;"><?= esc($sectionName) ?></td>
                                    <td class="text-muted text-center" colspan="<?= (count($shifts) * 3) + 4 ?>">Tidak ada product di section ini hari ini</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $prod): ?>
                                    <tr>
                                        <?php if ($firstRow): ?>
                                            <td class="section-name" rowspan="<?= $rowspan ?>" style="position: sticky; left: 0; z-index: 5;">
                                                <?= esc($sectionName) ?>
                                            </td>
                                            <?php $firstRow = false; ?>
                                        <?php endif; ?>

                                        <td class="text-start" style="position: sticky; left: 150px; z-index: 5; background-color: #fff;">
                                            <div class="fw-bold text-dark"><?= esc($prod['part_no']) ?></div>
                                            <div class="small text-muted text-truncate" style="max-width: 230px;" title="<?= esc($prod['part_name']) ?>">
                                                <?= esc($prod['part_name']) ?>
                                            </div>
                                        </td>
                                        
                                        <?php foreach ($shifts as $shift): ?>
                                            <?php 
                                                $shiftId = $shift['id'];
                                                $sData   = $prod['shifts'][$shiftId] ?? ['target' => 0, 'fg' => 0, 'eff' => 0];
                                                
                                                $effClass = '';
                                                if ($sData['eff'] >= 95) $effClass = 'eff-success';
                                                elseif ($sData['eff'] >= 80) $effClass = 'eff-warning';
                                                elseif ($sData['eff'] > 0) $effClass = 'eff-danger';
                                            ?>
                                            <td class="col-target text-muted"><?= number_format($sData['target']) ?></td>
                                            <td class="col-actual text-primary"><?= number_format($sData['fg']) ?></td>
                                            <td class="col-eff <?= $effClass ?>"><?= $sData['eff'] ?>%</td>
                                        <?php endforeach; ?>

                                        <?php 
                                            $tEffClass = '';
                                            if ($prod['total_eff'] >= 95) $tEffClass = 'eff-success';
                                            elseif ($prod['total_eff'] >= 80) $tEffClass = 'eff-warning';
                                            elseif ($prod['total_eff'] > 0) $tEffClass = 'eff-danger';
                                        ?>
                                        <td class="total-col col-target text-muted fw-bold"><?= number_format($prod['total_target']) ?></td>
                                        <td class="total-col col-actual text-primary fw-bold"><?= number_format($prod['total_fg']) ?></td>
                                        <td class="total-col col-eff <?= $tEffClass ?>">
                                            <?= $prod['total_eff'] ?>%
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>