<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
.perf-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid #f0f0f0;
}
.perf-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important;
}
.perf-title {
    font-size: 1.1rem;
    color: #2c3e50;
    letter-spacing: 0.5px;
}
.progress {
    background-color: #f1f5f9;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
}
</style>

<?php
$getOkClass = function($val) {
    if ($val >= 90) return ['bg' => 'bg-success', 'text' => 'text-success'];
    if ($val >= 70) return ['bg' => 'bg-warning', 'text' => 'text-warning'];
    return ['bg' => 'bg-danger', 'text' => 'text-danger'];
};
?>

<div class="container-fluid py-3">
    <!-- Header & Filter -->
    <div class="row mb-4 align-items-center text-center text-md-start">
        <div class="col-md-6 mb-3 mb-md-0 d-flex flex-column align-items-center">
            <h4 class="mb-0 fw-bold">Pilih Tanggal:</h4>
            <form action="" method="get" class="d-flex justify-content-center mt-2 mx-auto" style="max-width: 200px;">
                <input type="hidden" name="process" value="<?= esc($process) ?>">
                <div class="input-group">
                    <input type="date" class="form-control text-center" name="date" value="<?= esc($date) ?>" onchange="this.form.submit()">
                </div>
            </form>
        </div>
        <div class="col-md-6 text-center text-md-end">
            <div class="btn-group shadow-sm" role="group">
                <a href="?process=DC&date=<?= esc($date) ?>" class="btn <?= $process === 'DC' ? 'btn-primary' : 'btn-outline-primary' ?>">Die Casting</a>
                <a href="?process=MC&date=<?= esc($date) ?>" class="btn <?= $process === 'MC' ? 'btn-primary' : 'btn-outline-primary' ?>">Machining</a>
            </div>
        </div>
    </div>

    <h2 class="text-center fw-bold mb-4">Daily Performance<?= $process === 'DC' ? ' - Die Casting' : ' - Machining' ?></h2>

    <!-- Daily Achievement -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body px-4 py-5">
            <h4 class="text-center fw-semibold mb-4">Daily Achievement</h4>
            <div class="row text-center g-4 justify-content-center">
                <!-- OK Achievement Gauges -->
                <div class="col-md-4 col-sm-6">
                    <div id="chart-ok-achievement" class="d-flex justify-content-center"></div>
                    <div class="mt-2 text-muted fw-bold">OK Achievement</div>
                </div>

                <!-- NG Rate Gauges -->
                <div class="col-md-4 col-sm-6">
                    <div id="chart-ng-rate" class="d-flex justify-content-center"></div>
                    <div class="mt-2 text-muted fw-bold">NG Rate</div>
                </div>

                <!-- Downtime (DT) Rate Gauges -->
                <div class="col-md-4 col-sm-6">
                    <div id="chart-dt-rate" class="d-flex justify-content-center"></div>
                    <div class="mt-2 text-muted fw-bold">Downtime (DT) Rate</div>
                </div>
            </div>
            
            <?php if(empty($machines)): ?>
                <div class="alert alert-info text-center mt-4">
                    Belum ada data target atau actual pada tanggal terpilih.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Shift Performance -->
    <?php if(!empty($shifts)): ?>
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body px-4 py-5">
            <h4 class="text-center fw-semibold mb-5">Summary Shift Performance</h4>
            
            <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-4 justify-content-center text-center">
                <?php foreach($shifts as $s_idx => $s): 
                    $okColor = $getOkClass($s['ok_achievement']);
                ?>
                <div class="col text-start">
                    <div class="perf-card p-3 h-100 shadow-sm">
                        <div class="fw-bold mb-3 text-center border-bottom pb-2 perf-title">
                            <i class="bi bi-clock-history text-secondary me-1"></i><?= esc($s['shift_name']) ?>
                        </div>
                        
                        <div class="d-flex justify-content-between small <?= $okColor['text'] ?> fw-bold mb-1">
                            <span>OK</span><span><?= $s['ok_achievement'] ?>%</span>
                        </div>
                        <div class="progress mb-3" style="height: 10px; border-radius: 5px;">
                            <div class="progress-bar <?= $okColor['bg'] ?> progress-bar-striped progress-bar-animated" style="width: <?= $s['ok_achievement'] ?>%"></div>
                        </div>
                        
                        <div class="d-flex justify-content-between small text-danger fw-bold mb-1">
                            <span>NG</span><span><?= $s['ng_rate'] ?>%</span>
                        </div>
                        <div class="progress mb-3" style="height: 10px; border-radius: 5px;">
                            <div class="progress-bar bg-danger" style="width: <?= $s['ng_rate'] ?>%"></div>
                        </div>
                        
                        <div class="d-flex justify-content-between small text-warning fw-bold mb-1">
                            <span>DT</span><span><?= $s['dt_rate'] ?>%</span>
                        </div>
                        <div class="progress mb-3" style="height: 10px; border-radius: 5px;">
                            <div class="progress-bar bg-warning text-dark" style="width: <?= $s['dt_rate'] ?>%"></div>
                        </div>
                        
                        <div class="text-center mt-3 pt-2 border-top bg-light rounded p-2">
                            <div class="small text-muted fw-bold">Target: <span class="text-dark"><?= number_format($s['target']) ?></span></div>
                            <div class="small text-muted fw-bold">OK: <span class="text-dark"><?= number_format($s['fg']) ?></span></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Summary Machine Performance -->
    <?php if(!empty($machines)): ?>
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body px-4 py-5">
            <h4 class="text-center fw-semibold mb-5">Summary Machine Performance</h4>
            
            <div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-4 justify-content-center text-center">
                <?php foreach($machines as $m): 
                    $okColor = $getOkClass($m['ok_achievement']);
                ?>
                <div class="col text-start">
                    <div class="perf-card p-3 h-100 shadow-sm">
                        <div class="fw-bold mb-3 text-center border-bottom pb-2 perf-title">
                            <i class="bi bi-hdd-rack text-secondary me-1"></i><?= esc($m['machine_code']) ?>
                        </div>
                        
                        <div class="d-flex justify-content-between small <?= $okColor['text'] ?> fw-bold mb-1">
                            <span>OK</span><span><?= $m['ok_achievement'] ?>%</span>
                        </div>
                        <div class="progress mb-3" style="height: 10px; border-radius: 5px;">
                            <div class="progress-bar <?= $okColor['bg'] ?> progress-bar-striped progress-bar-animated" style="width: <?= $m['ok_achievement'] ?>%"></div>
                        </div>
                        
                        <div class="d-flex justify-content-between small text-danger fw-bold mb-1">
                            <span>NG</span><span><?= $m['ng_rate'] ?>%</span>
                        </div>
                        <div class="progress mb-3" style="height: 10px; border-radius: 5px;">
                            <div class="progress-bar bg-danger" style="width: <?= $m['ng_rate'] ?>%"></div>
                        </div>
                        
                        <div class="d-flex justify-content-between small text-warning fw-bold mb-1">
                            <span>DT</span><span><?= $m['dt_rate'] ?>%</span>
                        </div>
                        <div class="progress mb-3" style="height: 10px; border-radius: 5px;">
                            <div class="progress-bar bg-warning text-dark" style="width: <?= $m['dt_rate'] ?>%"></div>
                        </div>
                        
                        <div class="text-center mt-3 pt-2 border-top bg-light rounded p-2">
                            <div class="small text-muted fw-bold">Target: <span class="text-dark"><?= number_format($m['target']) ?></span></div>
                            <div class="small text-muted fw-bold">OK: <span class="text-dark"><?= number_format($m['fg']) ?></span></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Detail Performance (Placeholder per request) -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h4 class="text-center fw-semibold mb-4">Detail Performance</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped text-center align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th rowspan="2" class="align-middle">Machine</th>
                            <th rowspan="2" class="align-middle">Target</th>
                            <th colspan="2" class="text-success">OK</th>
                            <th colspan="2" class="text-danger">NG</th>
                            <th colspan="2" class="text-warning text-dark">Downtime (DT)</th>
                            <th rowspan="2" class="align-middle text-primary">Achievement</th>
                        </tr>
                        <tr>
                            <th class="text-success">Qty (Pcs)</th>
                            <th class="text-success">%</th>
                            <th class="text-danger">Qty (Pcs)</th>
                            <th class="text-danger">%</th>
                            <th class="text-warning text-dark">Menit</th>
                            <th class="text-warning text-dark">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($machines as $m): ?>
                        <tr>
                            <td class="fw-bold"><?= esc($m['machine_code']) ?></td>
                            <td class="fw-bold"><?= number_format($m['target']) ?></td>
                            <td class="text-success fw-bold"><?= number_format($m['fg']) ?></td>
                            <td class="text-success fw-bold"><?= $m['ok_achievement'] ?>%</td>
                            <td class="text-danger fw-bold"><?= number_format($m['ng']) ?></td>
                            <td class="text-danger fw-bold"><?= $m['ng_rate'] ?>%</td>
                            <td class="text-warning fw-bold text-dark"><?= number_format($m['downtime'] ?? 0) ?></td>
                            <td class="text-warning fw-bold text-dark"><?= $m['dt_rate'] ?>%</td>
                            <td class="fw-bold text-primary"><?= $m['ok_achievement'] ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    function createMultiRadialChart(elementId, okVal, ngVal, dtVal, okColor) {
        var options = {
            series: [okVal, ngVal, dtVal],
            chart: {
                height: 280,
                type: 'radialBar',
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 800,
                }
            },
            plotOptions: {
                radialBar: {
                    hollow: {
                        size: '45%',
                    },
                    dataLabels: {
                        name: {
                            fontSize: '14px',
                        },
                        value: {
                            fontSize: '16px',
                            fontWeight: 'bold',
                            formatter: function (val) {
                                return val + "%"
                            }
                        },
                        total: {
                            show: true,
                            label: 'Achiev.',
                            color: okColor,
                            formatter: function (w) {
                                return okVal + "%"
                            }
                        }
                    },
                    track: {
                        background: '#e0e0e0',
                        strokeWidth: '100%',
                        margin: 2
                    }
                },
            },
            stroke: {
                lineCap: 'round'
            },
            colors: [okColor, '#f87171', '#fbbf24'],
            labels: ['OK', 'NG', 'DT'],
        };

        var chart = new ApexCharts(document.querySelector("#" + elementId), options);
        chart.render();
    }

    function createSingleRadialChart(elementId, value, color, labelText) {
        var options = {
            series: [value],
            chart: {
                height: 250,
                type: 'radialBar',
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 800,
                }
            },
            plotOptions: {
                radialBar: {
                    hollow: {
                        size: '65%',
                    },
                    dataLabels: {
                        name: {
                            show: false,
                        },
                        value: {
                            show: true,
                            fontSize: '24px',
                            fontWeight: 'bold',
                            color: '#333',
                            formatter: function (val) {
                                return val + "%"
                            }
                        }
                    },
                    track: {
                        background: '#e0e0e0',
                        strokeWidth: '100%',
                    }
                },
            },
            stroke: {
                lineCap: 'round'
            },
            colors: [color],
            labels: [''],
        };

        var chart = new ApexCharts(document.querySelector("#" + elementId), options);
        chart.render();
    }

    // Colors
    const colorOk = '#4ade80'; // Light Green
    const colorNg = '#f87171'; // Red
    const colorDt = '#fbbf24'; // Yellow
    const colorAch = '#2563eb'; // Blue - alternative
    
    // Determine color based on threshold (simple logic for machine colors)
    function getColorByValue(val) {
        if(val >= 90) return '#4ade80';
        if(val >= 70) return '#fbbf24';
        if(val > 0) return '#2563eb';
        return '#9ca3af';
    }

    // Render Main Charts (Daily Achievement at the top)
    createSingleRadialChart("chart-ok-achievement", <?= $okAchievement ?>, '#4ade80', '');
    createSingleRadialChart("chart-ng-rate", <?= $ngRate ?>, '#f87171', '');
    createSingleRadialChart("chart-dt-rate", <?= $downtimeRate ?>, '#fbbf24', '');
});
</script>

<style>
/* Adjust ApexCharts default margin */
.apexcharts-canvas {
    margin: 0 auto;
}
/* Reduce height slightly for machine charts to save vertical space if needed */
[id^="chart-machine-"] .apexcharts-canvas {
    height: 180px !important;
}
</style>

<?= $this->endSection() ?>
