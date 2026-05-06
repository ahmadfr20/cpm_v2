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
                            <th colspan="7" class="text-primary"><?= esc($shift['shift_name']) ?></th>
                        <?php endforeach; ?>
                        <th colspan="5" class="total-col text-dark">TOTAL HARIAN</th>
                    </tr>
                    <tr>
                        <?php foreach ($shifts as $shift): ?>
                            <th class="col-target" style="width: 80px;">Target</th>
                            <th class="col-actual" style="width: 80px;">Actual</th>
                            <th style="width: 80px;">% NG</th>
                            <th style="width: 80px;">DT (m)</th>
                            <th style="width: 80px;">Eff (%)</th>
                            <th style="width: 120px;">Operator</th>
                            <th style="width: 150px;">Remark</th>
                        <?php endforeach; ?>
                        <th class="total-col col-target" style="width: 90px;">Target</th>
                        <th class="total-col col-actual" style="width: 90px;">Actual</th>
                        <th class="total-col" style="width: 80px;">% NG</th>
                        <th class="total-col" style="width: 80px;">DT (m)</th>
                        <th class="total-col" style="width: 100px;">Total Eff</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($summaryData)): ?>
                        <tr>
                            <td colspan="<?= (count($shifts) * 7) + 7 ?>" class="text-muted py-4 text-center">
                                Tidak ada data produksi yang ditemukan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($summaryData as $sectionName => $products): ?>
                            
                            <?php 
                                $rowspan = count($products) > 0 ? count($products) + 2 : 2; 
                            ?>

                                <tr>
                                    <td class="section-name" rowspan="<?= $rowspan ?>" style="position: sticky; left: 0; z-index: 5; background-color: #fdfdfe;">
                                        <?= esc($sectionName) ?>
                                    </td>
                                    <td class="text-center align-middle bg-light text-muted fw-bold" style="position: sticky; left: 150px; z-index: 5; font-size: 0.8rem; background-color: #fdfdfe;">
                                        Nama Operator
                                    </td>
                                    <?php foreach ($shifts as $shift): 
                                        $opStr = $operatorData[$sectionName][$shift['id']] ?? '-';
                                    ?>
                                        <td colspan="7" class="text-center align-middle bg-light text-primary fw-bold" style="font-size: 0.85rem;">
                                            <i class="bi bi-person"></i> <?= esc($opStr ?: '-') ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td colspan="5" class="total-col bg-light"></td>
                                </tr>
                                
                                <tr>
                                    <td class="text-center align-middle bg-light text-muted fw-bold" style="position: sticky; left: 150px; z-index: 5; font-size: 0.8rem; background-color: #fdfdfe;">
                                        Nama Leader
                                    </td>
                                    <?php foreach ($shifts as $shift): 
                                        $ldStr = $leaderData[$sectionName][$shift['id']] ?? '-';
                                    ?>
                                        <td colspan="7" class="text-center align-middle bg-light text-success fw-bold" style="font-size: 0.85rem;">
                                            <i class="bi bi-person-badge"></i> <?= esc($ldStr ?: '-') ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td colspan="5" class="total-col bg-light"></td>
                                </tr>

                            <?php if (empty($products)): ?>
                                <tr>
                                    <td class="text-muted text-center" colspan="<?= (count($shifts) * 7) + 6 ?>">Tidak ada product di section ini hari ini</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $prod): ?>
                                    <tr>

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
                                            <td class="col-actual text-primary fw-bold"><?= number_format($sData['fg']) ?></td>
                                            <td class="text-danger fw-bold"><?= isset($sData['ng_pct']) && $sData['ng_pct'] > 0 ? $sData['ng_pct'].'%' : '-' ?></td>
                                            <td class="text-warning fw-bold"><?= isset($sData['dt']) && $sData['dt'] > 0 ? $sData['dt'] : '-' ?></td>
                                            <td class="col-eff <?= $effClass ?>"><?= $sData['eff'] ?>%</td>
                                            <td class="text-muted small"><?= esc(empty($sData['operator']) ? '-' : $sData['operator']) ?></td>
                                            <td class="text-muted small" style="white-space: normal; min-width: 150px;"><?= esc(empty($sData['remark']) ? '-' : $sData['remark']) ?></td>
                                        <?php endforeach; ?>

                                        <?php 
                                            $tEffClass = '';
                                            if ($prod['total_eff'] >= 95) $tEffClass = 'eff-success';
                                            elseif ($prod['total_eff'] >= 80) $tEffClass = 'eff-warning';
                                            elseif ($prod['total_eff'] > 0) $tEffClass = 'eff-danger';
                                        ?>
                                        <td class="total-col col-target text-muted fw-bold"><?= number_format($prod['total_target']) ?></td>
                                        <td class="total-col col-actual text-primary fw-bold"><?= number_format($prod['total_fg']) ?></td>
                                        <td class="total-col text-danger fw-bold"><?= isset($prod['total_ng_pct']) && $prod['total_ng_pct'] > 0 ? $prod['total_ng_pct'].'%' : '-' ?></td>
                                        <td class="total-col text-warning fw-bold"><?= isset($prod['total_dt']) && $prod['total_dt'] > 0 ? $prod['total_dt'] : '-' ?></td>
                                        <td class="total-col col-eff <?= $tEffClass ?>">
                                            <?= $prod['total_eff'] ?>%
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Machine Performance Sub-section with NG/DT names -->
                            <?php 
                            $secMachines = $machinePerf[$sectionName] ?? [];
                            $totalColSpan = (count($shifts) * 7) + 6;
                            if (!empty($secMachines)): 
                            ?>
                            <tr>
                                <td colspan="<?= $totalColSpan ?>" class="p-0" style="position: sticky; left: 150px; z-index: 3; background: #fff;">
                                    <div style="background:linear-gradient(90deg, #f0f9ff, #f8fafc); padding: 8px 12px;">
                                        <div style="font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:#0369a1; margin-bottom:6px;">
                                            <i class="bi bi-display me-1"></i>Per Mesin — <?= esc($sectionName) ?>
                                        </div>
                                        <table class="table table-sm table-bordered mb-0" style="font-size:.75rem; background:#fff;">
                                            <thead>
                                                <tr style="background:#1e293b; color:#e2e8f0;">
                                                    <th class="text-start" style="width:100px;">Mesin</th>
                                                    <th style="width:60px;">Target</th>
                                                    <th style="width:60px;">FG</th>
                                                    <th style="width:55px;">OK%</th>
                                                    <th style="width:55px;">NG</th>
                                                    <th style="width:55px;">NG%</th>
                                                    <th style="width:55px;">DT%</th>
                                                    <th>Detail NG</th>
                                                    <th>Detail Downtime</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($secMachines as $mc): ?>
                                                <tr>
                                                    <td class="fw-bold text-dark"><i class="bi bi-display text-muted me-1"></i><?= esc($mc['machine_code']) ?></td>
                                                    <td class="text-center text-muted"><?= number_format($mc['target']) ?></td>
                                                    <td class="text-center fw-bold text-success"><?= number_format($mc['fg']) ?></td>
                                                    <td class="text-center fw-bold" style="color:<?= $mc['ok_achievement'] >= 95 ? '#16a34a' : ($mc['ok_achievement'] >= 75 ? '#f59e0b' : '#dc2626') ?>"><?= $mc['ok_achievement'] ?>%</td>
                                                    <td class="text-center fw-bold text-danger"><?= number_format($mc['ng']) ?></td>
                                                    <td class="text-center text-danger"><?= $mc['ng_rate'] ?>%</td>
                                                    <td class="text-center text-warning fw-bold"><?= $mc['dt_rate'] ?>%</td>
                                                    <td class="text-start" style="white-space:normal;">
                                                        <?php 
                                                        $ngDets = $mc['ng_details'] ?? [];
                                                        if (!empty($ngDets)):
                                                            foreach ($ngDets as $ngName => $ngQty): ?>
                                                            <span style="display:inline-block; background:#fee2e2; color:#dc2626; padding:1px 5px; border-radius:4px; font-weight:700; font-size:.65rem; margin:1px;"><?= esc($ngName) ?>: <?= $ngQty ?></span>
                                                        <?php endforeach; 
                                                        else: ?>
                                                            <span class="text-muted" style="font-size:.68rem;">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-start" style="white-space:normal;">
                                                        <?php 
                                                        $dtDets = $mc['dt_details'] ?? [];
                                                        if (!empty($dtDets)):
                                                            foreach ($dtDets as $dtName => $dtMins): ?>
                                                            <span style="display:inline-block; background:#fef3c7; color:#92400e; padding:1px 5px; border-radius:4px; font-weight:700; font-size:.65rem; margin:1px;"><?= esc($dtName) ?>: <?= $dtMins ?>m</span>
                                                        <?php endforeach;
                                                        else: ?>
                                                            <span class="text-muted" style="font-size:.68rem;">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>

                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>