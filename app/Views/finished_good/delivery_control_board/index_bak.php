<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

/* ══════════════════════════════════════════════
   DELIVERY CONTROL BOARD
══════════════════════════════════════════════ */
.dcb-page { font-family: 'Inter', sans-serif; }

/* ── Header Banner ── */
.dcb-header-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 55%, #0f172a 100%);
    border-radius: 16px; padding: 1.4rem 2rem; margin-bottom: 1.4rem;
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;
    box-shadow: 0 8px 32px rgba(0,0,0,.35); border: 1px solid rgba(255,255,255,.07);
}
.dcb-logo-box {
    width: 52px; height: 52px;
    background: linear-gradient(135deg, #ef4444, #b91c1c);
    border-radius: 12px; display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; font-weight: 900; color: white; letter-spacing: -1px;
    box-shadow: 0 4px 12px rgba(239,68,68,.4); flex-shrink: 0;
}
.dcb-company-name { color: #94a3b8; font-size: .72rem; font-weight: 600; letter-spacing: .09em; text-transform: uppercase; }
.dcb-date-info    { color: #e2e8f0; font-size: .88rem; font-weight: 600; margin-top: 2px; }
.dcb-title-main {
    font-size: 1.7rem; font-weight: 900; color: white;
    letter-spacing: .05em; text-transform: uppercase;
    text-shadow: 0 2px 14px rgba(59,130,246,.5);
}
.dcb-date-filter { display: flex; align-items: center; gap: .5rem; }
.dcb-date-filter input[type="date"] {
    background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2);
    color: white; border-radius: 8px; padding: .4rem .8rem; font-weight: 600; font-size: .875rem;
}
.dcb-date-filter input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); }
.dcb-date-filter button {
    background: #3b82f6; border: none; color: white;
    border-radius: 8px; padding: .4rem 1rem; font-weight: 700; font-size: .875rem; cursor: pointer;
}
.dcb-date-filter button:hover { background: #2563eb; }

/* ── Board Wrapper ── */
.dcb-board-wrap { background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.1); border: 1px solid #e2e8f0; }

/* ── Table Core ── */
.dcb-table { width: 100%; border-collapse: collapse; font-size: .81rem; }
.dcb-table th {
    background: #1e293b; color: white; font-weight: 700; text-transform: uppercase;
    font-size: .7rem; letter-spacing: .06em; padding: .65rem .7rem;
    border: 1px solid #334155; text-align: center; white-space: nowrap;
}
.dcb-table th.th-left { text-align: left; }
.dcb-table td { padding: .48rem .65rem; border: 1px solid #e8ecf0; vertical-align: middle; color: #1e293b; font-weight: 500; }

/* ── Column group colours ── */
.th-target { background: #1d4ed8 !important; }
.th-rit1   { background: #065f46 !important; }
.th-rit2   { background: #7c3aed !important; }
.th-status { background: #b45309 !important; }
.th-ket    { background: #374151 !important; }
.th-aksi   { background: #1f2937 !important; }

/* ── Customer group header ── */
.cust-group-row td {
    background: linear-gradient(90deg, #1e293b 0%, #2d3f55 100%);
    color: #f8fafc; font-weight: 800; font-size: .78rem;
    text-transform: uppercase; letter-spacing: .07em; padding: .55rem 1rem;
    border-color: #334155 !important;
}
.cust-group-row.fixed-cust td { background: linear-gradient(90deg, #14532d 0%, #166534 100%); }
.cust-badge { background: rgba(255,255,255,.15); border-radius: 5px; padding: .15rem .5rem; font-size: .65rem; margin-left: .5rem; }

/* ── Fixed row styling ── */
.row-fixed td:first-child { background: #f0fdf4; }

/* ── Part name cell ── */
.td-no { text-align: center; color: #94a3b8; font-size: .72rem; font-weight: 600; width: 34px; }
.td-partname { font-weight: 700; color: #0f172a; white-space: nowrap; }
.td-partno   { font-size: .68rem; color: #64748b; display: block; }

/* ── Editable inputs ── */
.dcb-qty-input {
    width: 68px; text-align: center; border: 1.5px solid #cbd5e1;
    border-radius: 6px; padding: .26rem .4rem; font-weight: 700; font-size: .81rem;
    color: #0f172a; background: white; transition: border-color .2s, box-shadow .2s;
}
.dcb-qty-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.18); }
.dcb-kanban-input {
    width: 58px; text-align: center; border: 1.5px dashed #94a3b8;
    border-radius: 6px; padding: .26rem .4rem; font-weight: 600; font-size: .8rem;
    color: #64748b; background: #f8fafc;
}
.dcb-kanban-input:focus { outline: none; border-color: #94a3b8; }
.dcb-ket-input {
    min-width: 110px; width: 100%; border: 1.5px solid #e2e8f0;
    border-radius: 6px; padding: .26rem .5rem; font-size: .79rem; color: #334155; background: white;
}
.dcb-ket-input:focus { outline: none; border-color: #3b82f6; }

/* ── Actual QTY pills ── */
.actual-qty {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 58px; padding: .28rem .55rem; border-radius: 7px;
    font-weight: 800; font-size: .83rem; transition: background .3s;
}
.actual-qty.blue { background: #dbeafe; color: #1d4ed8; border: 1.5px solid #93c5fd; }
.actual-qty.red  { background: #fee2e2; color: #dc2626; border: 1.5px solid #fca5a5; }
.actual-qty.grey { background: #f1f5f9; color: #94a3b8; border: 1.5px solid #e2e8f0; }

/* ── Status dots ── */
.status-dot {
    width: 26px; height: 26px; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .62rem; font-weight: 800;
}
.status-dot.ok { background: #dbeafe; color: #1d4ed8; border: 2px solid #93c5fd; }
.status-dot.ng { background: #fee2e2; color: #dc2626; border: 2px solid #fca5a5; }
.status-dot.na { background: #f1f5f9; color: #94a3b8; border: 2px solid #e2e8f0; }

/* ── Row action buttons ── */
.btn-save-row {
    background: #22c55e; color: white; border: none; border-radius: 6px;
    padding: .24rem .5rem; font-size: .72rem; font-weight: 700; cursor: pointer; transition: background .2s;
}
.btn-save-row:hover { background: #16a34a; }
.btn-del-row {
    background: transparent; color: #ef4444; border: 1.5px solid #fca5a5;
    border-radius: 6px; padding: .24rem .44rem; font-size: .72rem; cursor: pointer; transition: all .2s;
}
.btn-del-row:hover { background: #fee2e2; }
.btn-fixed-lock {
    background: transparent; color: #94a3b8; border: 1.5px solid #e2e8f0;
    border-radius: 6px; padding: .24rem .44rem; font-size: .72rem; cursor: default;
}

/* ── Add-row section ── */
.dcb-add-section {
    border-top: 2px dashed #cbd5e1; background: #f8fafc; padding: .9rem 1.2rem;
}
.dcb-add-section label { font-size: .75rem; font-weight: 700; color: #374151; margin-bottom: 3px; display: block; }
.btn-add-dcb {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; border: none;
    border-radius: 8px; padding: .42rem 1rem; font-weight: 700; font-size: .8rem; cursor: pointer;
    display: inline-flex; align-items: center; gap: .4rem; transition: opacity .2s, transform .1s;
    box-shadow: 0 2px 8px rgba(59,130,246,.3);
}
.btn-add-dcb:hover { opacity: .9; transform: translateY(-1px); }

/* ── Summary bar ── */
.dcb-summary-bar {
    display: flex; gap: .8rem; padding: .9rem 1.2rem;
    background: #f8fafc; border-top: 1px solid #e2e8f0; flex-wrap: wrap;
}
.summary-stat {
    display: flex; flex-direction: column; align-items: center;
    background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: .55rem 1rem; min-width: 90px;
}
.summary-stat .val { font-size: 1.25rem; font-weight: 900; color: #0f172a; }
.summary-stat .lbl { font-size: .65rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; margin-top: 2px; }

/* ── Toast ── */
.saving-toast {
    position: fixed; bottom: 22px; right: 22px;
    background: #0f172a; color: white; border-radius: 10px;
    padding: .55rem 1.1rem; font-weight: 700; font-size: .85rem;
    box-shadow: 0 8px 24px rgba(0,0,0,.3); display: none; z-index: 9999;
    animation: slideUp .3s ease;
}
@keyframes slideUp { from { transform: translateY(8px); opacity: 0; } to { transform: none; opacity: 1; } }
.spin { display: inline-block; animation: spin .65s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<div class="dcb-page">

<!-- ══ HEADER BANNER ══ -->
<div class="dcb-header-banner">
    <div class="d-flex align-items-center gap-3">
        <div class="dcb-logo-box">CP</div>
        <div>
            <div class="dcb-company-name">PT. Cikarang Perkasa Mfg.</div>
            <div class="dcb-date-info">
                <?= strtoupper(date('l, d F Y', strtotime($date))) ?>
            </div>
        </div>
    </div>
    <div class="dcb-title-main">
        <i class="bi bi-clipboard2-data me-2"></i> Delivery Control Board
    </div>
    <form method="get" class="dcb-date-filter">
        <i class="bi bi-calendar3 text-white"></i>
        <input type="date" name="date" value="<?= esc($date) ?>">
        <button type="submit"><i class="bi bi-search me-1"></i> Filter</button>
    </form>
</div>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success fw-bold mb-3 border-0 border-start border-4 border-success shadow-sm">
    <i class="bi bi-check-circle-fill me-2"></i><?= session()->getFlashdata('success') ?>
</div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="alert alert-danger fw-bold mb-3 border-0 border-start border-4 border-danger shadow-sm">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= session()->getFlashdata('error') ?>
</div>
<?php endif; ?>

<?php if (!$logged_in): ?>
<div class="alert alert-info fw-bold mb-3 border-0 border-start border-4 border-info shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-2">
    <span><i class="bi bi-eye me-2"></i>Mode <strong>View Only</strong> — Login untuk menginput atau mengubah data target delivery.</span>
    <a href="/login" class="btn btn-sm btn-primary fw-bold"><i class="bi bi-box-arrow-in-right me-1"></i>Login</a>
</div>
<?php endif; ?>

<!-- ══ EXPORT TOOLBAR ══ -->
<div class="d-flex gap-2 mb-3 flex-wrap">
    <button onclick="exportPDF()" style="background:#ef4444;color:white;border:none;border-radius:8px;padding:.4rem .9rem;font-weight:700;font-size:.8rem;cursor:pointer;display:flex;align-items:center;gap:.4rem;">
        <i class="bi bi-printer-fill"></i> Export PDF
    </button>
    <button onclick="exportExcel()" style="background:#16a34a;color:white;border:none;border-radius:8px;padding:.4rem .9rem;font-weight:700;font-size:.8rem;cursor:pointer;display:flex;align-items:center;gap:.4rem;">
        <i class="bi bi-file-earmark-excel-fill"></i> Export Excel
    </button>
</div>

<!-- ══ BOARD TABLE ══ -->
<div class="dcb-board-wrap">
<div class="table-responsive">
<table class="dcb-table" id="dcbTable">
    <thead>
        <tr>
            <th class="th-left" rowspan="2" style="min-width:160px; width:180px;">Customer</th>
            <th rowspan="2" style="width:36px">No.</th>
            <th class="th-left" rowspan="2" style="min-width:130px">Part Name</th>
            <th colspan="2" class="th-target">Target Delivery</th>
            <th colspan="2" class="th-rit1">Actual RIT-1</th>
            <th colspan="2" class="th-rit2">Actual RIT-2</th>
            <th rowspan="2" class="th-status" style="width:48px">Status</th>
            <th rowspan="2" class="th-ket th-left" style="min-width:120px">Keterangan</th>
            <?php if ($logged_in): ?>
            <th rowspan="2" class="th-aksi" style="width:68px">Aksi</th>
            <?php endif; ?>
        </tr>
        <tr>
            <th class="th-target" style="width:72px">QTY</th>
            <th class="th-target" style="width:68px">Kanban</th>
            <th class="th-rit1"  style="width:72px">QTY</th>
            <th class="th-rit1"  style="width:68px">Kanban</th>
            <th class="th-rit2"  style="width:72px">QTY</th>
            <th class="th-rit2"  style="width:68px">Kanban</th>
        </tr>
    </thead>
    <tbody id="dcbBody">

    <?php
    $grandTarget = 0; $grandRit1 = 0; $grandRit2 = 0;
    $totalParts  = 0;

    /* ── Fixed customers in fixed order, then custom customers ── */
    // $fixedCustomers is passed from the controller (avoids calling static method in view)
    $orderedCustomers = array_unique(array_merge($fixedCustomers, array_keys($displayGroups)));

    foreach ($orderedCustomers as $custName):
        if (!isset($displayGroups[$custName])) continue;
        $rows    = $displayGroups[$custName];
        $isFixed = in_array($custName, $fixedCustomers);
        $rowNo   = 1;
    ?>
        <!-- Customer group header -->
        <tr class="cust-group-row <?= $isFixed ? 'fixed-cust' : '' ?>">
            <td colspan="12">
                <?= esc($custName) ?>
                <?php if ($isFixed): ?>
                    <span class="cust-badge"><i class="bi bi-pin-fill me-1"></i>Tetap</span>
                <?php endif; ?>
            </td>
        </tr>

        <?php foreach ($rows as $r):
            $pid       = (int)$r['product_id'];
            $tQty      = (int)$r['target_qty'];
            $rit1Qty   = (int)($actualMap[$pid]['RIT-1'] ?? 0);
            $rit2Qty   = (int)($actualMap[$pid]['RIT-2'] ?? 0);
            $threshold = $tQty > 0 ? (int)ceil($tQty * 0.5) : 0;

            $rit1Class = $tQty <= 0 ? 'grey' : ($rit1Qty >= $threshold ? 'blue' : 'red');
            $rit2Class = $tQty <= 0 ? 'grey' : ($rit2Qty >= $threshold ? 'blue' : 'red');

            $totalAct = $rit1Qty + $rit2Qty;
            $statusClass = $tQty <= 0 ? 'na' : ($totalAct >= $tQty ? 'ok' : ($totalAct > 0 ? 'ng' : 'na'));
            $statusLabel = $tQty <= 0 ? '-'  : ($totalAct >= $tQty ? 'OK' : ($totalAct > 0 ? 'NG' : '-'));

            $grandTarget += $tQty;
            $grandRit1   += $rit1Qty;
            $grandRit2   += $rit2Qty;
            $totalParts++;

            // --- encode data attrs ---
            $isFixedRow  = (int)$r['is_fixed'];
            $dataId      = (int)$r['id'];
            $dataCname   = htmlspecialchars($r['customer_name'], ENT_QUOTES);
            $dataPname   = htmlspecialchars($r['part_name'], ENT_QUOTES);
        ?>
        <tr class="<?= $isFixedRow ? 'row-fixed' : '' ?>"
            data-id="<?= $dataId ?>"
            data-is-fixed="<?= $isFixedRow ?>"
            data-cname="<?= $dataCname ?>"
            data-pname="<?= $dataPname ?>"
            data-date="<?= esc($date) ?>"
            data-pid="<?= $pid ?>"
            data-cid="<?= (int)$r['customer_id'] ?>">

            <!-- Customer cell (show only on first row of group) -->
            <td style="font-size:.7rem; color:#64748b; background:<?= $isFixed ? '#f0fdf4' : '#f8fafc' ?>; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                <?php /* First row in group labels are in group header */ ?>
            </td>

            <!-- No. -->
            <td class="td-no"><?= $rowNo++ ?></td>

            <!-- Part Name -->
            <td class="td-partname">
                <?= esc($r['part_name']) ?>
            </td>

            <!-- TARGET QTY -->
            <td class="text-center" style="background:#eff6ff;">
                <?php if ($logged_in): ?>
                <input type="number" class="dcb-qty-input target-qty" value="<?= $tQty ?>" min="0" placeholder="0">
                <?php else: ?>
                <span class="fw-bold" style="color:#1d4ed8;"><?= $tQty > 0 ? number_format($tQty) : '—' ?></span>
                <?php endif; ?>
            </td>
            <!-- TARGET KANBAN -->
            <td class="text-center" style="background:#eff6ff;">
                <?php if ($logged_in): ?>
                <input type="number" class="dcb-kanban-input target-kanban" value="<?= $r['target_kanban'] !== null ? (int)$r['target_kanban'] : '' ?>" min="0" placeholder="—">
                <?php else: ?>
                <span class="text-muted"><?= $r['target_kanban'] !== null ? (int)$r['target_kanban'] : '—' ?></span>
                <?php endif; ?>
            </td>

            <!-- RIT-1 QTY -->
            <td class="text-center" style="background:#f0fdf4;">
                <span class="actual-qty <?= $rit1Class ?>">
                    <?= $pid > 0 && $rit1Qty > 0 ? number_format($rit1Qty) : '—' ?>
                </span>
            </td>
            <!-- RIT-1 KANBAN -->
            <td class="text-center" style="background:#f0fdf4;">
                <span class="actual-qty grey">—</span>
            </td>

            <!-- RIT-2 QTY -->
            <td class="text-center" style="background:#faf5ff;">
                <span class="actual-qty <?= $rit2Class ?>">
                    <?= $pid > 0 && $rit2Qty > 0 ? number_format($rit2Qty) : '—' ?>
                </span>
            </td>
            <!-- RIT-2 KANBAN -->
            <td class="text-center" style="background:#faf5ff;">
                <span class="actual-qty grey">—</span>
            </td>

            <!-- STATUS -->
            <td class="text-center">
                <span class="status-dot <?= $statusClass ?>"><?= $statusLabel ?></span>
            </td>

            <!-- KETERANGAN -->
            <td>
                <?php if ($logged_in): ?>
                <input type="text" class="dcb-ket-input ket-input"
                       value="<?= esc($r['keterangan'] ?? '') ?>"
                       placeholder="Keterangan…" maxlength="200">
                <?php else: ?>
                <span class="text-muted small"><?= esc($r['keterangan'] ?? '') ?: '—' ?></span>
                <?php endif; ?>
            </td>

            <!-- AKSI -->
            <?php if ($logged_in): ?>
            <td class="text-center">
                <div class="d-flex gap-1 justify-content-center">
                    <button class="btn-save-row" title="Simpan"><i class="bi bi-check-lg"></i></button>
                    <?php if ($isFixedRow): ?>
                        <button class="btn-fixed-lock" title="Baris tetap, tidak bisa dihapus">
                            <i class="bi bi-lock-fill"></i>
                        </button>
                    <?php else: ?>
                        <button class="btn-del-row" title="Hapus" data-id="<?= $dataId ?>">
                            <i class="bi bi-trash3"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </td>
            <?php endif; ?>
        </tr>
        <?php endforeach; // rows ?>
    <?php endforeach; // customers ?>

    </tbody>
</table>
</div>

<!-- ══ ADD CUSTOM ROW ══ -->
<?php if ($logged_in): ?>
<div class="dcb-add-section">
    <div class="fw-bold text-secondary mb-2" style="font-size:.8rem;">
        <i class="bi bi-plus-circle me-1 text-primary"></i>
        Tambah Customer / Part Lain
        <span class="text-muted fw-normal">(di luar daftar tetap)</span>
    </div>
    <div class="row g-2 align-items-end">
        <div class="col-auto">
            <label>Customer</label>
            <?php
            $custOpts = '<option value="">— Pilih Customer —</option>';
            foreach ($customers as $c) {
                $custOpts .= '<option value="' . (int)$c['id'] . '" data-name="' . esc($c['customer_name'], 'attr') . '">'
                           . esc($c['customer_name']) . '</option>';
            }
            ?>
            <select id="newCustSel" class="form-select form-select-sm fw-bold" style="min-width:180px;"><?= $custOpts ?></select>
        </div>
        <div class="col-auto">
            <label>Part</label>
            <?php
            $prodOpts = '<option value="">— Pilih Part —</option>';
            foreach ($products as $p) {
                $label = $p['part_no'] ? $p['part_no'] . ' — ' . $p['part_name'] : $p['part_name'];
                $prodOpts .= '<option value="' . (int)$p['id'] . '" data-name="' . esc($label, 'attr') . '">'
                           . esc($label) . '</option>';
            }
            ?>
            <select id="newProdSel" class="form-select form-select-sm fw-bold" style="min-width:200px;"><?= $prodOpts ?></select>
        </div>
        <div class="col-auto">
            <label>Target QTY</label>
            <input type="number" id="newTargetQty" class="dcb-qty-input" style="width:80px;" value="" min="0" placeholder="0">
        </div>
        <div class="col-auto">
            <label>Kanban</label>
            <input type="number" id="newTargetKan" class="dcb-kanban-input" style="width:70px;" value="" min="0" placeholder="—">
        </div>
        <div class="col">
            <label>Keterangan</label>
            <input type="text" id="newKet" class="dcb-ket-input" style="width:100%;" placeholder="Opsional…" maxlength="200">
        </div>
        <div class="col-auto">
            <button class="btn-add-dcb" id="btnAddDcb">
                <i class="bi bi-plus-lg"></i> Tambah
            </button>
        </div>
    </div>
</div>
<?php else: ?>
<div class="dcb-add-section d-flex align-items-center gap-3" style="background:#f0f9ff; border-top:2px dashed #bae6fd;">
    <i class="bi bi-lock-fill text-info fs-5"></i>
    <span class="text-secondary fw-semibold" style="font-size:.85rem;">
        <a href="/login" class="text-primary fw-bold">Login</a> untuk menambahkan atau mengubah data target delivery.
    </span>
</div>
<?php endif; ?>

<!-- ══ SUMMARY BAR ══ -->
<?php $grandActual = $grandRit1 + $grandRit2; ?>
<div class="dcb-summary-bar">
    <div class="summary-stat">
        <span class="val"><?= $totalParts ?></span>
        <span class="lbl">Total Part</span>
    </div>
    <div class="summary-stat">
        <span class="val text-primary"><?= number_format($grandTarget) ?></span>
        <span class="lbl">Target QTY</span>
    </div>
    <div class="summary-stat">
        <span class="val" style="color:#065f46;"><?= number_format($grandRit1) ?></span>
        <span class="lbl">Actual RIT-1</span>
    </div>
    <div class="summary-stat">
        <span class="val" style="color:#7c3aed;"><?= number_format($grandRit2) ?></span>
        <span class="lbl">Actual RIT-2</span>
    </div>
    <div class="summary-stat">
        <span class="val"><?= number_format($grandActual) ?></span>
        <span class="lbl">Total Actual</span>
    </div>
    <?php if ($grandTarget > 0): ?>
    <div class="summary-stat">
        <?php $pct = min(100, round($grandActual / $grandTarget * 100, 1)); ?>
        <span class="val" style="color:<?= $pct >= 100 ? '#16a34a' : '#dc2626' ?>;"><?= $pct ?>%</span>
        <span class="lbl">Achievement</span>
    </div>
    <?php endif; ?>
</div>
</div><!-- /dcb-board-wrap -->
</div><!-- /dcb-page -->

<!-- ══ TOAST ══ -->
<div class="saving-toast" id="savingToast">
    <i class="bi bi-arrow-repeat me-2 spin" id="toastIcon"></i>
    <span id="toastMsg">Menyimpan…</span>
</div>

<script>
const BOARD_DATE      = '<?= esc($date) ?>';
const SAVE_TARGET_URL = '<?= site_url('finished-good/delivery-control-board/save-target') ?>';
const DELETE_ROW_URL  = '<?= site_url('finished-good/delivery-control-board/delete') ?>';

/* ── Toast ── */
let _toastTimer;
function showToast(msg, type = 'spin') {
    const t   = document.getElementById('savingToast');
    const ico = document.getElementById('toastIcon');
    document.getElementById('toastMsg').textContent = msg;
    ico.className = type === 'spin'
        ? 'bi bi-arrow-repeat me-2 spin'
        : (type === 'ok' ? 'bi bi-check-circle-fill me-2' : 'bi bi-x-circle-fill me-2');
    t.style.display = 'block';
    clearTimeout(_toastTimer);
    if (type !== 'spin') _toastTimer = setTimeout(() => t.style.display = 'none', 2400);
}

/* ── Parse "1,234" → 1234 ── */
function parsePill(el) {
    const n = parseInt((el ? el.textContent : '').replace(/[^0-9]/g, ''));
    return isNaN(n) ? 0 : n;
}

/* ── Recolour actual QTY pills and status dot for a row ── */
function recolourRow(tr, tQty) {
    const thr = tQty > 0 ? Math.ceil(tQty * 0.5) : 0;
    const pills = [...tr.querySelectorAll('.actual-qty')];
    // pills[0]=rit1-qty, pills[1]=rit1-kan, pills[2]=rit2-qty, pills[3]=rit2-kan
    function colour(pill) {
        if (!pill) return;
        const val = parsePill(pill);
        pill.classList.remove('blue','red','grey');
        if (tQty <= 0)        pill.classList.add('grey');
        else if (val >= thr)  pill.classList.add('blue');
        else                  pill.classList.add('red');
    }
    colour(pills[0]); colour(pills[2]);
    // always grey for kanban placeholders
    if (pills[1]) { pills[1].classList.remove('blue','red'); pills[1].classList.add('grey'); }
    if (pills[3]) { pills[3].classList.remove('blue','red'); pills[3].classList.add('grey'); }

    const dot  = tr.querySelector('.status-dot');
    if (dot) {
        const r1  = parsePill(pills[0]);
        const r2  = parsePill(pills[2]);
        const tot = r1 + r2;
        if (tQty <= 0)      { dot.className='status-dot na'; dot.textContent='-'; }
        else if (tot>=tQty) { dot.className='status-dot ok'; dot.textContent='OK'; }
        else if (tot>0)     { dot.className='status-dot ng'; dot.textContent='NG'; }
        else                { dot.className='status-dot na'; dot.textContent='-'; }
    }
}

/* ── Save one row ── */
async function saveRow(tr) {
    const id       = parseInt(tr.dataset.id  || '0');
    const isFixed  = parseInt(tr.dataset.isFixed || '0');
    const cname    = tr.dataset.cname || '';
    const pname    = tr.dataset.pname || '';
    const cid      = parseInt(tr.dataset.cid || '0');
    const pid      = parseInt(tr.dataset.pid || '0');
    const tQty     = parseInt(tr.querySelector('.target-qty').value   || '0');
    const tKanRaw  = tr.querySelector('.target-kanban').value;
    const tKan     = tKanRaw === '' ? '' : parseInt(tKanRaw);
    const ket      = tr.querySelector('.ket-input').value;

    showToast('Menyimpan…', 'spin');

    const payload = {
        id, board_date: tr.dataset.date || BOARD_DATE,
        is_fixed: isFixed,
        customer_name: cname, part_name: pname,
        customer_id: cid, product_id: pid,
        target_qty: tQty, target_kanban: tKan,
        keterangan: ket
    };

    try {
        const res  = await fetch(SAVE_TARGET_URL, {
            method : 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body   : JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.ok) {
            if (id === 0) tr.dataset.id = data.id;
            showToast('Tersimpan ✓', 'ok');
            recolourRow(tr, tQty);
        } else {
            showToast('Gagal: ' + (data.msg || 'Error'), 'err');
        }
    } catch(e) {
        showToast('Network error', 'err');
    }
}

/* ── Delete one row (custom only) ── */
async function deleteRow(btn) {
    const id  = parseInt(btn.dataset.id || '0');
    if (!confirm('Hapus baris ini?')) return;
    try {
        if (id > 0) {
            const res  = await fetch(DELETE_ROW_URL + '/' + id, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} });
            const data = await res.json();
            if (!data.ok) { showToast('Gagal: ' + (data.msg||''), 'err'); return; }
        }
        const tr = btn.closest('tr');
        tr.remove();
        showToast('Dihapus ✓', 'ok');
    } catch(e) { showToast('Network error','err'); }
}

/* ── Wire up existing rows ── */
function wireRow(tr) {
    const saveBtn = tr.querySelector('.btn-save-row');
    const delBtn  = tr.querySelector('.btn-del-row');
    if (saveBtn) saveBtn.addEventListener('click', () => saveRow(tr));
    if (delBtn)  delBtn.addEventListener('click',  () => deleteRow(delBtn));
    tr.querySelectorAll('input').forEach(inp => {
        inp.addEventListener('keydown', e => { if (e.key === 'Enter') saveRow(tr); });
    });
}
document.querySelectorAll('#dcbBody tr[data-id]').forEach(wireRow);

/* ── Add custom row ── */
document.getElementById('btnAddDcb').addEventListener('click', async function() {
    const custSel = document.getElementById('newCustSel');
    const prodSel = document.getElementById('newProdSel');
    const cid     = parseInt(custSel.value || '0');
    const pid     = parseInt(prodSel.value || '0');
    const tQty    = parseInt(document.getElementById('newTargetQty').value || '0');
    const tKanRaw = document.getElementById('newTargetKan').value;
    const tKan    = tKanRaw === '' ? '' : parseInt(tKanRaw);
    const ket     = document.getElementById('newKet').value;
    const cname   = custSel.selectedOptions[0]?.dataset.name || '';
    const pname   = prodSel.selectedOptions[0]?.dataset.name || '';

    if (pid <= 0) { alert('Pilih Part terlebih dahulu.'); return; }

    showToast('Menyimpan…', 'spin');
    try {
        const res  = await fetch(SAVE_TARGET_URL, {
            method : 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body   : JSON.stringify({
                id: 0, board_date: BOARD_DATE,
                is_fixed: 0,
                customer_name: cname, part_name: pname,
                customer_id: cid, product_id: pid,
                target_qty: tQty, target_kanban: tKan, keterangan: ket
            })
        });
        const data = await res.json();
        if (data.ok) {
            showToast('Ditambahkan ✓', 'ok');
            setTimeout(() => location.reload(), 700);
        } else {
            showToast('Gagal: ' + (data.msg||'Error'), 'err');
        }
    } catch(e) { showToast('Network error','err'); }
});
/* ── Export PDF ── */
function exportPDF() { window.print(); }

/* ── Export Excel ── */
function exportExcel() {
    if (typeof XLSX === 'undefined') { alert('Library Excel belum siap.'); return; }
    const table = document.getElementById('dcbTable');
    const rows  = [];

    table.querySelectorAll('thead tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('th').forEach(th => { row.push(th.innerText.replace(/\n/g,' ').trim()); });
        rows.push(row);
    });
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => {
            if (td.querySelector('.btn-save-row, .btn-del-row, .btn-fixed-lock')) { return; }
            const inp = td.querySelector('input');
            const spn = td.querySelector('span.actual-qty, span.status-dot');
            if (inp) row.push(inp.value);
            else if (spn) row.push(spn.textContent.trim());
            else row.push(td.innerText.trim());
        });
        if (row.length > 0) rows.push(row);
    });

    const ws = XLSX.utils.aoa_to_sheet(rows);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'DeliveryControlBoard');
    XLSX.writeFile(wb, `DCB_${BOARD_DATE}.xlsx`);
    showToast('Export Excel selesai ✓', 'ok');
}
</script>

<?= $this->endSection() ?>
