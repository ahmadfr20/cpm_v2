<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

.dcb-page { font-family: 'Inter', sans-serif; }
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
.dcb-board-wrap { background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.1); border: 1px solid #e2e8f0; }
.dcb-table { width: 100%; border-collapse: collapse; font-size: .81rem; }
.dcb-table th {
    background: #1e293b; color: white; font-weight: 700; text-transform: uppercase;
    font-size: .7rem; letter-spacing: .06em; padding: .65rem .7rem;
    border: 1px solid #334155; text-align: center; white-space: nowrap;
}
.dcb-table th.th-left { text-align: left; }
.dcb-table td { padding: .48rem .65rem; border: 1px solid #e8ecf0; vertical-align: middle; color: #1e293b; font-weight: 600; }
.cust-group-row td {
    background: linear-gradient(90deg, #1e293b 0%, #2d3f55 100%);
    color: #f8fafc; font-weight: 800; font-size: .78rem;
    text-transform: uppercase; letter-spacing: .07em; padding: .55rem 1rem;
    border-color: #334155 !important;
}
.cust-group-row.fixed-cust td { background: linear-gradient(90deg, #14532d 0%, #166534 100%); }
.cust-badge { background: rgba(255,255,255,.15); border-radius: 5px; padding: .15rem .5rem; font-size: .65rem; margin-left: .5rem; }
.row-fixed td:first-child { background: #f0fdf4; }
.td-no { text-align: center; color: #94a3b8; font-size: .72rem; font-weight: 600; width: 34px; }
.td-partname { font-weight: 700; color: #0f172a; white-space: nowrap; }

.actual-qty { display: inline-flex; align-items: center; justify-content: center; min-width: 48px; padding: .28rem .55rem; border-radius: 7px; font-weight: 800; font-size: .83rem; }
.actual-qty.blue { background: #dbeafe; color: #1d4ed8; border: 1.5px solid #93c5fd; }
.actual-qty.red  { background: #fee2e2; color: #dc2626; border: 1.5px solid #fca5a5; }
.actual-qty.grey { background: #f1f5f9; color: #94a3b8; border: 1.5px solid #e2e8f0; }

.status-dot { width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: .62rem; font-weight: 800; }
.status-dot.ok { background: #dbeafe; color: #1d4ed8; border: 2px solid #93c5fd; }
.status-dot.ng { background: #fee2e2; color: #dc2626; border: 2px solid #fca5a5; }
.status-dot.na { background: #f1f5f9; color: #94a3b8; border: 2px solid #e2e8f0; }

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

.th-t { background: #334155!important; color: #cbd5e1!important; }
.th-a { background: #1e293b!important; color: white!important; }
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

<div class="alert alert-info fw-bold mb-3 border-0 border-start border-4 border-info shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-2">
    <span><i class="bi bi-eye me-2"></i>Mode <strong>Read-Only</strong>. Board ini otomatis memadukan data Target dari <strong>Schedule Delivery</strong> dan hasil dari <strong>FG Delivery</strong>.</span>
</div>

<!-- ══ EXPORT TOOLBAR ══ -->
<div class="d-flex gap-2 mb-3 flex-wrap align-items-center">
    <button onclick="exportPDF()" style="background:#ef4444;color:white;border:none;border-radius:8px;padding:.4rem .9rem;font-weight:700;font-size:.8rem;cursor:pointer;display:flex;align-items:center;gap:.4rem;">
        <i class="bi bi-printer-fill"></i> Export PDF
    </button>
    <button onclick="exportExcel()" style="background:#16a34a;color:white;border:none;border-radius:8px;padding:.4rem .9rem;font-weight:700;font-size:.8rem;cursor:pointer;display:flex;align-items:center;gap:.4rem;">
        <i class="bi bi-file-earmark-excel-fill"></i> Export Excel
    </button>

    <!-- PLAY BUTTON -->
    <button id="playBtn" onclick="togglePlayMode()" style="background:#7c3aed;color:white;border:none;border-radius:8px;padding:.4rem 1.1rem;font-weight:700;font-size:.85rem;cursor:pointer;display:flex;align-items:center;gap:.5rem;margin-left:auto;box-shadow:0 2px 8px rgba(124,58,237,.4);">
        <i class="bi bi-play-fill" id="playIcon"></i> <span id="playLabel">Play Tampilan</span>
    </button>
    <div id="playStatus" style="display:none; font-size:.78rem; color:#7c3aed; font-weight:700;">
        <i class="bi bi-arrow-repeat spin-icon"></i> Auto-refresh dalam <span id="refreshTimer">60</span>s
    </div>
</div>

<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.spin-icon { display: inline-block; animation: spin 1.5s linear infinite; }
</style>


<!-- ══ BOARD TABLE ══ -->
<div class="dcb-board-wrap">
<div class="table-responsive">
<table class="dcb-table" id="dcbTable">
    <thead>
        <tr>
            <th class="th-left" rowspan="3" style="min-width:160px; vertical-align: middle;">CUSTOMER</th>
            <th rowspan="3" style="width:36px; vertical-align: middle;">No.</th>
            <th class="th-left" rowspan="3" style="min-width:150px; vertical-align: middle;">PART NAME</th>
            <th colspan="2" rowspan="2" style="background:#2d3748!important; border-left:2px solid #1a202c; vertical-align: middle;">TARGET DELIVERY</th>
            <th colspan="4" style="background:#4a5568!important; border-left:2px solid #1a202c;">ACTUAL DELIVERY</th>
            <th rowspan="3" style="width:60px; background:#2c7a7b!important; vertical-align: middle;">STATUS</th>
            <th rowspan="3" style="width:120px; background:#285e61!important; vertical-align: middle;">KETERANGAN</th>
        </tr>
        <tr>
            <th colspan="2" style="background:#718096!important; border-left:2px solid #2d3748;">RIT-1</th>
            <th colspan="2" style="background:#718096!important; border-left:2px solid #2d3748;">RIT-2</th>
        </tr>
        <tr>
            <th style="background:#4a5568!important; border-left:2px solid #2d3748; width:70px;">QTY</th>
            <th style="background:#4a5568!important; width:70px;">KANBAN</th>
            <th style="background:#a0aec0!important; color:#1a202c!important; border-left:2px solid #2d3748; width:70px;">QTY</th>
            <th style="background:#a0aec0!important; color:#1a202c!important; width:70px;">KANBAN</th>
            <th style="background:#a0aec0!important; color:#1a202c!important; border-left:2px solid #2d3748; width:70px;">QTY</th>
            <th style="background:#a0aec0!important; color:#1a202c!important; width:70px;">KANBAN</th>
        </tr>
    </thead>
    <tbody>

    <?php
    $grandTarget = 0;
    $grandActual = 0;
    $totalParts  = 0;

    $orderedCustomers = array_unique(array_merge($fixedCustomers, array_keys($displayGroups)));

    foreach ($orderedCustomers as $custName):
        if (!isset($displayGroups[$custName])) continue;
        $rows    = $displayGroups[$custName];
        $isFixed = in_array($custName, $fixedCustomers);
        $rowNo   = 1;
    ?>
        <tr class="cust-group-row <?= $isFixed ? 'fixed-cust' : '' ?>">
            <td colspan="10">
                <?= esc($custName) ?>
                <?php if ($isFixed): ?>
                    <span class="cust-badge"><i class="bi bi-pin-fill me-1"></i>Tetap</span>
                <?php endif; ?>
            </td>
        </tr>

        <?php foreach ($rows as $r):
            $pid       = (int)$r['product_id'];
            $sched     = $r['sched'] ?? [];
            
            $t1 = (int)($sched['rit_1'] ?? 0);
            $t2 = (int)($sched['rit_2'] ?? 0);
            $tQty = $t1 + $t2;
            
            $a1 = (int)($actualMap[$pid]['RIT-1'] ?? 0);
            $a2 = (int)($actualMap[$pid]['RIT-2'] ?? 0);
            $aQty = $a1 + $a2;

            $statusClass = $tQty <= 0 ? 'na' : ($aQty >= $tQty ? 'ok' : ($aQty > 0 ? 'ng' : 'na'));
            $statusLabel = $tQty <= 0 ? '—'  : ($aQty >= $tQty ? '<i class="bi bi-check-lg"></i>' : ($aQty > 0 ? '<i class="bi bi-x-lg"></i>' : '—'));

            $grandTarget += $tQty;
            $grandActual += $aQty;
            $totalParts++;
        ?>
        <tr class="<?= $r['is_fixed'] ? 'row-fixed' : '' ?>">
            <td style="font-size:.7rem; color:#64748b; background:<?= $isFixed ? '#f0fdf4' : '#f8fafc' ?>; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"></td>
            <td class="td-no"><?= $rowNo++ ?></td>
            <td class="td-partname"><?= esc($r['part_name']) ?></td>

            <!-- TARGET DELIVERY -->
            <td class="text-center" style="background:#f1f5f9; border-left:2px solid #cbd5e1;">
                <span class="fw-bold fs-6 text-primary"><?= $tQty > 0 ? $tQty : '—' ?></span>
            </td>
            <td class="text-center" style="background:#f1f5f9;">
                <span class="text-muted fw-bold">—</span>
            </td>

            <!-- ACTUAL DELIVERY RIT-1 -->
            <td class="text-center" style="background:#fff; border-left:2px solid #cbd5e1;">
                <?php 
                    $aclass1 = 'grey';
                    if ($t1 > 0) $aclass1 = ($a1 >= $t1) ? 'blue' : 'red';
                ?>
                <span class="actual-qty <?= $aclass1 ?>"><?= ($t1 > 0 || $a1 > 0) ? $a1 : '—' ?></span>
            </td>
            <td class="text-center" style="background:#fff;">
                <span class="text-muted fw-bold">—</span>
            </td>

            <!-- ACTUAL DELIVERY RIT-2 -->
            <td class="text-center" style="background:#f8fafc; border-left:2px solid #cbd5e1;">
                <?php 
                    $aclass2 = 'grey';
                    if ($t2 > 0) $aclass2 = ($a2 >= $t2) ? 'blue' : 'red';
                ?>
                <span class="actual-qty <?= $aclass2 ?>"><?= ($t2 > 0 || $a2 > 0) ? $a2 : '—' ?></span>
            </td>
            <td class="text-center" style="background:#f8fafc;">
                <span class="text-muted fw-bold">—</span>
            </td>

            <!-- STATUS -->
            <td class="text-center" style="background:#f0fdf4; border-left:2px solid #cbd5e1;">
                <span class="status-dot <?= $statusClass ?>"><?= $statusLabel ?></span>
            </td>

            <!-- KETERANGAN -->
            <td class="text-center" style="background:#fff; border-left:2px solid #cbd5e1;">
                <span class="text-muted">—</span>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>

    </tbody>
</table>
</div>

<!-- ══ SUMMARY BAR ══ -->
<div class="dcb-summary-bar">
    <div class="summary-stat">
        <span class="val"><?= $totalParts ?></span>
        <span class="lbl">Total Part</span>
    </div>
    <div class="summary-stat">
        <span class="val text-primary"><?= number_format($grandTarget) ?></span>
        <span class="lbl">Total Target</span>
    </div>
    <div class="summary-stat">
        <span class="val" style="color:#065f46;"><?= number_format($grandActual) ?></span>
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
</div>
</div>

<script>
function exportPDF() { window.print(); }

function exportExcel() {
    if (typeof XLSX === 'undefined') { alert('Library Excel belum siap. Pastikan ada koneksi internet.'); return; }
    const table = document.getElementById('dcbTable');
    const rows  = [];
    const date  = '<?= esc($date) ?>';

    table.querySelectorAll('thead tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('th').forEach(th => { row.push(th.innerText.replace(/\n/g,' ').trim()); });
        rows.push(row);
    });
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => {
            const spn = td.querySelector('span');
            if (spn) row.push(spn.textContent.trim());
            else row.push(td.innerText.trim());
        });
        if (row.length > 0) rows.push(row);
    });

    const ws = XLSX.utils.aoa_to_sheet(rows);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'DeliveryControlBoard');
    XLSX.writeFile(wb, `DCB_${date}.xlsx`);
}
/* ══ PLAY MODE: Auto-Scroll + Auto-Refresh ══ */
let _play = false;
let _scrollRaf = null;
let _refreshInt = null;
let _scrollDir  = 1;      // 1 = bawah, -1 = atas
const PLAY_REFRESH_SECS = 30;
const SCROLL_SPEED = 0.6;  // px per frame (~36px/s pada 60fps)

function getScrollContainer() {
    // Layout ini menggunakan .main-content sebagai scroll container
    return document.querySelector('.main-content') || document.documentElement;
}

function playScroll() {
    if (!_play) return;
    const el   = getScrollContainer();
    const maxY = el.scrollHeight - el.clientHeight;
    if (maxY <= 0) { _scrollRaf = requestAnimationFrame(playScroll); return; }

    el.scrollTop += _scrollDir * SCROLL_SPEED;

    const y = el.scrollTop;
    if (_scrollDir === 1  && y >= maxY - 1) _scrollDir = -1;  // sampai bawah → balik atas
    if (_scrollDir === -1 && y <= 1)         _scrollDir =  1;  // sampai atas  → balik bawah

    _scrollRaf = requestAnimationFrame(playScroll);
}

function togglePlayMode() {
    _play = !_play;
    const btn    = document.getElementById('playBtn');
    const icon   = document.getElementById('playIcon');
    const label  = document.getElementById('playLabel');
    const status = document.getElementById('playStatus');
    const timer  = document.getElementById('refreshTimer');

    if (_play) {
        /* --- UI: aktifkan mode play ---- */
        icon.className = 'bi bi-stop-fill';
        label.textContent = 'Stop';
        btn.style.background = '#dc2626';
        status.style.display = 'flex';
        status.style.alignItems = 'center';
        status.style.gap = '.4rem';

        /* --- Reset ke atas ---- */
        getScrollContainer().scrollTop = 0;
        _scrollDir = 1;

        /* --- Mulai scroll dengan rAF ---- */
        _scrollRaf = requestAnimationFrame(playScroll);

        /* --- Countdown auto-refresh ---- */
        let secs = PLAY_REFRESH_SECS;
        timer.textContent = secs;

        _refreshInt = setInterval(() => {
            secs -= 1;
            if (secs <= 0) {
                clearInterval(_refreshInt);
                cancelAnimationFrame(_scrollRaf);
                _refreshInt = null;
                _scrollRaf  = null;
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
        cancelAnimationFrame(_scrollRaf);
        clearInterval(_refreshInt);
        _scrollRaf  = null;
        _refreshInt = null;

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
