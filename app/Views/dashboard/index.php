<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');

/* ══ BASE ══ */
.dash-wrap { font-family: 'Inter', sans-serif; color: #0f172a; }

/* ══ HEADER BANNER ══ */
.dash-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0f172a 100%);
    border-radius: 20px; padding: 1.6rem 2rem; margin-bottom: 1.5rem;
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;
    box-shadow: 0 10px 40px rgba(0,0,0,.4); border: 1px solid rgba(255,255,255,.07);
    position: relative; overflow: hidden;
}
.dash-header::before {
    content: ''; position: absolute; top: -60px; right: -60px;
    width: 200px; height: 200px; border-radius: 50%;
    background: rgba(59,130,246,.15); pointer-events: none;
}
.dash-logo { width: 56px; height: 56px; background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    border-radius: 14px; display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; font-weight: 900; color: white; box-shadow: 0 4px 16px rgba(59,130,246,.5); }
.dash-company { color: #94a3b8; font-size: .72rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
.dash-title { font-size: 1.8rem; font-weight: 900; color: white; letter-spacing: .04em; text-transform: uppercase;
    text-shadow: 0 2px 16px rgba(59,130,246,.4); }
.dash-subtitle { color: #64748b; font-size: .82rem; font-weight: 600; margin-top: 2px; }
.dash-date-form input[type="date"] {
    background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2);
    color: white; border-radius: 10px; padding: .45rem .9rem; font-weight: 600; font-size: .875rem; }
.dash-date-form input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); }
.dash-date-form button {
    background: #3b82f6; border: none; color: white; border-radius: 10px;
    padding: .45rem 1.2rem; font-weight: 700; font-size: .875rem; cursor: pointer; transition: background .2s; }
.dash-date-form button:hover { background: #2563eb; }

/* ══ QUICK LINKS ══ */
.quick-links { display: flex; gap: .8rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
.ql-btn {
    display: inline-flex; align-items: center; gap: .45rem;
    padding: .5rem 1.1rem; border-radius: 10px; font-weight: 700; font-size: .82rem;
    text-decoration: none; border: none; cursor: pointer; transition: all .2s;
    box-shadow: 0 2px 8px rgba(0,0,0,.12);
}
.ql-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,.18); }
.ql-asakai { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; }
.ql-performance { background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: white; }
.ql-dcb { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
.ql-scd { background: linear-gradient(135deg, #10b981, #059669); color: white; }
.ql-qc { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
.ql-fg { background: linear-gradient(135deg, #06b6d4, #0284c7); color: white; }
.ql-defect { background: linear-gradient(135deg, #dc2626, #991b1b); color: white; }
.ql-defect-yearly { background: linear-gradient(135deg, #9333ea, #6b21a8); color: white; }
.ql-wip { background: linear-gradient(135deg, #0891b2, #0e7490); color: white; }

/* ══ WIP TABLE ══ */
.wip-process-badge { display: inline-block; font-size: .62rem; font-weight: 700;
    padding: .2rem .55rem; border-radius: 5px; background: #e0f2fe; color: #0369a1;
    border: 1px solid #bae6fd; text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; }
.wip-total-badge { display: inline-block; font-size: .85rem; font-weight: 900;
    padding: .22rem .65rem; border-radius: 7px; background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

/* ══ SECTION TITLE ══ */
.section-title {
    display: flex; align-items: center; gap: .7rem; margin-bottom: 1rem; padding-bottom: .5rem;
    border-bottom: 2px solid #e2e8f0;
}
.section-title .icon-box {
    width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; flex-shrink: 0;
}
.section-title h5 { font-weight: 800; font-size: 1.1rem; margin: 0; color: #0f172a; }
.section-title small { color: #64748b; font-size: .78rem; font-weight: 600; }
.full-link { margin-left: auto; font-size: .78rem; font-weight: 700; text-decoration: none;
    display: inline-flex; align-items: center; gap: .3rem; color: #3b82f6; }
.full-link:hover { color: #1d4ed8; }

/* ══ CARD BASE ══ */
.dash-card {
    background: white; border-radius: 16px; box-shadow: 0 2px 16px rgba(0,0,0,.07);
    border: 1px solid #e8ecf4; margin-bottom: 1.5rem; overflow: hidden;
}
.dash-card-header { padding: 1rem 1.4rem; border-bottom: 1px solid #f1f5f9; }

/* ══ STAT PILLS ══ */
.stat-pill {
    background: #f8fafc; border-radius: 12px; border: 1px solid #e8ecf4;
    padding: .8rem 1.2rem; display: flex; align-items: center; gap: .8rem;
    transition: transform .2s, box-shadow .2s;
}
.stat-pill:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.1); }
.stat-pill-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
.stat-pill-val { font-size: 1.4rem; font-weight: 900; color: #0f172a; line-height: 1; }
.stat-pill-lbl { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #64748b; margin-top: 2px; }

/* ══ FG INVENTORY TABLE ══ */
.mini-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
.mini-table th { background: #1e293b; color: #cbd5e1; font-weight: 700; text-transform: uppercase;
    font-size: .68rem; letter-spacing: .05em; padding: .5rem .75rem; border: 1px solid #334155; text-align: center; }
.mini-table td { padding: .42rem .75rem; border: 1px solid #f1f5f9; vertical-align: middle; color: #334155; }
.mini-table tbody tr:hover { background: #f8fafc; }
.mini-table td:first-child { font-weight: 700; color: #0f172a; }
.badge-stock { display: inline-block; padding: .22rem .6rem; border-radius: 6px; font-weight: 800; font-size: .78rem; }
.badge-in { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.badge-out { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.badge-avail { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; font-size: .85rem; }

/* ══ PERFORMANCE DONUT ══ */
.perf-donut-wrap { display: flex; gap: 1rem; align-items: flex-start; margin-bottom: .8rem; }
.perf-donut-chart { position: relative; width: 110px; height: 110px; flex-shrink: 0; }
.perf-donut-chart canvas { display: block; }
.donut-center-label {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
    text-align: center; pointer-events: none;
}
.donut-center-label .pct { font-size: 1.2rem; font-weight: 900; line-height: 1; }
.donut-center-label .lbl { font-size: .55rem; font-weight: 700; letter-spacing: .06em;
    text-transform: uppercase; color: #64748b; }
.perf-kpi-pills { display: flex; flex-direction: column; gap: .45rem; flex: 1; justify-content: center; }
.perf-kpi-pill { display: flex; align-items: center; justify-content: space-between;
    background: #f8fafc; border-radius: 8px; padding: .35rem .7rem;
    border: 1px solid #e8ecf4; font-size: .75rem; }
.perf-kpi-pill .kpi-lbl { color: #64748b; font-weight: 600; }
.perf-kpi-pill .kpi-val { font-weight: 800; }
.machine-code { font-weight: 800; color: #1e293b; min-width: 55px; }
.mini-bar { flex: 1; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; }
.mini-bar-fill { height: 100%; border-radius: 3px; transition: width .5s ease; }
.fill-ok { background: linear-gradient(90deg, #22c55e, #16a34a); }
.machine-pct { font-weight: 800; font-size: .75rem; min-width: 42px; text-align: right; }
.perf-machine-row { display: flex; align-items: center; gap: .5rem; padding: .3rem 0;
    border-bottom: 1px solid #f1f5f9; font-size: .78rem; }
.perf-machine-row:last-child { border-bottom: none; }


/* ══ ASAKAI TABLE ══ */
.asakai-mini th { background: #0f172a; color: #e2e8f0; font-size: .68rem; font-weight: 700;
    letter-spacing: .05em; text-transform: uppercase; padding: .45rem .6rem; border: 1px solid #1e293b; text-align: center; }
.asakai-mini td { padding: .38rem .6rem; border: 1px solid #f1f5f9; font-size: .78rem; vertical-align: middle; }
.asakai-mini tr:hover td { background: #f8fafc; }
.eff-hi { color: #16a34a; font-weight: 800; }
.eff-mid { color: #f59e0b; font-weight: 800; }
.eff-lo { color: #dc2626; font-weight: 800; }
.section-header-row td { background: linear-gradient(90deg, #1e293b, #334155) !important;
    color: white !important; font-weight: 800 !important; text-transform: uppercase;
    letter-spacing: .07em; font-size: .72rem !important; padding: .5rem .75rem !important; }
.total-row td { background: #f0fdf4 !important; font-weight: 900 !important; color: #166534 !important; font-size: .8rem !important; }
.operator-row td { background: #f0f9ff !important; color: #0369a1 !important; font-size: .7rem !important; font-weight: 600 !important; padding: .28rem .6rem !important; }
.leader-row td   { background: #f0fdf4 !important; color: #166534 !important; font-size: .7rem !important; font-weight: 600 !important; padding: .28rem .6rem !important; }
.op-badge { font-size: .68rem; font-weight: 700; padding: .12rem .45rem; border-radius: 5px; display: inline-block; }
.op-badge-op { background: #dbeafe; color: #1d4ed8; }
.op-badge-ld { background: #dcfce7; color: #166534; }
/* DCB customer group row */
.dcb-cust-row td { background: linear-gradient(90deg, #1e293b, #2d3f55) !important;
    color: #f8fafc !important; font-weight: 800 !important; font-size: .7rem !important;
    text-transform: uppercase; letter-spacing: .07em; padding: .4rem .7rem !important;
    border-color: #334155 !important; }
.dcb-part-blank td { color: #cbd5e1 !important; background: #f8fafc !important; }


/* ══ DCB TABLE ══ */
.dcb-mini th { background: #1e293b; color: white; font-weight: 700; text-transform: uppercase;
    font-size: .66rem; letter-spacing: .05em; padding: .42rem .6rem; border: 1px solid #334155; text-align: center; white-space: nowrap; }
.dcb-mini td { padding: .36rem .6rem; border: 1px solid #f1f5f9; font-size: .75rem; vertical-align: middle; color: #334155; }
.dcb-mini tbody tr:hover td { background: #f8fafc; }
.dcb-status { display: inline-flex; width: 22px; height: 22px; border-radius: 50%;
    align-items: center; justify-content: center; font-size: .58rem; font-weight: 800; }
.dcb-status.ok { background: #dcfce7; color: #16a34a; border: 1.5px solid #86efac; }
.dcb-status.na { background: #f1f5f9; color: #94a3b8; border: 1.5px solid #e2e8f0; }
.dcb-status.ng { background: #fee2e2; color: #dc2626; border: 1.5px solid #fca5a5; }
.act-badge { display: inline-block; padding: .18rem .5rem; border-radius: 5px; font-weight: 800; font-size: .75rem; }
.act-ok { background: #dbeafe; color: #1d4ed8; }
.act-ng-val { background: #fee2e2; color: #dc2626; }
.act-na { background: #f1f5f9; color: #94a3b8; }

/* ══ SCD TABLE ══ */
.scd-mini th { background: #0f172a; color: white; font-weight: 700; text-transform: uppercase;
    font-size: .65rem; letter-spacing: .05em; padding: .42rem .6rem; border: 1px solid #1e293b; text-align: center; white-space: nowrap; }
.scd-mini td { padding: .36rem .6rem; border: 1px solid #f1f5f9; font-size: .75rem; vertical-align: middle; }
.scd-mini tbody tr:hover td { background: #f8fafc; }
.rit-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 3px; }

/* ══ QC CHART WRAPPER ══ */
.chart-wrap { position: relative; height: 220px; }

/* ══ SUMMARY FOOTER STRIP ══ */
.summary-strip { display: flex; gap: .7rem; padding: .8rem 1.2rem; background: #f8fafc;
    border-top: 1px solid #e8ecf4; flex-wrap: wrap; }
.sum-stat { background: white; border: 1px solid #e8ecf4; border-radius: 10px; padding: .45rem .9rem;
    display: flex; flex-direction: column; align-items: center; min-width: 80px; }
.sum-val { font-size: 1.15rem; font-weight: 900; color: #0f172a; }
.sum-lbl { font-size: .62rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .05em; margin-top: 1px; }

/* ══ EMPTY STATE ══ */
.empty-state { padding: 2.5rem 1rem; text-align: center; color: #94a3b8; }
.empty-state i { font-size: 2rem; display: block; margin-bottom: .5rem; }
.empty-state span { font-size: .85rem; font-weight: 600; }

/* ══ LIVE CLOCK ══ */
.live-clock { color: #93c5fd; font-weight: 700; font-size: .9rem; letter-spacing: .04em; }
</style>

<div class="dash-wrap">

<!-- ══ HEADER ══ -->
<div class="dash-header">
    <div class="d-flex align-items-center gap-3">
        <div class="dash-logo"><i class="bi bi-speedometer2"></i></div>
        <div>
            <div class="dash-company">PT. Cikarang Perkasa Manufacturing</div>
            <div class="dash-title">Production Dashboard</div>
            <div class="dash-subtitle">Real-time monitoring — <?= strtoupper(date('l, d F Y', strtotime($date))) ?></div>
        </div>
    </div>
    <div class="d-flex flex-column align-items-end gap-2">
        <div class="live-clock" id="liveClock">--:--:--</div>
        <form method="get" class="dash-date-form d-flex align-items-center gap-2">
            <i class="bi bi-calendar3 text-white"></i>
            <input type="date" name="date" value="<?= esc($date) ?>">
            <button type="submit"><i class="bi bi-search me-1"></i>Filter</button>
        </form>
    </div>
</div>

<!-- ══ QUICK LINKS ══ -->
<div class="quick-links">
    <a href="<?= site_url('dashboard/asakai') ?>" class="ql-btn ql-asakai">
        <i class="bi bi-sunrise"></i> Asakai Dashboard
    </a>
    <a href="<?= site_url('dashboard/daily-performance') ?>" class="ql-btn ql-performance">
        <i class="bi bi-graph-up-arrow"></i> Daily Performance
    </a>
    <a href="<?= site_url('finished-good/delivery-control-board') ?>" class="ql-btn ql-dcb">
        <i class="bi bi-clipboard2-data"></i> Delivery Control Board
    </a>
    <a href="<?= site_url('finished-good/special-control-delivery') ?>" class="ql-btn ql-scd">
        <i class="bi bi-truck-front"></i> Special Control Delivery
    </a>
    <a href="<?= site_url('inventory-fg') ?>" class="ql-btn ql-fg">
        <i class="bi bi-boxes"></i> Inventory FG
    </a>
    <a href="<?= site_url('qc/defect-ongoing') ?>" class="ql-btn ql-defect">
        <i class="bi bi-bug"></i> QC Defect Ongoing
    </a>
    <a href="<?= site_url('qc/summary-defect-ongoing') ?>" class="ql-btn ql-defect-yearly">
        <i class="bi bi-bar-chart-steps"></i> Summary Defect Yearly
    </a>
</div>

<!-- ══ ROW 1: FG INVENTORY ══ -->
<div class="dash-card">
    <div class="dash-card-header">
        <div class="section-title" style="border:none; margin-bottom:0; padding-bottom:0;">
            <div class="icon-box" style="background:#eff6ff; color:#3b82f6;"><i class="bi bi-boxes"></i></div>
            <div>
                <h5>Inventory Finished Good</h5>
                <small>Stok produk jadi yang tersedia saat ini</small>
            </div>
            <a href="<?= site_url('inventory-fg') ?>" class="full-link">
                Lihat Detail <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="p-3 d-flex gap-3 flex-wrap" style="background: #f8fafc; border-bottom: 1px solid #e8ecf4;">
        <?php
        $totalFgVarian = count($fgInventory);
        $totalFgQty = array_sum(array_column($fgInventory, 'qty_available'));
        $totalFgIn = array_sum(array_column($fgInventory, 'qty_total_in'));
        ?>
        <div class="stat-pill">
            <div class="stat-pill-icon" style="background:#eff6ff; color:#3b82f6;"><i class="bi bi-box-seam"></i></div>
            <div>
                <div class="stat-pill-val"><?= number_format($totalFgVarian) ?></div>
                <div class="stat-pill-lbl">Total Varian</div>
            </div>
        </div>
        <div class="stat-pill">
            <div class="stat-pill-icon" style="background:#f0fdf4; color:#16a34a;"><i class="bi bi-archive"></i></div>
            <div>
                <div class="stat-pill-val" style="color:#16a34a;"><?= number_format($totalFgQty) ?></div>
                <div class="stat-pill-lbl">Total Stock (Pcs)</div>
            </div>
        </div>
        <div class="stat-pill">
            <div class="stat-pill-icon" style="background:#fef3c7; color:#d97706;"><i class="bi bi-truck"></i></div>
            <div>
                <div class="stat-pill-val" style="color:#d97706;"><?= number_format($totalFgIn) ?></div>
                <div class="stat-pill-lbl">Total Masuk</div>
            </div>
        </div>
    </div>

    <div class="table-responsive" style="max-height: 260px;">
        <table class="mini-table">
            <thead>
                <tr>
                    <th class="text-start">Part No</th>
                    <th class="text-start">Part Name</th>
                    <th>Total Masuk</th>
                    <th>Delivery</th>
                    <th>Stock Tersedia</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($fgInventory)): ?>
                    <tr><td colspan="5" class="empty-state"><i class="bi bi-inbox"></i><span>Belum ada stok FG</span></td></tr>
                <?php else: ?>
                    <?php foreach ($fgInventory as $item): ?>
                    <tr>
                        <td style="font-weight:800; color:#0f172a; font-size:.8rem;"><?= esc($item['part_no']) ?></td>
                        <td style="color:#64748b; font-size:.78rem;"><?= esc($item['part_name']) ?></td>
                        <td class="text-center"><span class="badge-stock badge-in"><?= number_format($item['qty_total_in']) ?></span></td>
                        <td class="text-center"><span class="badge-stock badge-out"><?= number_format($item['qty_delivered']) ?></span></td>
                        <td class="text-center"><span class="badge-stock badge-avail"><?= number_format($item['qty_available']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══ ROW 2: DAILY PERFORMANCE (DC + MC) ══ -->
<div class="row g-3 mb-3">
    <?php
    $perfCards = [
        ['DC','Die Casting','#1d4ed8','bi-gear-wide-connected',$perfDC,'perfChartDC'],
        ['MC','Machining',  '#7c3aed','bi-cpu',               $perfMC,'perfChartMC'],
    ];
    foreach ($perfCards as [$code,$name,$color,$icon,$perf,$chartId]):
        $okPct = (float)$perf['ok_achievement'];
        $ngPct = (float)$perf['ng_rate'];
        $dtPct = (float)$perf['downtime_rate'];
        $okColor = $okPct >= 95 ? '#16a34a' : ($okPct >= 75 ? '#f59e0b' : '#dc2626');
    ?>
    <div class="col-lg-6">
        <div class="dash-card" style="margin-bottom:0; height:100%;">
            <div class="dash-card-header">
                <div class="section-title" style="border:none; margin-bottom:0; padding-bottom:0;">
                    <div class="icon-box" style="background:<?= $color ?>18; color:<?= $color ?>;"><i class="bi <?= $icon ?>"></i></div>
                    <div>
                        <h5>Daily Performance — <?= $name ?></h5>
                        <small>Efisiensi &amp; KPI mesin hari ini</small>
                    </div>
                    <a href="<?= site_url("dashboard/daily-performance?process={$code}") ?>" class="full-link">
                        Detail <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="p-3">
                <!-- Donut + KPI Pills -->
                <div class="perf-donut-wrap">
                    <div class="perf-donut-chart">
                        <canvas id="<?= $chartId ?>" width="110" height="110"></canvas>
                        <div class="donut-center-label">
                            <div class="pct" style="color:<?= $okColor ?>"><?= $okPct ?>%</div>
                            <div class="lbl">OK</div>
                        </div>
                    </div>
                    <div class="perf-kpi-pills">
                        <div class="perf-kpi-pill">
                            <span class="kpi-lbl"><i class="bi bi-check-circle-fill text-success me-1"></i>OK Achieve</span>
                            <span class="kpi-val" style="color:#16a34a"><?= $okPct ?>%</span>
                        </div>
                        <div class="perf-kpi-pill">
                            <span class="kpi-lbl"><i class="bi bi-x-circle-fill text-danger me-1"></i>NG Rate</span>
                            <span class="kpi-val" style="color:#dc2626"><?= $ngPct ?>%</span>
                        </div>
                        <div class="perf-kpi-pill">
                            <span class="kpi-lbl"><i class="bi bi-clock-fill text-warning me-1"></i>Downtime</span>
                            <span class="kpi-val" style="color:#f59e0b"><?= $dtPct ?>%</span>
                        </div>
                    </div>
                </div>

                <!-- Per Shift Bars -->
                <?php if (!empty($perf['shifts'])): ?>
                <div style="margin-top:.4rem; margin-bottom: .8rem;">
                    <div style="font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:#94a3b8; margin-bottom:.3rem;">Per Shift</div>
                    <?php foreach ($perf['shifts'] as $s): ?>
                    <div class="perf-machine-row">
                        <span class="machine-code" style="font-size: .72rem; min-width:60px;"><?= esc(str_replace('Shift ', 'S-', $s['shift_name'])) ?></span>
                        <div class="mini-bar">
                            <div class="mini-bar-fill fill-ok" style="width:<?= min(100, $s['ok_achievement']) ?>%"></div>
                        </div>
                        <span class="machine-pct" style="color:<?= $s['ok_achievement'] >= 95 ? '#16a34a' : ($s['ok_achievement'] >= 75 ? '#f59e0b' : '#dc2626') ?>">
                            <?= $s['ok_achievement'] ?>%
                        </span>
                        <span style="font-size:.68rem; color:#94a3b8; min-width:52px;">NG:<?= $s['ng'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Per Machine Bars -->
                <?php if (!empty($perf['machines'])): ?>
                <div style="max-height:260px; overflow-y:auto; margin-top:.4rem;">
                    <div style="font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:#94a3b8; margin-bottom:.3rem;">Per Mesin</div>
                    <?php foreach ($perf['machines'] as $m): ?>
                    <div class="perf-machine-row" style="flex-wrap:wrap;">
                        <span class="machine-code"><?= esc($m['machine_code']) ?></span>
                        <div class="mini-bar">
                            <div class="mini-bar-fill fill-ok" style="width:<?= min(100, $m['ok_achievement']) ?>%"></div>
                        </div>
                        <span class="machine-pct" style="color:<?= $m['ok_achievement'] >= 95 ? '#16a34a' : ($m['ok_achievement'] >= 75 ? '#f59e0b' : '#dc2626') ?>">
                            <?= $m['ok_achievement'] ?>%
                        </span>
                        <span style="font-size:.68rem; color:#94a3b8; min-width:52px;">NG:<?= $m['ng'] ?></span>
                    </div>
                    <?php
                    $ngDetails = $m['ng_details'] ?? [];
                    $dtDetails = $m['dt_details'] ?? [];
                    if (!empty($ngDetails) || !empty($dtDetails)):
                    ?>
                    <div style="padding: 2px 0 4px 60px; font-size:.68rem; border-bottom:1px solid #f1f5f9;">
                        <?php if (!empty($ngDetails)): ?>
                        <div style="margin-bottom:2px;">
                            <?php foreach ($ngDetails as $ngName => $ngQty): ?>
                            <span style="display:inline-block; background:#fee2e2; color:#dc2626; padding:1px 6px; border-radius:4px; font-weight:700; margin-right:3px; margin-bottom:2px; font-size:.62rem;"><?= esc($ngName) ?>: <?= $ngQty ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($dtDetails)): ?>
                        <div>
                            <?php foreach ($dtDetails as $dtName => $dtMins): ?>
                            <span style="display:inline-block; background:#fef3c7; color:#92400e; padding:1px 6px; border-radius:4px; font-weight:700; margin-right:3px; margin-bottom:2px; font-size:.62rem;"><?= esc($dtName) ?>: <?= $dtMins ?>m</span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state" style="padding:1rem;"><i class="bi bi-bar-chart"></i><span>Belum ada data hari ini</span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>



<!-- ══ WIP INVENTORY ══ -->
<div class="dash-card">
    <div class="dash-card-header">
        <div class="section-title" style="border:none; margin-bottom:0; padding-bottom:0;">
            <div class="icon-box" style="background:#e0f2fe; color:#0369a1;"><i class="bi bi-alarm"></i></div>
            <div>
                <h5>WIP Inventory</h5>
                <small>Work-In-Process per stasiun — stok &amp; transfer</small>
            </div>
            <div class="ms-auto d-flex align-items-center gap-3">
                <div class="sum-stat" style="min-width:90px;">
                    <span class="sum-val" style="color:#0369a1;"><?= number_format($wipData['total_wip']) ?></span>
                    <span class="sum-lbl">Total WIP</span>
                </div>
                <a href="<?= site_url('wip/inventory') ?>" class="full-link">
                    Lihat Detail <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
    <div class="table-responsive" style="max-height: 260px;">
        <?php $wipRows = $wipData['rows'] ?? []; ?>
        <table class="mini-table" style="font-size:.78rem;">
            <thead>
                <tr>
                    <th class="text-start" style="min-width:100px;">Proses</th>
                    <th class="text-start" style="min-width:80px;">Part No</th>
                    <th class="text-start" style="min-width:130px;">Part Name</th>
                    <th style="width:75px;">Stock</th>
                    <th style="width:75px;">Transfer</th>
                    <th style="width:85px;">Total WIP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($wipRows)): ?>
                <tr><td colspan="6" class="empty-state text-center py-4">
                    <i class="bi bi-inbox d-block fs-3 mb-2 text-muted"></i>
                    <span class="text-muted">Belum ada data WIP</span>
                </td></tr>
                <?php else: ?>
                <?php
                $lastProc = null;
                foreach ($wipRows as $wr):
                ?>
                <tr>
                    <td>
                        <?php if ($lastProc !== $wr['process_name']): ?>
                        <span class="wip-process-badge"><?= esc($wr['process_name']) ?></span>
                        <?php $lastProc = $wr['process_name']; endif; ?>
                    </td>
                    <td style="font-weight:800; color:#0f172a; font-size:.77rem;"><?= esc($wr['part_no']) ?></td>
                    <td style="color:#64748b; font-size:.75rem; max-width:130px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= esc($wr['part_name']) ?>"><?= esc($wr['part_name']) ?></td>
                    <td class="text-center">
                        <span class="badge-stock badge-in"><?= number_format($wr['stock']) ?></span>
                    </td>
                    <td class="text-center">
                        <?php if ($wr['transfer'] > 0): ?>
                        <span class="badge-stock badge-out"><?= number_format($wr['transfer']) ?></span>
                        <?php else: ?>
                        <span class="text-muted" style="font-size:.7rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <span class="wip-total-badge"><?= number_format($wr['wip_total']) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══ ASAKAI SUMMARY ══ -->
<div class="dash-card">
    <div class="dash-card-header">
        <div class="section-title" style="border:none; margin-bottom:0; padding-bottom:0;">
            <div class="icon-box" style="background:#fef3c7; color:#d97706;"><i class="bi bi-sunrise"></i></div>
            <div>
                <h5>Asakai Summary</h5>
                <small>Daily production summary per section &amp; shift — termasuk nama operator</small>
            </div>
            <a href="<?= site_url('dashboard/asakai') ?>" class="full-link">
                Lihat Penuh <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>
    <div class="table-responsive" style="max-height: 440px;">
        <?php
        $asakaiShifts   = $asakai['shifts'] ?? [];
        $asakaiSections = $asakai['sections'] ?? [];
        $asakaiOps      = $asakai['operatorData'] ?? [];
        $shiftCount     = count($asakaiShifts);
        $totalCols      = 2 + ($shiftCount * 7) + 5;
        ?>
        <table class="mini-table asakai-mini">
            <thead>
                <tr>
                    <th rowspan="2" style="text-align:left; min-width:100px;">Section</th>
                    <th rowspan="2" style="text-align:left; min-width:130px;">Part</th>
                    <?php foreach ($asakaiShifts as $sh): ?>
                    <th colspan="7" style="background:#334155!important; text-align:center;"><?= esc($sh['shift_name']) ?></th>
                    <?php endforeach; ?>
                    <th colspan="5" style="background:#064e3b!important; text-align:center;">TOTAL</th>
                </tr>
                <tr>
                    <?php foreach ($asakaiShifts as $sh): ?>
                    <th style="width:50px; background:#475569!important; text-align:center;">Target</th>
                    <th style="width:50px; background:#475569!important; text-align:center;">Actual</th>
                    <th style="width:50px; background:#475569!important; text-align:center;">% NG</th>
                    <th style="width:50px; background:#475569!important; text-align:center;">DT (m)</th>
                    <th style="width:50px; background:#475569!important; text-align:center;">Eff%</th>
                    <th style="width:100px; background:#475569!important; text-align:center;">Operator</th>
                    <th style="width:120px; background:#475569!important; text-align:center;">Remark</th>
                    <?php endforeach; ?>
                    <th style="width:75px; background:#065f46!important; text-align:center;">Target</th>
                    <th style="width:75px; background:#065f46!important; text-align:center;">Actual</th>
                    <th style="width:50px; background:#065f46!important; text-align:center;">% NG</th>
                    <th style="width:50px; background:#065f46!important; text-align:center;">DT</th>
                    <th style="width:80px; background:#065f46!important; text-align:center;">Eff%</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($asakaiSections)): ?>
                <tr><td colspan="<?= $totalCols ?>" class="empty-state text-center py-4">
                    <i class="bi bi-inbox d-block fs-3 mb-2 text-muted"></i>
                    <span class="text-muted">Belum ada data produksi hari ini</span>
                </td></tr>
                <?php else: ?>
                    <?php foreach ($asakaiSections as $secName => $secData): ?>
                    <tr>
                        <td colspan="<?= $totalCols ?>" class="section-header-row text-start">
                            <i class="bi bi-chevron-right me-2"></i><?= esc($secName) ?> (<?= $secData['code'] ?>)
                            <span style="margin-left:.5rem; font-size:.65rem; opacity:.7; font-weight:600;">Total Eff: <?= $secData['total']['eff'] ?>%</span>
                        </td>
                    </tr>
                    <?php
                    $secOps = $asakaiOps[$secName] ?? [];
                    if (!empty($secOps)):
                    ?>
                    <tr class="operator-row">
                        <td colspan="2" style="text-align:right; padding-right:.8rem;">
                            <span class="op-badge op-badge-op"><i class="bi bi-person-fill me-1"></i>Operator</span>
                        </td>
                        <?php foreach ($asakaiShifts as $sh): $opInfo = $secOps[$sh['id']] ?? []; ?>
                        <td colspan="7" class="text-center">
                            <?php if (!empty($opInfo['operator']) && $opInfo['operator'] !== '-'): ?>
                            <span style="font-weight:700; color:#0369a1; font-size:.72rem;"><i class="bi bi-person me-1"></i><?= esc($opInfo['operator']) ?></span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <td colspan="5" style="background:#f0f9ff;"></td>
                    </tr>
                    <tr class="leader-row">
                        <td colspan="2" style="text-align:right; padding-right:.8rem;">
                            <span class="op-badge op-badge-ld"><i class="bi bi-person-badge-fill me-1"></i>Leader</span>
                        </td>
                        <?php foreach ($asakaiShifts as $sh): $opInfo = $secOps[$sh['id']] ?? []; ?>
                        <td colspan="7" class="text-center">
                            <?php if (!empty($opInfo['leader']) && $opInfo['leader'] !== '-'): ?>
                            <span style="font-weight:700; color:#166534; font-size:.72rem;"><i class="bi bi-person-badge me-1"></i><?= esc($opInfo['leader']) ?></span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <td colspan="5" style="background:#f0fdf4;"></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (empty($secData['products'])): ?>
                    <tr><td colspan="<?= $totalCols ?>" class="text-center text-muted py-2" style="font-size:.78rem;">Tidak ada produk terjadwal hari ini</td></tr>
                    <?php else: ?>
                    <?php foreach ($secData['products'] as $prod): ?>
                    <tr>
                        <td style="font-size:.75rem; color:#64748b;"><?= esc($prod['part_no']) ?></td>
                        <td style="font-weight:700; font-size:.77rem; color:#0f172a; max-width:130px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= esc($prod['part_name']) ?>"><?= esc($prod['part_name']) ?></td>
                        <?php foreach ($asakaiShifts as $sh):
                            $sid = (int)$sh['id'];
                            $sd  = $prod['shifts'][$sid] ?? ['target'=>0,'fg'=>0,'eff'=>0];
                            $ec  = $sd['eff'] >= 95 ? 'eff-hi' : ($sd['eff'] >= 80 ? 'eff-mid' : ($sd['eff'] > 0 ? 'eff-lo' : ''));
                        ?>
                        <td class="text-center text-muted"><?= number_format($sd['target']) ?></td>
                        <td class="text-center fw-bold text-primary"><?= number_format($sd['fg']) ?></td>
                        <td class="text-center fw-bold text-danger"><?= isset($sd['ng_pct']) && $sd['ng_pct'] > 0 ? $sd['ng_pct'].'%' : '-' ?></td>
                        <td class="text-center fw-bold text-warning"><?= isset($sd['dt']) && $sd['dt'] > 0 ? $sd['dt'] : '-' ?></td>
                        <td class="text-center <?= $ec ?>"><?= $sd['eff'] ?>%</td>
                        <td style="font-size:.7rem; color:#64748b;"><?= esc(empty($sd['operator']) ? '-' : $sd['operator']) ?></td>
                        <td style="font-size:.7rem; color:#64748b; white-space:normal;"><?= esc(empty($sd['remark']) ? '-' : $sd['remark']) ?></td>
                        <?php endforeach; ?>
                        <?php 
                        $tc = $prod['total_eff'] >= 95 ? 'eff-hi' : ($prod['total_eff'] >= 80 ? 'eff-mid' : ($prod['total_eff'] > 0 ? 'eff-lo' : ''));
                        ?>
                        <td class="text-center" style="background:#f0fdf4!important; font-weight:800; color:#334155;"><?= number_format($prod['total_target']) ?></td>
                        <td class="text-center" style="background:#f0fdf4!important; font-weight:800; color:#1d4ed8;"><?= number_format($prod['total_fg']) ?></td>
                        <td class="text-center" style="background:#f0fdf4!important; font-weight:800; color:#dc2626;"><?= isset($prod['total_ng_pct']) && $prod['total_ng_pct'] > 0 ? $prod['total_ng_pct'].'%' : '-' ?></td>
                        <td class="text-center" style="background:#f0fdf4!important; font-weight:800; color:#d97706;"><?= isset($prod['total_dt']) && $prod['total_dt'] > 0 ? $prod['total_dt'] : '-' ?></td>
                        <td class="text-center <?= $tc ?>" style="background:#f0fdf4!important;"><?= $prod['total_eff'] ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="2" class="total-row text-end" style="background:#f0fdf4!important;">
                            <span style="font-size:.7rem; letter-spacing:.06em; text-transform:uppercase;">Section Total</span>
                        </td>
                        <td colspan="<?= $shiftCount * 7 ?>" style="background:#f0fdf4;"></td>
                        <td class="text-center total-row"><?= number_format($secData['total']['target']) ?></td>
                        <td class="text-center total-row text-primary"><?= number_format($secData['total']['fg']) ?></td>
                        <td class="text-center total-row text-danger"><?= isset($secData['total']['ng_pct']) && $secData['total']['ng_pct'] > 0 ? $secData['total']['ng_pct'].'%' : '-' ?></td>
                        <td class="text-center total-row text-warning"><?= isset($secData['total']['dt']) && $secData['total']['dt'] > 0 ? $secData['total']['dt'] : '-' ?></td>
                        <td class="text-center total-row"><?= $secData['total']['eff'] ?>%</td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══ DCB + SCD ══ -->
<div class="row g-3 mb-3">

    <!-- DCB -->
    <div class="col-lg-6">
        <div class="dash-card" style="height:100%; margin-bottom:0;">
            <div class="dash-card-header">
                <div class="section-title" style="border:none; margin-bottom:0; padding-bottom:0;">
                    <div class="icon-box" style="background:#fef3c7; color:#f59e0b;"><i class="bi bi-clipboard2-data"></i></div>
                    <div>
                        <h5>Delivery Control Board</h5>
                        <small>DENSO · SUZUKI · ISUZU</small>
                    </div>
                    <a href="<?= site_url('finished-good/delivery-control-board?date='.$date) ?>" class="full-link">
                        Detail <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="table-responsive" style="max-height: 320px;">
                <?php $dcbGroups = $dcbData['groups'] ?? []; ?>
                <table class="mini-table dcb-mini" style="font-size:.72rem;">
                    <thead>
                        <tr>
                            <th class="text-start" style="min-width:120px;">Part Name</th>
                            <th style="width:60px;">Target</th>
                            <th style="width:50px;">R1-T</th>
                            <th style="width:50px;">R1-A</th>
                            <th style="width:50px;">R2-T</th>
                            <th style="width:50px;">R2-A</th>
                            <th style="width:38px;">Sts</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dcbGroups)): ?>
                        <tr><td colspan="7" class="empty-state text-center py-4">
                            <i class="bi bi-inbox d-block fs-3 mb-2 text-muted"></i>
                            <span class="text-muted" style="font-size:.78rem;">Tidak ada data delivery hari ini</span>
                        </td></tr>
                        <?php else: ?>
                        <?php foreach ($dcbGroups as $grp): ?>
                        <!-- Customer Header -->
                        <tr class="dcb-cust-row">
                            <td colspan="7">
                                <i class="bi bi-building me-1"></i><?= esc($grp['customer_name']) ?>
                            </td>
                        </tr>
                        <!-- Parts -->
                        <?php foreach ($grp['parts'] as $r): ?>
                        <?php
                        $t = (int)$r['target']; $a = (int)$r['actual'];
                        $hasData = $r['has_data'];
                        $stClass = $t <= 0 ? 'na' : ($a >= $t ? 'ok' : ($a > 0 ? 'ng' : 'na'));
                        $stIcon  = $t <= 0 ? '—'  : ($a >= $t ? '<i class="bi bi-check-lg"></i>' : ($a > 0 ? '<i class="bi bi-x-lg"></i>' : '—'));
                        $t1Class = $r['rit1_t'] > 0 ? ($r['rit1_a'] >= $r['rit1_t'] ? 'act-ok' : 'act-ng-val') : 'act-na';
                        $t2Class = $r['rit2_t'] > 0 ? ($r['rit2_a'] >= $r['rit2_t'] ? 'act-ok' : 'act-ng-val') : 'act-na';
                        ?>
                        <tr class="<?= !$hasData ? 'dcb-part-blank' : '' ?>">
                            <td style="font-weight:<?= $hasData ? '700' : '500' ?>; font-size:.7rem; max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; padding-left:1.2rem;">
                                <?= esc($r['part_name']) ?>
                            </td>
                            <td class="text-center"><?= $t > 0 ? '<span style="font-weight:800;color:#1d4ed8;">'.number_format($t).'</span>' : '<span class="text-muted" style="font-size:.65rem;">—</span>' ?></td>
                            <td class="text-center" style="color:#64748b;"><?= $r['rit1_t'] > 0 ? number_format($r['rit1_t']) : '—' ?></td>
                            <td class="text-center"><span class="act-badge <?= $t1Class ?>"><?= ($r['rit1_a'] > 0 || $r['rit1_t'] > 0) ? number_format($r['rit1_a']) : '—' ?></span></td>
                            <td class="text-center" style="color:#64748b;"><?= $r['rit2_t'] > 0 ? number_format($r['rit2_t']) : '—' ?></td>
                            <td class="text-center"><span class="act-badge <?= $t2Class ?>"><?= ($r['rit2_a'] > 0 || $r['rit2_t'] > 0) ? number_format($r['rit2_a']) : '—' ?></span></td>
                            <td class="text-center"><span class="dcb-status <?= $stClass ?>"><?= $stIcon ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- DCB Summary -->
            <?php if ($dcbData['grand_target'] > 0): ?>
            <div class="summary-strip">
                <div class="sum-stat"><span class="sum-val text-primary"><?= number_format($dcbData['grand_target']) ?></span><span class="sum-lbl">Grand Target</span></div>
                <div class="sum-stat"><span class="sum-val" style="color:#16a34a;"><?= number_format($dcbData['grand_actual']) ?></span><span class="sum-lbl">Grand Actual</span></div>
                <?php $dcbPct = $dcbData['grand_target'] > 0 ? round($dcbData['grand_actual'] / $dcbData['grand_target'] * 100, 1) : 0; ?>
                <div class="sum-stat"><span class="sum-val" style="color:<?= $dcbPct >= 100 ? '#16a34a' : '#dc2626' ?>;"><?= $dcbPct ?>%</span><span class="sum-lbl">Achievement</span></div>
            </div>
            <?php endif; ?>
        </div>
    </div>


    <!-- SCD -->
    <div class="col-lg-6">
        <div class="dash-card" style="height:100%; margin-bottom:0;">
            <div class="dash-card-header">
                <div class="section-title" style="border:none; margin-bottom:0; padding-bottom:0;">
                    <div class="icon-box" style="background:#f0fdf4; color:#16a34a;"><i class="bi bi-truck-front"></i></div>
                    <div>
                        <h5>Special Control Delivery</h5>
                        <small>Delivery khusus hari ini</small>
                    </div>
                    <a href="<?= site_url('finished-good/special-control-delivery?date='.$date) ?>" class="full-link">
                        Detail <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="table-responsive" style="max-height: 280px;">
                <?php $scdRows = $scdData['rows'] ?? []; ?>
                <table class="mini-table scd-mini" style="font-size:.73rem;">
                    <thead>
                        <tr>
                            <th class="text-start" style="min-width:90px;">Customer</th>
                            <th class="text-start" style="min-width:110px;">Part</th>
                            <th style="width:60px;">Plan</th>
                            <th style="width:45px; background:#065f46!important;">R1</th>
                            <th style="width:45px; background:#4338ca!important;">R2</th>
                            <th style="width:45px; background:#c2410c!important;">R3</th>
                            <th style="width:55px; background:#111827!important;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($scdRows)): ?>
                        <tr><td colspan="7" class="empty-state text-center py-4">
                            <i class="bi bi-inbox d-block fs-3 mb-2 text-muted"></i>
                            <span class="text-muted" style="font-size:.78rem;">Belum ada data special delivery hari ini</span>
                        </td></tr>
                        <?php else: ?>
                        <?php foreach ($scdRows as $r): ?>
                        <?php
                        $plan = (int)$r['plan_qty'];
                        $total = (int)$r['total_actual'];
                        $stColor = ($plan > 0 && $total >= $plan) ? '#16a34a' : ($total > 0 ? '#dc2626' : '#94a3b8');
                        ?>
                        <tr>
                            <td style="font-size:.72rem; font-weight:700; max-width:90px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= esc($r['customer_name'] ?: '—') ?></td>
                            <td style="font-weight:700; font-size:.72rem; max-width:110px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= esc($r['part_name'] ?: '—') ?></td>
                            <td class="text-center" style="color:#1d4ed8; font-weight:800;"><?= $plan > 0 ? number_format($plan) : '—' ?></td>
                            <td class="text-center" style="background:#f0fdf4;"><?= (int)$r['rit1_qty'] > 0 ? number_format($r['rit1_qty']) : '<span class="text-muted">—</span>' ?></td>
                            <td class="text-center" style="background:#eef2ff;"><?= (int)$r['rit2_qty'] > 0 ? number_format($r['rit2_qty']) : '<span class="text-muted">—</span>' ?></td>
                            <td class="text-center" style="background:#fff7ed;"><?= (int)$r['rit3_qty'] > 0 ? number_format($r['rit3_qty']) : '<span class="text-muted">—</span>' ?></td>
                            <td class="text-center" style="font-weight:900; color:<?= $stColor ?>;"><?= $total > 0 ? number_format($total) : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($scdData['grand_plan'] > 0 || $scdData['grand_actual'] > 0): ?>
            <div class="summary-strip">
                <div class="sum-stat"><span class="sum-val text-primary"><?= number_format($scdData['grand_plan']) ?></span><span class="sum-lbl">Grand Plan</span></div>
                <div class="sum-stat"><span class="sum-val" style="color:#16a34a;"><?= number_format($scdData['grand_actual']) ?></span><span class="sum-lbl">Actual Total</span></div>
                <?php $scdPct = $scdData['grand_plan'] > 0 ? round($scdData['grand_actual'] / $scdData['grand_plan'] * 100, 1) : 0; ?>
                <div class="sum-stat"><span class="sum-val" style="color:<?= $scdPct >= 100 ? '#16a34a' : '#dc2626' ?>;"><?= $scdPct ?>%</span><span class="sum-lbl">Achievement</span></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ══ ROW 5: QC CHARTS ══ -->
<div class="row g-3 mb-3">
    <!-- Monthly Chart -->
    <div class="col-lg-6">
        <div class="dash-card" style="margin-bottom:0;">
            <div class="dash-card-header">
                <div class="section-title" style="border:none; margin-bottom:0; padding-bottom:0;">
                    <div class="icon-box" style="background:#fee2e2; color:#dc2626;"><i class="bi bi-bar-chart-line"></i></div>
                    <div>
                        <h5>QC Monthly — <?= date('F Y') ?></h5>
                        <small>OK vs NG per hari dalam bulan ini</small>
                    </div>
                </div>
            </div>
            <div class="p-3">
                <div class="chart-wrap">
                    <canvas id="qcMonthlyChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <!-- Yearly Chart -->
    <div class="col-lg-6">
        <div class="dash-card" style="margin-bottom:0;">
            <div class="dash-card-header">
                <div class="section-title" style="border:none; margin-bottom:0; padding-bottom:0;">
                    <div class="icon-box" style="background:#fef3c7; color:#d97706;"><i class="bi bi-bar-chart"></i></div>
                    <div>
                        <h5>QC Yearly — <?= date('Y') ?></h5>
                        <small>OK vs NG per bulan dalam tahun ini</small>
                    </div>
                </div>
            </div>
            <div class="p-3">
                <div class="chart-wrap">
                    <canvas id="qcYearlyChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- /dash-wrap -->

<!-- ══ SCRIPTS ══ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
/* ── Live Clock ── */
(function tickClock() {
    const el = document.getElementById('liveClock');
    if (el) el.textContent = new Date().toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
    setTimeout(tickClock, 1000);
})();

/* ── Chart Defaults ── */
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.color = '#64748b';
Chart.defaults.plugins.legend.labels.boxWidth = 12;
Chart.defaults.plugins.legend.labels.padding = 16;

/* ══ PERFORMANCE DONUT CHARTS ══ */
function makeDonut(canvasId, okPct, ngPct, dtPct, color) {
    const el = document.getElementById(canvasId);
    if (!el) return;
    const remaining = Math.max(0, 100 - okPct);
    new Chart(el, {
        type: 'doughnut',
        data: {
            labels: ['OK', 'Sisa'],
            datasets: [{
                data: [okPct, remaining],
                backgroundColor: [
                    okPct >= 95 ? '#16a34a' : (okPct >= 75 ? '#f59e0b' : '#ef4444'),
                    '#e2e8f0'
                ],
                borderWidth: 0,
                hoverBorderWidth: 2,
            }]
        },
        options: {
            responsive: false,
            cutout: '72%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.label + ': ' + ctx.parsed + '%'
                    }
                }
            },
            animation: { animateRotate: true, duration: 800 }
        }
    });
}

/* Init donut DC */
makeDonut(
    'perfChartDC',
    <?= (float)$perfDC['ok_achievement'] ?>,
    <?= (float)$perfDC['ng_rate'] ?>,
    <?= (float)$perfDC['downtime_rate'] ?>,
    '#1d4ed8'
);

/* Init donut MC */
makeDonut(
    'perfChartMC',
    <?= (float)$perfMC['ok_achievement'] ?>,
    <?= (float)$perfMC['ng_rate'] ?>,
    <?= (float)$perfMC['downtime_rate'] ?>,
    '#7c3aed'
);

/* ══ QC Monthly Chart ══ */
const monthlyData = <?= json_encode($qcChartData['monthly'] ?? ['labels'=>[],'ok'=>[],'ng'=>[]]) ?>;
new Chart(document.getElementById('qcMonthlyChart'), {
    type: 'bar',
    data: {
        labels: monthlyData.labels,
        datasets: [
            {
                label: 'OK',
                data: monthlyData.ok,
                backgroundColor: 'rgba(34,197,94,.75)',
                borderRadius: 4,
                borderSkipped: false,
            },
            {
                label: 'NG',
                data: monthlyData.ng,
                backgroundColor: 'rgba(239,68,68,.75)',
                borderRadius: 4,
                borderSkipped: false,
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 9 } } },
            y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 9 } } }
        }
    }
});

/* ══ QC Yearly Chart ══ */
const yearlyData = <?= json_encode($qcChartData['yearly'] ?? ['labels'=>[],'ok'=>[],'ng'=>[]]) ?>;
new Chart(document.getElementById('qcYearlyChart'), {
    type: 'bar',
    data: {
        labels: yearlyData.labels,
        datasets: [
            {
                label: 'OK',
                data: yearlyData.ok,
                backgroundColor: 'rgba(59,130,246,.75)',
                borderRadius: 4,
                borderSkipped: false,
            },
            {
                label: 'NG',
                data: yearlyData.ng,
                backgroundColor: 'rgba(245,158,11,.75)',
                borderRadius: 4,
                borderSkipped: false,
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10 } } },
            y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 9 } } }
        }
    }
});
</script>

<?= $this->endSection() ?>
