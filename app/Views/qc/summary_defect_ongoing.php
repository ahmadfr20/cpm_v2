<?= $this->extend('layout/layout'); ?>

<?= $this->section('content'); ?>
<style>
    @media print {
        body * { visibility: hidden; }
        .container-fluid, .container-fluid * { visibility: visible; }
        .container-fluid { position: absolute; left: 0; top: 0; width: 100%; }
        .d-print-none, form, .btn { display: none !important; }
        .card { box-shadow: none !important; border: 1px solid #ddd !important; margin-bottom: 20px; }
        canvas { max-height: 250px !important; }
        .table-responsive { overflow: visible !important; }
        .table { font-size: 9px !important; }
    }
</style>
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="m-0 text-dark">Summary Defect Ongoing (Yearly)</h4>
            <?php if ($productId): ?>
                <div class="d-flex gap-2 d-print-none">
                    <a href="/qc/summary-defect-ongoing/export?year=<?= esc($year) ?>&product_id=<?= $productId ?>" class="btn btn-outline-success btn-sm fw-bold shadow-sm">
                        <i class="bi bi-file-earmark-excel"></i> Export Excel
                    </a>
                    <button class="btn btn-outline-danger btn-sm fw-bold shadow-sm" onclick="window.print()">
                        <i class="bi bi-file-earmark-pdf"></i> Export PDF
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" action="">
                <div class="row align-items-end">
                    <div class="col-md-3 mb-3 mb-md-0">
                        <label for="year" class="form-label">Year</label>
                        <select id="year" name="year" class="form-select">
                            <?php 
                            $currentYear = date('Y');
                            for ($y = $currentYear - 3; $y <= $currentYear + 1; $y++): ?>
                                <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label for="product_id" class="form-label">Product</label>
                        <select name="product_id" id="product_id" class="form-select select2" required>
                            <option value="">-- Select Product --</option>
                            <?php foreach ($products as $prod): ?>
                                <option value="<?= $prod['id'] ?>" <?= $productId == $prod['id'] ? 'selected' : '' ?>>
                                    <?= esc($prod['part_name']) ?> (<?= esc($prod['part_no']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i> Filter</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($productId): ?>

    <!-- Summary Chart -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="m-0">Quality Control Check - Year <?= esc($year) ?></h5>
                </div>
                <div class="card-body">
                    <canvas id="defectChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-sm table-hover mb-0 text-center" style="font-size: 0.85rem; white-space: nowrap;">
                    <thead class="table-dark">
                        <tr>
                            <th class="align-middle text-start" style="min-width: 200px;">Metrics / Month</th>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <th class="align-middle"><?= $monthsLabels[$i] ?></th>
                            <?php endfor; ?>
                            <th class="align-middle bg-secondary text-white">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Shifts Data -->
                        <?php for ($s = 1; $s <= 3; $s++): ?>
                            <tr>
                                <td class="text-start fw-bold text-success">OK Shift <?= $s ?></td>
                                <?php for ($i = 1; $i <= 12; $i++): 
                                    $val = $data[$i]['shifts'][$s]['ok'];
                                ?>
                                    <td><?= $val > 0 ? number_format($val) : '-' ?></td>
                                <?php endfor; ?>
                                <td class="fw-bold bg-light"><?= number_format($yearTotals['shifts'][$s]['ok']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-start fw-bold text-danger">NG Shift <?= $s ?></td>
                                <?php for ($i = 1; $i <= 12; $i++): 
                                    $val = $data[$i]['shifts'][$s]['ng'];
                                ?>
                                    <td <?= $val > 0 ? 'class="text-danger"' : '' ?>><?= $val > 0 ? number_format($val) : '-' ?></td>
                                <?php endfor; ?>
                                <td class="fw-bold text-danger bg-light"><?= number_format($yearTotals['shifts'][$s]['ng']) ?></td>
                            </tr>
                            <tr style="border-bottom: 2px solid #e5e7eb;">
                                <td class="text-start fw-bold text-warning" style="font-size: 11px;">% NG Shift <?= $s ?></td>
                                <?php for ($i = 1; $i <= 12; $i++): 
                                    $ok = $data[$i]['shifts'][$s]['ok'];
                                    $ng = $data[$i]['shifts'][$s]['ng'];
                                    $tot = $ok + $ng;
                                    $pct = $tot > 0 ? ($ng / $tot) * 100 : 0;
                                ?>
                                    <td class="text-warning fw-bold" style="font-size: 11px;"><?= $tot > 0 ? number_format($pct, 1) . '%' : '-' ?></td>
                                <?php endfor; ?>
                                <?php
                                    $totOk = $yearTotals['shifts'][$s]['ok'];
                                    $totNg = $yearTotals['shifts'][$s]['ng'];
                                    $totAll = $totOk + $totNg;
                                    $totPct = $totAll > 0 ? ($totNg / $totAll) * 100 : 0;
                                ?>
                                <td class="fw-bold text-warning bg-light" style="font-size: 11px;"><?= $totAll > 0 ? number_format($totPct, 1) . '%' : '-' ?></td>
                            </tr>
                        <?php endfor; ?>

                        <!-- Total Inspection & Total OK % -->
                        <tr class="table-light">
                            <td class="text-start fw-bold">Total Inspection (QTY)</td>
                            <?php for ($i = 1; $i <= 12; $i++): 
                                $val = $data[$i]['total_inspection'];
                            ?>
                                <td class="fw-bold"><?= $val > 0 ? number_format($val) : '-' ?></td>
                            <?php endfor; ?>
                            <td class="fw-bold fs-6"><?= number_format($yearTotals['total_inspection']) ?></td>
                        </tr>
                        <tr class="table-light">
                            <td class="text-start fw-bold text-success">Total OK (%)</td>
                            <?php for ($i = 1; $i <= 12; $i++): 
                                $insp = $data[$i]['total_inspection'];
                                $ok = $data[$i]['total_ok'];
                                $pct = $insp > 0 ? ($ok / $insp) * 100 : 0;
                            ?>
                                <td class="fw-bold text-success"><?= $insp > 0 ? number_format($pct, 1) . '%' : '-' ?></td>
                            <?php endfor; ?>
                            <?php
                                $totInsp = $yearTotals['total_inspection'];
                                $totOk = $yearTotals['total_ok'];
                                $totPct = $totInsp > 0 ? ($totOk / $totInsp) * 100 : 0;
                            ?>
                            <td class="fw-bold text-success fs-6"><?= number_format($totPct, 1) ?>%</td>
                        </tr>

                        <!-- Rejects -->
                        <tr>
                            <td class="text-start fw-bold text-danger table-warning">Reject Total</td>
                            <?php for ($i = 1; $i <= 12; $i++): 
                                $val = $data[$i]['reject_total'];
                            ?>
                                <td class="fw-bold table-warning text-danger"><?= $val > 0 ? number_format($val) : '-' ?></td>
                            <?php endfor; ?>
                            <td class="fw-bold table-warning text-danger"><?= number_format($yearTotals['reject_total']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-start pe-3" style="padding-left: 2rem;">- Reject Die Casting</td>
                            <?php for ($i = 1; $i <= 12; $i++): 
                                $val = $data[$i]['reject_dc'];
                            ?>
                                <td class="text-muted"><?= $val > 0 ? number_format($val) : '-' ?></td>
                            <?php endfor; ?>
                            <td class="fw-bold bg-light"><?= number_format($yearTotals['reject_dc']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-start pe-3" style="padding-left: 2rem;">- Reject Baritori</td>
                            <?php for ($i = 1; $i <= 12; $i++): 
                                $val = $data[$i]['reject_bt'];
                            ?>
                                <td class="text-muted"><?= $val > 0 ? number_format($val) : '-' ?></td>
                            <?php endfor; ?>
                            <td class="fw-bold bg-light"><?= number_format($yearTotals['reject_bt']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-start pe-3" style="padding-left: 2rem;">- Reject Machining</td>
                            <?php for ($i = 1; $i <= 12; $i++): 
                                $val = $data[$i]['reject_mc'];
                            ?>
                                <td class="text-muted"><?= $val > 0 ? number_format($val) : '-' ?></td>
                            <?php endfor; ?>
                            <td class="fw-bold bg-light"><?= number_format($yearTotals['reject_mc']) ?></td>
                        </tr>
                        
                        <!-- Percentage Rejects Per Section -->
                        <tr class="table-light">
                            <td class="text-start pe-3" style="font-size: 11px; padding-left: 2rem;">% Reject Die Casting</td>
                            <?php for ($i = 1; $i <= 12; $i++): 
                                $val = $data[$i]['reject_dc'];
                                $insp = $data[$i]['total_inspection'];
                                $pct = $insp > 0 ? ($val / $insp) * 100 : 0;
                            ?>
                                <td class="text-muted" style="font-size: 11px;"><?= $insp > 0 ? number_format($pct, 1).'%' : '-' ?></td>
                            <?php endfor; ?>
                            <?php 
                                $totDc = $yearTotals['reject_dc'];
                                $totInsp = $yearTotals['total_inspection'];
                                $totPct = $totInsp > 0 ? ($totDc / $totInsp) * 100 : 0;
                            ?>
                            <td class="fw-bold bg-light" style="font-size: 11px;"><?= number_format($totPct, 1) ?>%</td>
                        </tr>
                        <tr class="table-light">
                            <td class="text-start pe-3" style="font-size: 11px; padding-left: 2rem;">% Reject Baritori</td>
                            <?php for ($i = 1; $i <= 12; $i++): 
                                $val = $data[$i]['reject_bt'];
                                $insp = $data[$i]['total_inspection'];
                                $pct = $insp > 0 ? ($val / $insp) * 100 : 0;
                            ?>
                                <td class="text-muted" style="font-size: 11px;"><?= $insp > 0 ? number_format($pct, 1).'%' : '-' ?></td>
                            <?php endfor; ?>
                            <?php 
                                $totBt = $yearTotals['reject_bt'];
                                $totInsp = $yearTotals['total_inspection'];
                                $totPct = $totInsp > 0 ? ($totBt / $totInsp) * 100 : 0;
                            ?>
                            <td class="fw-bold bg-light" style="font-size: 11px;"><?= number_format($totPct, 1) ?>%</td>
                        </tr>
                        <tr class="table-light">
                            <td class="text-start pe-3" style="font-size: 11px; padding-left: 2rem;">% Reject Machining</td>
                            <?php for ($i = 1; $i <= 12; $i++): 
                                $val = $data[$i]['reject_mc'];
                                $insp = $data[$i]['total_inspection'];
                                $pct = $insp > 0 ? ($val / $insp) * 100 : 0;
                            ?>
                                <td class="text-muted" style="font-size: 11px;"><?= $insp > 0 ? number_format($pct, 1).'%' : '-' ?></td>
                            <?php endfor; ?>
                            <?php 
                                $totMc = $yearTotals['reject_mc'];
                                $totInsp = $yearTotals['total_inspection'];
                                $totPct = $totInsp > 0 ? ($totMc / $totInsp) * 100 : 0;
                            ?>
                            <td class="fw-bold bg-light" style="font-size: 11px;"><?= number_format($totPct, 1) ?>%</td>
                        </tr>

                        <!-- NG Categories -->
                        <?php if (!empty($ngCategories)): ?>
                            <tr class="table-secondary">
                                <td class="text-start fw-bold" colspan="14">NG Categories Breakdown</td>
                            </tr>
                            <?php foreach ($ngCategories as $cat): 
                                $catId = $cat['id'];
                                $catTotal = $yearTotals['ng_categories'][$catId] ?? 0;
                                if ($catTotal > 0): 
                            ?>
                                <tr>
                                    <td class="text-start pe-3" style="padding-left: 2rem;">
                                        <small><?= esc($cat['ng_code']) ?> - <?= esc($cat['ng_name']) ?></small>
                                    </td>
                                    <?php for ($i = 1; $i <= 12; $i++): 
                                        $val = $data[$i]['ng_categories'][$catId] ?? 0;
                                    ?>
                                        <td class="text-danger"><?= $val > 0 ? number_format($val) : '-' ?></td>
                                    <?php endfor; ?>
                                    <td class="fw-bold text-danger bg-light"><?= number_format($catTotal) ?></td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($productId === null): ?>
        <div class="alert alert-info border-0 shadow-sm">
            Please select a product and year to view the yearly defect summary.
        </div>
    <?php else: ?>
        <div class="alert alert-warning border-0 shadow-sm">
            Product not found or invalid selection.
        </div>
    <?php endif; ?>

</div>

<!-- Load jQuery and Chart.js manually -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    $(document).ready(function() {
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });

        <?php if ($productId): ?>
        // Extract chart data
        const labels = [];
        const datasetOK = [];
        const datasetNG = [];

        <?php for ($i = 1; $i <= 12; $i++): 
            $ok = $data[$i]['total_ok'];
            $ng = $data[$i]['reject_total'];
        ?>
            labels.push('<?= $monthsLabels[$i] ?>');
            datasetOK.push(<?= $ok ?>);
            datasetNG.push(<?= $ng ?>);
        <?php endfor; ?>

        const ctx = document.getElementById('defectChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total OK',
                        data: datasetOK,
                        backgroundColor: 'rgba(25, 135, 84, 0.7)',
                        borderColor: 'rgb(25, 135, 84)',
                        borderWidth: 1
                    },
                    {
                        label: 'Total Reject (NG)',
                        data: datasetNG,
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgb(220, 53, 69)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    x: { stacked: true },
                    y: { stacked: true, beginAtZero: true }
                },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                }
            }
        });
        <?php endif; ?>
    });
</script>
<?= $this->endSection(); ?>
