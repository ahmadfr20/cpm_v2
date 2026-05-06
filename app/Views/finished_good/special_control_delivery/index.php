<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

/* ══ PAGE ══ */
.scd-page { font-family: 'Inter', sans-serif; }

/* ══ HEADER BANNER ══ */
.scd-header-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1a3a2e 55%, #0f172a 100%);
    border-radius: 16px; padding: 1.4rem 2rem; margin-bottom: 1.2rem;
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;
    box-shadow: 0 8px 32px rgba(0,0,0,.35); border: 1px solid rgba(255,255,255,.07);
}
.scd-logo-box {
    width: 52px; height: 52px;
    background: linear-gradient(135deg, #16a34a, #064e3b);
    border-radius: 12px; display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; font-weight: 900; color: white;
    box-shadow: 0 4px 12px rgba(22,163,74,.4); flex-shrink: 0;
}
.scd-company  { color: #94a3b8; font-size: .72rem; font-weight: 600; letter-spacing: .09em; text-transform: uppercase; }
.scd-date-txt { color: #e2e8f0; font-size: .88rem; font-weight: 600; margin-top: 2px; }
.scd-title-main {
    font-size: 1.55rem; font-weight: 900; color: white;
    letter-spacing: .05em; text-transform: uppercase;
    text-shadow: 0 2px 14px rgba(34,197,94,.4);
}
.scd-ost-area { display: flex; align-items: center; gap: .6rem; }
.scd-ost-label { color: #94a3b8; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
.scd-ost-input {
    background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2);
    color: white; border-radius: 8px; padding: .35rem .7rem; font-weight: 700; font-size: .875rem;
    min-width: 140px;
}
.scd-ost-input::placeholder { color: rgba(255,255,255,.35); }
.scd-ost-input:focus { outline: none; border-color: #22c55e; }
.scd-ost-display { color: #22c55e; font-weight: 800; font-size: .95rem; }
.scd-date-filter { display: flex; align-items: center; gap: .5rem; }
.scd-date-filter input[type="date"] {
    background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2);
    color: white; border-radius: 8px; padding: .4rem .8rem; font-weight: 600; font-size: .875rem;
}
.scd-date-filter input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); }
.scd-date-filter button {
    background: #22c55e; border: none; color: white;
    border-radius: 8px; padding: .4rem 1rem; font-weight: 700; font-size: .875rem; cursor: pointer;
}
.scd-date-filter button:hover { background: #16a34a; }

/* ══ TOOLBAR ══ */
.scd-toolbar {
    display: flex; gap: .6rem; flex-wrap: wrap; margin-bottom: 1rem; align-items: center;
}
.btn-export-pdf {
    background: #ef4444; color: white; border: none; border-radius: 8px;
    padding: .4rem .9rem; font-weight: 700; font-size: .8rem; cursor: pointer;
    display: flex; align-items: center; gap: .4rem; transition: opacity .2s;
}
.btn-export-pdf:hover { opacity: .85; }
.btn-export-excel {
    background: #16a34a; color: white; border: none; border-radius: 8px;
    padding: .4rem .9rem; font-weight: 700; font-size: .8rem; cursor: pointer;
    display: flex; align-items: center; gap: .4rem; transition: opacity .2s;
}
.btn-export-excel:hover { opacity: .85; }
.btn-add-scd {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; border: none;
    border-radius: 8px; padding: .4rem 1rem; font-weight: 700; font-size: .8rem; cursor: pointer;
    display: flex; align-items: center; gap: .4rem; box-shadow: 0 2px 8px rgba(59,130,246,.3);
    transition: opacity .2s, transform .1s;
}
.btn-add-scd:hover { opacity: .9; transform: translateY(-1px); }

/* ══ BOARD WRAP ══ */
.scd-board-wrap { background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.1); border: 1px solid #e2e8f0; }

/* ══ TABLE ══ */
.scd-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
.scd-table th {
    background: #1e293b; color: white; font-weight: 700; text-transform: uppercase;
    font-size: .68rem; letter-spacing: .06em; padding: .55rem .6rem;
    border: 1px solid #334155; text-align: center; white-space: nowrap;
}
.scd-table td { padding: .4rem .55rem; border: 1px solid #e8ecf0; vertical-align: middle; color: #1e293b; font-weight: 500; }

/* Column group header colours */
.th-plan   { background: #1d4ed8 !important; }
.th-actual { background: #374151 !important; letter-spacing: .1em; }
.th-total  { background: #111827 !important; }
.th-aksi   { background: #1f2937 !important; }
.th-rit1   { background: #065f46 !important; }
.th-rit2   { background: #4338ca !important; }
.th-rit3   { background: #c2410c !important; }
.th-rit4   { background: #9f1239 !important; }
.th-rit5   { background: #0f766e !important; }

/* Cell backgrounds */
.bg-rit1 { background: #f0fdf4; }
.bg-rit2 { background: #eef2ff; }
.bg-rit3 { background: #fff7ed; }
.bg-rit4 { background: #fff1f2; }
.bg-rit5 { background: #f0fdfa; }

/* Time input inside TH */
.scd-time-input {
    background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25);
    color: white; border-radius: 5px; padding: .18rem .3rem; font-weight: 600; font-size: .72rem;
    width: 72px; text-align: center; margin-top: 3px;
    color-scheme: dark;
}
.scd-time-input:focus { outline: none; border-color: #22c55e; background: rgba(255,255,255,.22); }
.scd-time-txt { color: #a5f3b0; font-size: .72rem; font-weight: 700; display: block; margin-top: 3px; }

/* Inputs in cells */
.scd-num-input {
    width: 62px; text-align: center; border: 1.5px solid #cbd5e1;
    border-radius: 6px; padding: .22rem .3rem; font-weight: 700; font-size: .8rem;
    color: #0f172a; background: white; transition: border-color .2s, box-shadow .2s;
}
.scd-num-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.18); }
.scd-sel {
    border: 1.5px solid #cbd5e1; border-radius: 6px; padding: .22rem .4rem;
    font-weight: 600; font-size: .76rem; color: #0f172a; background: white;
    transition: border-color .2s;
}
.scd-sel:focus { outline: none; border-color: #3b82f6; }
.scd-ket-input {
    min-width: 90px; width: 100%; border: 1.5px solid #e2e8f0;
    border-radius: 6px; padding: .22rem .4rem; font-size: .75rem; color: #334155; background: white;
}
.scd-ket-input:focus { outline: none; border-color: #3b82f6; }

/* Row total */
.row-total { font-weight: 900; font-size: .88rem; }

/* Footer totals row */
.scd-totals-row td {
    background: #1e293b !important; color: white !important; font-weight: 800;
    border-color: #334155 !important; text-align: center; padding: .5rem .6rem;
}

/* No. column */
.td-no { width: 34px; text-align: center; color: #94a3b8; font-size: .72rem; font-weight: 700; }

/* Action buttons */
.btn-save-row {
    background: #22c55e; color: white; border: none; border-radius: 6px;
    padding: .22rem .45rem; font-size: .72rem; cursor: pointer; transition: background .2s;
}
.btn-save-row:hover { background: #16a34a; }
.btn-del-row {
    background: transparent; color: #ef4444; border: 1.5px solid #fca5a5;
    border-radius: 6px; padding: .22rem .4rem; font-size: .72rem; cursor: pointer; transition: all .2s;
}
.btn-del-row:hover { background: #fee2e2; }

/* Add section */
.scd-add-section {
    border-top: 2px dashed #cbd5e1; background: #f8fafc; padding: .85rem 1.2rem;
    display: flex; align-items: center; gap: .8rem; flex-wrap: wrap;
}

/* Toast */
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

/* ══ PRINT STYLES ══ */
@media print {
    .scd-print-header    { display: block !important; }
    .scd-toolbar         { display: none !important; }
    .scd-date-filter     { display: none !important; }
    .scd-ost-input       { border: none !important; background: transparent !important; color: #000 !important; }
    .scd-time-input      { border: none !important; background: transparent !important; color: #000 !important; }
    .scd-num-input       { border: none !important; background: transparent !important; }
    .scd-sel             { border: none !important; background: transparent !important; }
    .scd-ket-input       { border: none !important; background: transparent !important; }
    .scd-add-section     { display: none !important; }
    .btn-del-row, .btn-save-row { display: none !important; }
    .th-aksi, td:last-child.aksi-cell { display: none !important; }
    .scd-header-banner   { background: none !important; border: 1px solid #000 !important; color: #000 !important; border-radius: 4px !important; box-shadow: none !important; }
    .scd-title-main      { color: #000 !important; text-shadow: none !important; }
    .scd-company, .scd-date-txt, .scd-ost-label { color: #333 !important; }
    .scd-logo-box        { background: #ccc !important; color: #000 !important; box-shadow: none !important; }
    table th             { color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .scd-board-wrap      { box-shadow: none !important; border: 1px solid #ccc !important; }
}
.scd-print-header { display: none; }
</style>

<?php
/* ─── Pre-compute PHP-side helpers ───────────────────────────── */
$grandPlan = 0;
$grandRit  = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

/* Build JSON option arrays for JS new-row builder */
$custOptsJson = json_encode(array_values(array_map(fn($c) => [
    'id'   => (int)$c['id'],
    'name' => $c['customer_name'],
], $customers)));

$prodOptsJson = json_encode(array_values(array_map(fn($p) => [
    'id'   => (int)$p['id'],
    'name' => ($p['part_no'] ? $p['part_no'] . ' — ' : '') . $p['part_name'],
], $products)));

$ritColors = [
    1 => '#065f46', 2 => '#4338ca', 3 => '#c2410c',
    4 => '#9f1239', 5 => '#0f766e',
];
$ritBg = [
    1 => 'bg-rit1', 2 => 'bg-rit2', 3 => 'bg-rit3',
    4 => 'bg-rit4', 5 => 'bg-rit5',
];
?>

<div class="scd-page">

<!-- ══ PRINT-ONLY HEADER ══ -->
<div class="scd-print-header" style="margin-bottom:12px; text-align:center;">
    <div style="font-size:1.1rem;font-weight:900;text-transform:uppercase;letter-spacing:.1em;">Special Control Delivery</div>
    <div style="font-size:.85rem; margin-top:4px;">
        Hari/Tanggal: <strong><?= strtoupper(date('l, d F Y', strtotime($date))) ?></strong>
        &nbsp;|&nbsp; OST: <strong><?= esc($settings['ost_label'] ?: '—') ?></strong>
    </div>
    <div style="font-size:.75rem; color:#555; margin-top:2px;">PT. Cikarang Perkasa Manufacturing</div>
</div>

<!-- ══ HEADER BANNER ══ -->
<div class="scd-header-banner">
    <div class="d-flex align-items-center gap-3">
        <div class="scd-logo-box">SC</div>
        <div>
            <div class="scd-company">PT. Cikarang Perkasa Mfg.</div>
            <div class="scd-date-txt"><?= strtoupper(date('l, d F Y', strtotime($date))) ?></div>
        </div>
    </div>

    <div class="scd-title-main">
        <i class="bi bi-truck-front me-2"></i>Special Control Delivery
    </div>

    <div class="d-flex flex-column gap-2 align-items-end">
        <!-- OST label -->
        <div class="scd-ost-area">
            <span class="scd-ost-label">OST:</span>
            <?php if ($logged_in): ?>
            <input type="text" id="ostLabel" class="scd-ost-input"
                   value="<?= esc($settings['ost_label']) ?>"
                   placeholder="mis. PT. DMIA…" maxlength="100">
            <?php else: ?>
            <span class="scd-ost-display"><?= esc($settings['ost_label'] ?: '—') ?></span>
            <?php endif; ?>
        </div>
        <!-- Date filter -->
        <form method="get" class="scd-date-filter">
            <i class="bi bi-calendar3 text-white"></i>
            <input type="date" name="date" value="<?= esc($date) ?>">
            <button type="submit"><i class="bi bi-search me-1"></i>Filter</button>
        </form>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success fw-bold mb-3 border-0 border-start border-4 border-success shadow-sm">
    <i class="bi bi-check-circle-fill me-2"></i><?= session()->getFlashdata('success') ?>
</div>
<?php endif; ?>

<?php if (!$logged_in): ?>
<div class="alert alert-info fw-bold mb-3 border-0 border-start border-4 border-info shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-2">
    <span><i class="bi bi-eye me-2"></i>Mode <strong>View Only</strong> — Login untuk menginput atau mengubah data.</span>
    <a href="/login" class="btn btn-sm btn-primary fw-bold"><i class="bi bi-box-arrow-in-right me-1"></i>Login</a>
</div>
<?php endif; ?>

<!-- ══ TOOLBAR ══ -->
<div class="scd-toolbar">
    <button class="btn-export-pdf" onclick="exportPDF()">
        <i class="bi bi-printer-fill"></i> Export PDF
    </button>
    <button class="btn-export-excel" onclick="exportExcel()">
        <i class="bi bi-file-earmark-excel-fill"></i> Export Excel
    </button>
    <?php if ($logged_in): ?>
    <button class="btn-add-scd" id="btnAddRow">
        <i class="bi bi-plus-lg"></i> Tambah Baris
    </button>
    <?php endif; ?>

    <!-- PLAY BUTTON -->
    <button id="scdPlayBtn" onclick="togglePlayMode()" style="background:#7c3aed;color:white;border:none;border-radius:8px;padding:.4rem 1.1rem;font-weight:700;font-size:.85rem;cursor:pointer;display:flex;align-items:center;gap:.5rem;margin-left:auto;box-shadow:0 2px 8px rgba(124,58,237,.4);">
        <i class="bi bi-play-fill" id="scdPlayIcon"></i> <span id="scdPlayLabel">Play Tampilan</span>
    </button>
    <div id="scdPlayStatus" style="display:none; font-size:.78rem; color:#7c3aed; font-weight:700;">
        <i class="bi bi-arrow-repeat" style="display:inline-block;animation:spin 1.5s linear infinite;"></i> Auto-refresh dalam <span id="scdRefreshTimer">60</span>s
    </div>
</div>

<style>
@keyframes scd-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>

<!-- ══ BOARD TABLE ══ -->
<div class="scd-board-wrap">
<div class="table-responsive">
<table class="scd-table" id="scdTable">
    <thead>
        <tr>
            <th rowspan="2" style="width:36px">NO</th>
            <th rowspan="2" class="text-start" style="min-width:100px">CUST</th>
            <th rowspan="2" class="text-start" style="min-width:130px">PART</th>
            <th rowspan="2" class="th-plan" style="width:80px">PLAN</th>
            <th colspan="5" class="th-actual" style="letter-spacing:.08em;">— ACTUAL —</th>
            <th rowspan="2" class="th-total" style="width:76px">TOTAL</th>
            <?php if ($logged_in): ?>
            <th rowspan="2" class="th-aksi" style="width:70px">AKSI</th>
            <?php endif; ?>
        </tr>
        <tr>
            <?php for ($i = 1; $i <= 5; $i++):
                $ritTime = $settings["rit{$i}_time"] ?? '';
            ?>
            <th class="th-rit<?= $i ?>" style="width:90px;">
                RIT-<?= $i ?>
                <?php if ($logged_in): ?>
                <br><input type="time" id="rit<?= $i ?>Time" class="scd-time-input"
                       value="<?= esc($ritTime) ?>" title="Jam Delivery RIT-<?= $i ?>">
                <?php else: ?>
                <span class="scd-time-txt"><?= $ritTime ?: '--:--' ?></span>
                <?php endif; ?>
            </th>
            <?php endfor; ?>
        </tr>
    </thead>

    <tbody id="scdBody">
    <?php
    $rowNum = 1;
    foreach ($rows as $r):
        $plan  = (int)$r['plan_qty'];
        $rits  = [];
        $total = 0;
        for ($i = 1; $i <= 5; $i++) {
            $rits[$i] = (int)$r["rit{$i}_qty"];
            $total   += $rits[$i];
            $grandRit[$i] += $rits[$i];
        }
        $grandPlan += $plan;

        $statusColor = ($plan > 0 && $total >= $plan) ? '#16a34a'
                     : ($total > 0 ? '#dc2626' : '#94a3b8');

        $custSelHtml = '<option value="0">— Cust —</option>';
        foreach ($customers as $c) {
            $sel = (int)$r['customer_id'] === (int)$c['id'] ? 'selected' : '';
            $custSelHtml .= '<option value="' . (int)$c['id'] . '" ' . $sel . '>' . esc($c['customer_name']) . '</option>';
        }
        $prodSelHtml = '<option value="0">— Part —</option>';
        foreach ($products as $p) {
            $plabel = $p['part_no'] ? $p['part_no'] . ' — ' . $p['part_name'] : $p['part_name'];
            $sel    = (int)$r['product_id'] === (int)$p['id'] ? 'selected' : '';
            $prodSelHtml .= '<option value="' . (int)$p['id'] . '" ' . $sel . '>' . esc($plabel) . '</option>';
        }
    ?>
    <tr data-id="<?= (int)$r['id'] ?>" data-order="<?= (int)$r['row_order'] ?>">
        <td class="td-no"><?= $rowNum++ ?></td>

        <!-- CUST -->
        <td>
            <?php if ($logged_in): ?>
            <select class="scd-sel cust-sel" style="min-width:90px"><?= $custSelHtml ?></select>
            <?php else: ?>
            <span class="fw-semibold"><?= esc($r['customer_name'] ?: '—') ?></span>
            <?php endif; ?>
        </td>

        <!-- PART -->
        <td>
            <?php if ($logged_in): ?>
            <select class="scd-sel prod-sel" style="min-width:120px"><?= $prodSelHtml ?></select>
            <?php else: ?>
            <span class="fw-bold"><?= esc($r['part_name'] ?: '—') ?></span>
            <?php endif; ?>
        </td>

        <!-- PLAN -->
        <td class="text-center" style="background:#eff6ff;">
            <?php if ($logged_in): ?>
            <input type="number" class="scd-num-input plan-qty" value="<?= $plan ?>" min="0" placeholder="0">
            <?php else: ?>
            <span class="fw-bold" style="color:#1d4ed8;"><?= $plan > 0 ? number_format($plan) : '—' ?></span>
            <?php endif; ?>
        </td>

        <!-- RIT 1–5 -->
        <?php for ($i = 1; $i <= 5; $i++): ?>
        <td class="text-center <?= $ritBg[$i] ?>">
            <?php if ($logged_in): ?>
            <input type="number" class="scd-num-input rit-qty" data-rit="<?= $i ?>"
                   value="<?= $rits[$i] ?>" min="0" placeholder="0">
            <?php else: ?>
            <span class="<?= $rits[$i] > 0 ? 'fw-bold' : 'text-muted' ?>">
                <?= $rits[$i] > 0 ? number_format($rits[$i]) : '—' ?>
            </span>
            <?php endif; ?>
        </td>
        <?php endfor; ?>

        <!-- TOTAL -->
        <td class="text-center">
            <span class="row-total" style="color:<?= $statusColor ?>; font-weight:900; font-size:.9rem;">
                <?= $total > 0 ? number_format($total) : '—' ?>
            </span>
        </td>

        <!-- AKSI -->
        <?php if ($logged_in): ?>
        <td class="text-center aksi-cell">
            <div class="d-flex gap-1 justify-content-center">
                <button class="btn-save-row" title="Simpan"><i class="bi bi-check-lg"></i></button>
                <button class="btn-del-row" title="Hapus"><i class="bi bi-trash3"></i></button>
            </div>
        </td>
        <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>

    <!-- ══ TOTALS FOOTER ══ -->
    <tfoot>
        <tr class="scd-totals-row">
            <td colspan="3" class="text-end" style="font-size:.72rem; letter-spacing:.06em;">GRAND TOTAL</td>
            <td style="background:#1d4ed8 !important;"><?= $grandPlan > 0 ? number_format($grandPlan) : '—' ?></td>
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <td style="background:<?= $ritColors[$i] ?> !important; opacity:.9;">
                <?= $grandRit[$i] > 0 ? number_format($grandRit[$i]) : '—' ?>
            </td>
            <?php endfor; ?>
            <td style="background:#111827 !important;">
                <?= array_sum($grandRit) > 0 ? number_format(array_sum($grandRit)) : '—' ?>
            </td>
            <?php if ($logged_in): ?><td style="background:#1f2937 !important;"></td><?php endif; ?>
        </tr>
    </tfoot>
</table>
</div>

<!-- ══ ADD ROW / LOGIN NOTICE ══ -->
<?php if ($logged_in): ?>
<div class="scd-add-section">
    <button class="btn-add-scd" id="btnAddRowBottom">
        <i class="bi bi-plus-lg"></i> Tambah Baris Baru
    </button>
    <span class="text-muted small">Pilih Customer dan Part lalu isi data qty, kemudian klik <i class="bi bi-check-lg text-success"></i> Simpan</span>
</div>
<?php else: ?>
<div class="scd-add-section" style="background:#f0f9ff; border-top:2px dashed #bae6fd;">
    <i class="bi bi-lock-fill text-info fs-5"></i>
    <span class="text-secondary fw-semibold" style="font-size:.85rem;">
        <a href="/login" class="text-primary fw-bold">Login</a> untuk menambah atau mengubah data delivery.
    </span>
</div>
<?php endif; ?>

</div><!-- /scd-board-wrap -->
</div><!-- /scd-page -->

<!-- ══ TOAST ══ -->
<div class="saving-toast" id="savingToast">
    <i class="bi bi-arrow-repeat me-2 spin" id="toastIcon"></i>
    <span id="toastMsg">Menyimpan…</span>
</div>

<script>
/* ── Constants ── */
const SCD_DATE          = '<?= esc($date) ?>';
const SAVE_SETTINGS_URL = '<?= site_url('finished-good/special-control-delivery/save-settings') ?>';
const SAVE_ROW_URL      = '<?= site_url('finished-good/special-control-delivery/save-row') ?>';
const DELETE_ROW_URL    = '<?= site_url('finished-good/special-control-delivery/delete') ?>';
const LOGGED_IN         = <?= $logged_in ? 'true' : 'false' ?>;

const CUST_OPTS = <?= $custOptsJson ?>;
const PROD_OPTS = <?= $prodOptsJson ?>;

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

/* ── Re-calc row total ── */
function recalcRow(tr) {
    const rits  = [...tr.querySelectorAll('.rit-qty')];
    const total = rits.reduce((s, i) => s + parseInt(i.value || '0', 10), 0);
    const plan  = parseInt(tr.querySelector('.plan-qty')?.value || '0', 10);
    const el    = tr.querySelector('.row-total');
    if (!el) return;
    el.textContent = total > 0 ? total.toLocaleString() : '—';
    el.style.color = (plan > 0 && total >= plan) ? '#16a34a' : (total > 0 ? '#dc2626' : '#94a3b8');
}

/* ── Save settings (auto-debounced) ── */
let _settTimer;
function scheduleSaveSettings() {
    clearTimeout(_settTimer);
    _settTimer = setTimeout(doSaveSettings, 700);
}
async function doSaveSettings() {
    const d = {
        board_date: SCD_DATE,
        ost_label:  document.getElementById('ostLabel')?.value   || '',
        rit1_time:  document.getElementById('rit1Time')?.value   || '',
        rit2_time:  document.getElementById('rit2Time')?.value   || '',
        rit3_time:  document.getElementById('rit3Time')?.value   || '',
        rit4_time:  document.getElementById('rit4Time')?.value   || '',
        rit5_time:  document.getElementById('rit5Time')?.value   || '',
    };
    try {
        await fetch(SAVE_SETTINGS_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(d),
        });
    } catch(e) {}
}

/* ── Save a row ── */
async function saveRow(tr) {
    const id    = parseInt(tr.dataset.id  || '0');
    const cSel  = tr.querySelector('.cust-sel');
    const pSel  = tr.querySelector('.prod-sel');
    const cid   = parseInt(cSel?.value   || '0');
    const cname = cSel?.selectedOptions[0]?.text?.trim() || '';
    const pid   = parseInt(pSel?.value   || '0');
    const pname = pSel?.selectedOptions[0]?.text?.trim() || '';

    if (pid <= 0 && cid <= 0) {
        showToast('Pilih Customer/Part terlebih dahulu', 'err'); return;
    }

    const payload = {
        id, board_date: SCD_DATE,
        customer_id: cid, customer_name: cname,
        product_id:  pid, part_name: pname,
        plan_qty: parseInt(tr.querySelector('.plan-qty')?.value || '0'),
        rit1_qty: parseInt(tr.querySelector('[data-rit="1"]')?.value || '0'),
        rit2_qty: parseInt(tr.querySelector('[data-rit="2"]')?.value || '0'),
        rit3_qty: parseInt(tr.querySelector('[data-rit="3"]')?.value || '0'),
        rit4_qty: parseInt(tr.querySelector('[data-rit="4"]')?.value || '0'),
        rit5_qty: parseInt(tr.querySelector('[data-rit="5"]')?.value || '0'),
        keterangan: '',
        row_order: parseInt(tr.dataset.order || '0'),
    };

    showToast('Menyimpan…', 'spin');
    try {
        const res  = await fetch(SAVE_ROW_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (data.ok) {
            if (id === 0) tr.dataset.id = data.id;
            showToast('Tersimpan ✓', 'ok');
        } else showToast('Gagal simpan', 'err');
    } catch(e) { showToast('Network error', 'err'); }
}

/* ── Delete a row ── */
async function deleteRow(btn) {
    if (!confirm('Hapus baris ini?')) return;
    const tr = btn.closest('tr');
    const id = parseInt(tr.dataset.id || '0');
    try {
        if (id > 0) {
            const res  = await fetch(DELETE_ROW_URL + '/' + id, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} });
            const data = await res.json();
            if (!data.ok) { showToast('Gagal hapus', 'err'); return; }
        }
        tr.remove();
        renumberRows();
        showToast('Dihapus ✓', 'ok');
    } catch(e) { showToast('Network error', 'err'); }
}

/* ── Build select HTML ── */
function buildOpts(arr, defaultLabel) {
    let h = `<option value="0">— ${defaultLabel} —</option>`;
    arr.forEach(o => { h += `<option value="${o.id}">${o.name}</option>`; });
    return h;
}

/* ── Add new row ── */
function addRow() {
    const tbody  = document.getElementById('scdBody');
    const rowNum = tbody.querySelectorAll('tr').length + 1;
    const tr     = document.createElement('tr');
    tr.dataset.id    = '0';
    tr.dataset.order = rowNum;

    const bgRit = ['bg-rit1','bg-rit2','bg-rit3','bg-rit4','bg-rit5'];
    let ritCells = '';
    for (let i = 1; i <= 5; i++) {
        ritCells += `<td class="text-center ${bgRit[i-1]}">
            <input type="number" class="scd-num-input rit-qty" data-rit="${i}" value="0" min="0" placeholder="0">
        </td>`;
    }

    tr.innerHTML = `
        <td class="td-no">${rowNum}</td>
        <td><select class="scd-sel cust-sel" style="min-width:90px">${buildOpts(CUST_OPTS,'Cust')}</select></td>
        <td><select class="scd-sel prod-sel" style="min-width:120px">${buildOpts(PROD_OPTS,'Part')}</select></td>
        <td class="text-center" style="background:#eff6ff;">
            <input type="number" class="scd-num-input plan-qty" value="0" min="0" placeholder="0">
        </td>
        ${ritCells}
        <td class="text-center"><span class="row-total" style="color:#94a3b8;font-weight:900;font-size:.9rem;">—</span></td>
        <td class="text-center aksi-cell">
            <div class="d-flex gap-1 justify-content-center">
                <button class="btn-save-row" title="Simpan"><i class="bi bi-check-lg"></i></button>
                <button class="btn-del-row" title="Hapus"><i class="bi bi-trash3"></i></button>
            </div>
        </td>`;

    tbody.appendChild(tr);
    wireRow(tr);
    renumberRows();
    tr.querySelector('.cust-sel')?.focus();
}

/* ── Renumber NO column ── */
function renumberRows() {
    document.querySelectorAll('#scdBody tr').forEach((tr, i) => {
        const no = tr.querySelector('.td-no');
        if (no) no.textContent = i + 1;
    });
}

/* ── Wire row events ── */
function wireRow(tr) {
    tr.querySelector('.btn-save-row')?.addEventListener('click', () => saveRow(tr));
    tr.querySelector('.btn-del-row')?.addEventListener('click', e => deleteRow(e.currentTarget));
    tr.querySelectorAll('.rit-qty, .plan-qty').forEach(inp => {
        inp.addEventListener('input', () => recalcRow(tr));
        inp.addEventListener('keydown', e => { if (e.key === 'Enter') saveRow(tr); });
    });
}

/* ── Export PDF ── */
function exportPDF() { window.print(); }

/* ── Export Excel ── */
function exportExcel() {
    if (typeof XLSX === 'undefined') { alert('Library Excel belum siap.'); return; }

    const wb = XLSX.utils.book_new();

    /* Build data array manually to handle inputs */
    const table  = document.getElementById('scdTable');
    const rows   = [];

    /* Header rows */
    table.querySelectorAll('thead tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('th').forEach(th => {
            // Extract text, ignore inputs (use their values)
            const inp = th.querySelector('input');
            if (inp) row.push(inp.value || '');
            else row.push(th.innerText.replace(/\n/g,' ').trim());
        });
        rows.push(row);
    });

    /* Body rows */
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => {
            if (td.classList.contains('aksi-cell')) return;
            const sel = td.querySelector('select');
            const inp = td.querySelector('input');
            const spn = td.querySelector('.row-total');
            if (sel) row.push(sel.selectedOptions[0]?.text?.trim() || '');
            else if (inp) row.push(inp.value);
            else if (spn) row.push(spn.textContent.trim());
            else row.push(td.innerText.trim());
        });
        rows.push(row);
    });

    /* Footer */
    table.querySelectorAll('tfoot tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => { row.push(td.innerText.trim()); });
        rows.push(row);
    });

    const ws = XLSX.utils.aoa_to_sheet(rows);
    XLSX.utils.book_append_sheet(wb, ws, 'Special Control Delivery');
    XLSX.writeFile(wb, `SCD_${SCD_DATE}.xlsx`);
    showToast('Export Excel selesai ✓', 'ok');
}

/* ── Init ── */
document.querySelectorAll('#scdBody tr[data-id]').forEach(wireRow);

if (LOGGED_IN) {
    ['btnAddRow','btnAddRowBottom'].forEach(id => {
        document.getElementById(id)?.addEventListener('click', addRow);
    });

    /* Auto-save settings on change */
    ['ostLabel','rit1Time','rit2Time','rit3Time','rit4Time','rit5Time'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', scheduleSaveSettings);
        document.getElementById(id)?.addEventListener('input',  scheduleSaveSettings);
    });
}
/* ══ PLAY MODE: Auto-Scroll + Auto-Refresh ══ */
let _scdPlay      = false;
let _scdScrollRaf = null;
let _scdRefreshInt = null;
let _scdScrollDir  = 1;       // 1 = bawah, -1 = atas
const SCD_REFRESH_SECS = 30;
const SCD_SCROLL_SPEED = 0.6; // px per frame (~36px/s pada 60fps)

function getScdScrollContainer() {
    return document.querySelector('.main-content') || document.documentElement;
}

function scdPlayScroll() {
    if (!_scdPlay) return;
    const el   = getScdScrollContainer();
    const maxY = el.scrollHeight - el.clientHeight;
    if (maxY <= 0) { _scdScrollRaf = requestAnimationFrame(scdPlayScroll); return; }

    el.scrollTop += _scdScrollDir * SCD_SCROLL_SPEED;

    const y = el.scrollTop;
    if (_scdScrollDir === 1  && y >= maxY - 1) _scdScrollDir = -1;  // sampai bawah → balik atas
    if (_scdScrollDir === -1 && y <= 1)         _scdScrollDir =  1;  // sampai atas  → balik bawah

    _scdScrollRaf = requestAnimationFrame(scdPlayScroll);
}

function togglePlayMode() {
    _scdPlay = !_scdPlay;
    const btn    = document.getElementById('scdPlayBtn');
    const icon   = document.getElementById('scdPlayIcon');
    const label  = document.getElementById('scdPlayLabel');
    const status = document.getElementById('scdPlayStatus');
    const timer  = document.getElementById('scdRefreshTimer');

    if (_scdPlay) {
        /* --- UI: aktifkan mode play ---- */
        icon.className = 'bi bi-stop-fill';
        label.textContent = 'Stop';
        btn.style.background = '#dc2626';
        status.style.display = 'flex';
        status.style.alignItems = 'center';
        status.style.gap = '.4rem';

        /* --- Reset ke atas ---- */
        getScdScrollContainer().scrollTop = 0;
        _scdScrollDir = 1;

        /* --- Mulai scroll dengan rAF ---- */
        _scdScrollRaf = requestAnimationFrame(scdPlayScroll);

        /* --- Countdown auto-refresh ---- */
        let secs = SCD_REFRESH_SECS;
        timer.textContent = secs;

        _scdRefreshInt = setInterval(() => {
            secs -= 1;
            if (secs <= 0) {
                clearInterval(_scdRefreshInt);
                cancelAnimationFrame(_scdScrollRaf);
                _scdRefreshInt = null;
                _scdScrollRaf  = null;
                timer.textContent = '0';
                const url = new URL(window.location.href);
                url.searchParams.set('play', '1');
                window.location.replace(url.toString());
                return;
            }
            timer.textContent = secs;
        }, 1000);

    } else {
        /* --- Hentikan play mode ---- */
        cancelAnimationFrame(_scdScrollRaf);
        clearInterval(_scdRefreshInt);
        _scdScrollRaf  = null;
        _scdRefreshInt = null;

        icon.className = 'bi bi-play-fill';
        label.textContent = 'Play Tampilan';
        btn.style.background = '#7c3aed';
        status.style.display = 'none';
    }
}

/* Auto-start jika URL mengandung ?play=1 */
document.addEventListener('DOMContentLoaded', () => {
    if (new URLSearchParams(window.location.search).get('play') === '1') {
        setTimeout(togglePlayMode, 300);
    }
});
</script>

<?= $this->endSection() ?>
