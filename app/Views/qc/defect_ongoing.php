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
            <h4 class="m-0 text-dark">Defect Ongoing</h4>
            <?php if ($productId): ?>
                <div class="d-flex gap-2 d-print-none">
                    <a href="/qc/defect-ongoing/export?month=<?= esc($month) ?>&product_id=<?= $productId ?>" class="btn btn-outline-success btn-sm fw-bold shadow-sm">
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
                        <label for="month" class="form-label">Month</label>
                        <input type="month" id="month" name="month" class="form-control" value="<?= esc($month) ?>">
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
                    <h5 class="m-0">Quality Control Check - <?= date('F Y', strtotime($month . '-01')) ?></h5>
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
                            <th class="align-middle text-start" style="min-width: 200px;">Metrics / Date</th>
                            <?php for ($i = 1; $i <= $daysInMonth; $i++): ?>
                                <th class="align-middle"><?= $i ?></th>
                            <?php endfor; ?>
                            <th class="align-middle bg-secondary text-white">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Shifts Data -->
                        <?php for ($s = 1; $s <= 3; $s++): ?>
                            <tr>
                                <td class="text-start fw-bold text-success">OK Shift <?= $s ?></td>
                                <?php for ($i = 1; $i <= $daysInMonth; $i++): 
                                    $dateStr = sprintf('%s-%02d', $month, $i);
                                    $val = $data[$dateStr]['shifts'][$s]['ok'];
                                ?>
                                    <td><?= $val > 0 ? number_format($val) : '-' ?></td>
                                <?php endfor; ?>
                                <td class="fw-bold bg-light"><?= number_format($monthTotals['shifts'][$s]['ok']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-start fw-bold text-danger">NG Shift <?= $s ?></td>
                                <?php for ($i = 1; $i <= $daysInMonth; $i++): 
                                    $dateStr = sprintf('%s-%02d', $month, $i);
                                    $val = $data[$dateStr]['shifts'][$s]['ng'];
                                ?>
                                    <td <?= $val > 0 ? 'class="text-danger"' : '' ?>><?= $val > 0 ? number_format($val) : '-' ?></td>
                                <?php endfor; ?>
                                <td class="fw-bold text-danger bg-light"><?= number_format($monthTotals['shifts'][$s]['ng']) ?></td>
                            </tr>
                            <tr style="border-bottom: 2px solid #e5e7eb;">
                                <td class="text-start fw-bold text-warning" style="font-size: 11px;">% NG Shift <?= $s ?></td>
                                <?php for ($i = 1; $i <= $daysInMonth; $i++): 
                                    $dateStr = sprintf('%s-%02d', $month, $i);
                                    $ok = $data[$dateStr]['shifts'][$s]['ok'];
                                    $ng = $data[$dateStr]['shifts'][$s]['ng'];
                                    $tot = $ok + $ng;
                                    $pct = $tot > 0 ? ($ng / $tot) * 100 : 0;
                                ?>
                                    <td class="text-warning fw-bold" style="font-size: 11px;"><?= $tot > 0 ? number_format($pct, 1) . '%' : '-' ?></td>
                                <?php endfor; ?>
                                <?php
                                    $totOk = $monthTotals['shifts'][$s]['ok'];
                                    $totNg = $monthTotals['shifts'][$s]['ng'];
                                    $totAll = $totOk + $totNg;
                                    $totPct = $totAll > 0 ? ($totNg / $totAll) * 100 : 0;
                                ?>
                                <td class="fw-bold text-warning bg-light" style="font-size: 11px;"><?= $totAll > 0 ? number_format($totPct, 1) . '%' : '-' ?></td>
                            </tr>
                        <?php endfor; ?>

                        <!-- Total Inspection & Total OK % -->
                        <tr class="table-light">
                            <td class="text-start fw-bold">Total Inspection (QTY)</td>
                            <?php for ($i = 1; $i <= $daysInMonth; $i++): 
                                $dateStr = sprintf('%s-%02d', $month, $i);
                                $val = $data[$dateStr]['total_inspection'];
                            ?>
                                <td class="fw-bold"><?= $val > 0 ? number_format($val) : '-' ?></td>
                            <?php endfor; ?>
                            <td class="fw-bold fs-6"><?= number_format($monthTotals['total_inspection']) ?></td>
                        </tr>
                        <tr class="table-light">
                            <td class="text-start fw-bold text-success">Total OK (%)</td>
                            <?php for ($i = 1; $i <= $daysInMonth; $i++): 
                                $dateStr = sprintf('%s-%02d', $month, $i);
                                $insp = $data[$dateStr]['total_inspection'];
                                $ok = $data[$dateStr]['total_ok'];
                                $pct = $insp > 0 ? ($ok / $insp) * 100 : 0;
                            ?>
                                <td class="fw-bold text-success"><?= $insp > 0 ? number_format($pct, 1) . '%' : '-' ?></td>
                            <?php endfor; ?>
                            <?php
                                $totInsp = $monthTotals['total_inspection'];
                                $totOk = $monthTotals['total_ok'];
                                $totPct = $totInsp > 0 ? ($totOk / $totInsp) * 100 : 0;
                            ?>
                            <td class="fw-bold text-success fs-6"><?= number_format($totPct, 1) ?>%</td>
                        </tr>

                        <!-- Rejects -->
                        <tr>
                            <td class="text-start fw-bold text-danger table-warning">Reject Total</td>
                            <?php for ($i = 1; $i <= $daysInMonth; $i++): 
                                $dateStr = sprintf('%s-%02d', $month, $i);
                                $val = $data[$dateStr]['reject_total'];
                            ?>
                                <td class="fw-bold table-warning text-danger"><?= $val > 0 ? number_format($val) : '-' ?></td>
                            <?php endfor; ?>
                            <td class="fw-bold table-warning text-danger"><?= number_format($monthTotals['reject_total']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-start pe-3" style="padding-left: 2rem;">- Reject Die Casting</td>
                            <?php for ($i = 1; $i <= $daysInMonth; $i++): 
                                $dateStr = sprintf('%s-%02d', $month, $i);
                                $val = $data[$dateStr]['reject_dc'];
                            ?>
                                <td class="text-muted"><?= $val > 0 ? number_format($val) : '-' ?></td>
                            <?php endfor; ?>
                            <td class="fw-bold bg-light"><?= number_format($monthTotals['reject_dc']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-start pe-3" style="padding-left: 2rem;">- Reject Baritori</td>
                            <?php for ($i = 1; $i <= $daysInMonth; $i++): 
                                $dateStr = sprintf('%s-%02d', $month, $i);
                                $val = $data[$dateStr]['reject_bt'];
                            ?>
                                <td class="text-muted"><?= $val > 0 ? number_format($val) : '-' ?></td>
                            <?php endfor; ?>
                            <td class="fw-bold bg-light"><?= number_format($monthTotals['reject_bt']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-start pe-3" style="padding-left: 2rem;">- Reject Machining</td>
                            <?php for ($i = 1; $i <= $daysInMonth; $i++): 
                                $dateStr = sprintf('%s-%02d', $month, $i);
                                $val = $data[$dateStr]['reject_mc'];
                            ?>
                                <td class="text-muted"><?= $val > 0 ? number_format($val) : '-' ?></td>
                            <?php endfor; ?>
                            <td class="fw-bold bg-light"><?= number_format($monthTotals['reject_mc']) ?></td>
                        </tr>
                        <!-- Percentage Rejects Per Section -->
                        <tr class="table-light">
                            <td class="text-start pe-3" style="font-size: 11px; padding-left: 2rem;">% Reject Die Casting</td>
                            <?php for ($i = 1; $i <= $daysInMonth; $i++): 
                                $dateStr = sprintf('%s-%02d', $month, $i);
                                $val = $data[$dateStr]['reject_dc'];
                                $insp = $data[$dateStr]['total_inspection'];
                                $pct = $insp > 0 ? ($val / $insp) * 100 : 0;
                            ?>
                                <td class="text-muted" style="font-size: 11px;"><?= $insp > 0 ? number_format($pct, 1).'%' : '-' ?></td>
                            <?php endfor; ?>
                            <?php 
                                $totDc = $monthTotals['reject_dc'];
                                $totInsp = $monthTotals['total_inspection'];
                                $totPct = $totInsp > 0 ? ($totDc / $totInsp) * 100 : 0;
                            ?>
                            <td class="fw-bold bg-light" style="font-size: 11px;"><?= number_format($totPct, 1) ?>%</td>
                        </tr>
                        <tr class="table-light">
                            <td class="text-start pe-3" style="font-size: 11px; padding-left: 2rem;">% Reject Baritori</td>
                            <?php for ($i = 1; $i <= $daysInMonth; $i++): 
                                $dateStr = sprintf('%s-%02d', $month, $i);
                                $val = $data[$dateStr]['reject_bt'];
                                $insp = $data[$dateStr]['total_inspection'];
                                $pct = $insp > 0 ? ($val / $insp) * 100 : 0;
                            ?>
                                <td class="text-muted" style="font-size: 11px;"><?= $insp > 0 ? number_format($pct, 1).'%' : '-' ?></td>
                            <?php endfor; ?>
                            <?php 
                                $totBt = $monthTotals['reject_bt'];
                                $totInsp = $monthTotals['total_inspection'];
                                $totPct = $totInsp > 0 ? ($totBt / $totInsp) * 100 : 0;
                            ?>
                            <td class="fw-bold bg-light" style="font-size: 11px;"><?= number_format($totPct, 1) ?>%</td>
                        </tr>
                        <tr class="table-light">
                            <td class="text-start pe-3" style="font-size: 11px; padding-left: 2rem;">% Reject Machining</td>
                            <?php for ($i = 1; $i <= $daysInMonth; $i++): 
                                $dateStr = sprintf('%s-%02d', $month, $i);
                                $val = $data[$dateStr]['reject_mc'];
                                $insp = $data[$dateStr]['total_inspection'];
                                $pct = $insp > 0 ? ($val / $insp) * 100 : 0;
                            ?>
                                <td class="text-muted" style="font-size: 11px;"><?= $insp > 0 ? number_format($pct, 1).'%' : '-' ?></td>
                            <?php endfor; ?>
                            <?php 
                                $totMc = $monthTotals['reject_mc'];
                                $totInsp = $monthTotals['total_inspection'];
                                $totPct = $totInsp > 0 ? ($totMc / $totInsp) * 100 : 0;
                            ?>
                            <td class="fw-bold bg-light" style="font-size: 11px;"><?= number_format($totPct, 1) ?>%</td>
                        </tr>

                        <!-- NG Categories -->
                        <?php if (!empty($ngCategories)): ?>
                            <tr class="table-secondary">
                                <td class="text-start fw-bold" colspan="<?= $daysInMonth + 2 ?>">NG Categories Breakdown</td>
                            </tr>
                            <?php foreach ($ngCategories as $cat): 
                                $catId = $cat['id'];
                                // Check if this category has any data this month to hide empty rows (optional)
                                // If want to show all, ignore. Let's show all that have > 0 in monthTotal
                                $catTotal = $monthTotals['ng_categories'][$catId] ?? 0;
                                if ($catTotal > 0): // Only show categories with actual defects this month
                            ?>
                                <tr>
                                    <td class="text-start pe-3" style="padding-left: 2rem;">
                                        <small><?= esc($cat['ng_code']) ?> - <?= esc($cat['ng_name']) ?></small>
                                    </td>
                                    <?php for ($i = 1; $i <= $daysInMonth; $i++): 
                                        $dateStr = sprintf('%s-%02d', $month, $i);
                                        $val = $data[$dateStr]['ng_categories'][$catId] ?? 0;
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
            Please select a product and month to view the ongoing defect report.
        </div>
    <?php else: ?>
        <div class="alert alert-warning border-0 shadow-sm">
            Product not found or invalid selection.
        </div>
    <?php endif; ?>

</div>
</div>

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

        <?php for ($i = 1; $i <= $daysInMonth; $i++): 
            $dateStr = sprintf('%s-%02d', $month, $i);
            $ok = $data[$dateStr]['total_ok'];
            $ng = $data[$dateStr]['reject_total'];
        ?>
            labels.push('<?= $i ?>');
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
