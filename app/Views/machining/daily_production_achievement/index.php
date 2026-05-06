<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">MACHINING – DAILY PRODUCTION PER SHIFT</h4>

<style>
.ng-inline{ display:flex; flex-direction:column; gap:8px; }
.ng-inline-head{ display:flex; justify-content:space-between; align-items:center; gap:8px; }
.ng-mini-table{ width:100%; border-collapse:separate; border-spacing:0; }
.ng-mini-table th, .ng-mini-table td{ border:1px solid #e5e7eb; padding:6px 6px; font-size:12px; text-align:left; background:#fff; }
.ng-mini-table th{ background:#f8fafc; font-weight:900; text-align:center; }
.ng-mini-table td.ng-no{ width:60px; text-align:center; font-weight:900; }
.ng-mini-table td.ng-qty{ width:110px; }
.ng-mini-table td.ng-act{ width:70px; text-align:center; }
.ng-empty{ font-size:12px; color:#64748b; font-weight:700; text-align:center; padding:8px 0; border:1px dashed #cbd5e1; border-radius:8px; }
.dt-inline{ display:flex; flex-direction:column; gap:6px; }
.dt-inline-head{ display:flex; justify-content:space-between; align-items:center; gap:6px; }
</style>

<form method="get" class="mb-3 d-flex flex-wrap gap-2 align-items-end justify-content-between">
    <div class="d-flex flex-wrap gap-2 align-items-end">
        <div>
            <label class="form-label small mb-1">Tanggal</label>
            <input type="date" name="date" value="<?= esc($date) ?>" class="form-control" onchange="this.form.submit()">
        </div>
        <div>
            <button class="btn btn-primary btn-sm" type="submit">
                <i class="bi bi-search"></i> Filter
            </button>
        </div>
    </div>
    
    <?php if (isset($isAdmin) && $isAdmin): ?>
    <div>
        <div class="form-check form-switch mb-1 d-inline-block">
            <input class="form-check-input border-danger" type="checkbox" id="adminOverrideToggle" role="switch" style="cursor: pointer; transform: scale(1.2);">
            <label class="form-check-label fw-bold text-danger ms-2" for="adminOverrideToggle" style="cursor: pointer;">
                <i class="bi bi-unlock-fill"></i> Unlock Form Koreksi (Admin)
            </label>
        </div>
    </div>
    <?php endif; ?>
</form>

<div class="alert alert-success fw-bold">
    DAILY SUMMARY<br>
    Target : <?= number_format((int)$dailyTarget) ?> |
    FG : <?= number_format((int)$dailyFG) ?> |
    NG : <?= number_format((int)$dailyNG) ?> |
    Downtime : <?= number_format((int)$dailyDT) ?> |
    Efficiency :
    <span class="badge bg-dark"><?= esc($dailyEfficiency) ?> %</span>
</div>

<form method="post" action="/machining/daily-production-achievement/store" id="mainFormShift">
    <?= csrf_field() ?>

    <?php foreach ($shifts as $shift): ?>

        <h5 class="mt-4 mb-2">
            <?= esc($shift['shift_name']) ?>
        </h5>

        <?php if (!$shift['isEditable']): ?>
            <div class="alert alert-warning py-2 small">
                <i class="bi bi-lock-fill"></i>
                Koreksi hanya dapat dilakukan <strong>maksimal 1 jam setelah shift berakhir</strong>.
                <?php if (!empty($shift['editDeadline'])): ?>
                    <div class="mt-1">
                        Batas koreksi: <strong><?= esc($shift['editDeadline']) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-success py-2 small">
                <i class="bi bi-unlock-fill"></i>
                Koreksi aktif.
                <?php if (!empty($shift['editDeadline'])): ?>
                    Batas koreksi: <strong><?= esc($shift['editDeadline']) ?></strong>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="table-responsive mb-4">
            <table class="table table-bordered table-sm align-middle">

                <thead class="table-light">
                <tr class="text-center">
                    <th style="width:40px">No</th>
                    <th>Part</th>
                    <th style="width:90px">Target</th>
                    <th style="width:90px">FG (Actual)</th>
                    <th style="width:90px">NG</th>
                    <th style="min-width:280px">NG Category</th>
                    <th style="width:90px">NG Blank Material</th>
                    <th style="width:120px">Catatan</th>
                    <th style="width:160px">Next Process</th>
                    <th style="width:90px">WIP Qty</th>
                    <th style="width:120px">WIP Status</th>
                    <th style="min-width:230px">Downtime</th>
                </tr>
                </thead>

                <tbody>
                <?php
                $no = 1;
                $totalTarget = 0;
                $totalFG = 0;
                $totalNG = 0;
                $totalDT = 0;
                ?>

                <?php if (empty($shift['items'])): ?>
                    <tr>
                        <td colspan="12" class="text-center text-muted">
                            Tidak ada data schedule
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($shift['items'] as $row): ?>
                    <?php
                    $totalTarget += (int)$row['target'];
                    $totalFG     += (int)$row['fg_display'];
                    $totalNG     += (int)$row['ng_display'];
                    $totalDT     += (int)($row['downtime'] ?? 0);

                    $wipStatus = $row['wip_status'] ?? 'WAITING';
                    $badge = 'secondary';
                    if ($wipStatus === 'WAITING') $badge = 'warning';
                    if ($wipStatus === 'SCHEDULED') $badge = 'info';
                    if ($wipStatus === 'DONE') $badge = 'success';

                    $key = $shift['id'].'_'.$row['machine_id'].'_'.$row['product_id'];
                    ?>

                    <tr>
                        <td class="text-center"><?= $no++ ?></td>

                        <td>
                            <strong><?= esc($row['part_no']) ?></strong><br>
                            <small class="text-muted"><?= esc($row['part_name']) ?></small>
                            <div class="small text-muted mt-1">
                                <span class="me-2">Mesin: <strong><?= esc($row['machine_code'] ?? '-') ?></strong></span>
                                <span class="me-2">Line: <strong><?= esc($row['line_position'] ?? '-') ?></strong></span>
                            </div>
                        </td>

                        <td class="text-end fw-bold">
                            <?= number_format((int)$row['target']) ?>
                        </td>

                        <td>
                            <input type="number"
                                   class="form-control form-control-sm text-end editable-field"
                                   name="items[<?= $key ?>][fg]"
                                   value="<?= (int)$row['fg_display'] ?>"
                                   <?= !$shift['isEditable'] ? 'disabled' : '' ?>>
                        </td>

                        <?php $ngDetail = $shift['ng_detail_map'][$row['machine_id']][$row['product_id']] ?? []; ?>
                        <td>
                            <input type="number"
                                   class="form-control form-control-sm text-end ng-total-val"
                                   id="ngTotalInput_<?= esc($key) ?>"
                                   name="items[<?= $key ?>][ng]"
                                   value="<?= (int)$row['ng_display'] ?>"
                                   readonly>
                        </td>

                        <td class="text-start">
                            <div class="ng-inline" data-key="<?= esc($key) ?>">
                                <div class="ng-inline-head mb-1 d-flex justify-content-between align-items-center">
                                    <div class="meta text-muted" style="font-size:12px; font-weight:bold;">
                                        Total NG: <span class="fw-bold text-danger" id="ngTotalBadge_<?= esc($key) ?>"><?= (int)$row['ng_display'] ?></span>
                                    </div>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger fw-bold ng-add-btn editable-field"
                                            onclick="addNgRowInline('<?= esc($key) ?>', <?= $shift['id'] ?>)"
                                            <?= !$shift['isEditable'] ? 'disabled' : '' ?>>
                                        + NG
                                    </button>
                                </div>
                                <div class="table-responsive">
                                    <table class="ng-mini-table table-bordered mb-0" style="font-size:12px;">
                                        <thead class="table-light text-center">
                                            <tr>
                                                <th style="width:60px">No</th>
                                                <th>Category</th>
                                                <th style="width:80px">Qty</th>
                                                <th style="width:50px"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="ngBody_<?= esc($key) ?>">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="ng-hidden d-none" id="ngHidden_<?= esc($key) ?>">
                                <?php foreach ($ngDetail as $idx => $d): ?>
                                    <input type="hidden" name="items[<?= esc($key) ?>][ng_details][<?= $idx ?>][ng_category_id]" value="<?= (int)$d['ng_category_id'] ?>">
                                    <input type="hidden" name="items[<?= esc($key) ?>][ng_details][<?= $idx ?>][qty]" value="<?= (int)$d['qty'] ?>">
                                <?php endforeach; ?>
                            </div>
                        </td>

                        <td>
                            <input type="number"
                                   class="form-control form-control-sm text-end editable-field"
                                   name="items[<?= $key ?>][ng_blank]"
                                   value="<?= (int)($row['ng_blank_display'] ?? 0) ?>"
                                   <?= !$shift['isEditable'] ? 'disabled' : '' ?>>
                        </td>

                        <td class="text-center align-middle">
                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold"
                                    onclick="openRemarkModal('<?= esc($key) ?>', <?= $shift['id'] ?>)">
                                Atur Catatan
                            </button>
                            <div class="remark-hidden d-none" id="remarkHidden_<?= esc($key) ?>">
                                <?php 
                                $remarkMap = $shift['ng_detail_map']['remark_map'][$row['machine_id']][$row['product_id']] ?? [];
                                foreach ($shift['slots'] as $slot): 
                                    $sId = (int)$slot['id'];
                                    $rmk = $remarkMap[$sId] ?? '';
                                ?>
                                    <input type="hidden" name="items[<?= esc($key) ?>][remarks][<?= $sId ?>]" value="<?= esc($rmk) ?>" data-slot-id="<?= $sId ?>">
                                <?php endforeach; ?>
                            </div>
                        </td>

                        <td class="text-center">
                            <?= esc($row['next_process_name'] ?? '-') ?>
                        </td>

                        <td class="text-end">
                            <?= number_format((int)($row['wip_qty'] ?? 0)) ?>
                        </td>

                        <td class="text-center">
                            <span class="badge bg-<?= $badge ?>">
                                <?= esc($wipStatus) ?>
                            </span>
                        </td>

                        <td>
                            <?php
                            $dtDetail = $shift['dt_detail_map'][$row['machine_id']][$row['product_id']] ?? [];
                            ?>
                            <div class="dt-inline" data-key="<?= esc($key) ?>">
                                <div class="dt-inline-head">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-warning fw-bold dt-add-btn editable-field"
                                            onclick="addDtRowInline('<?= esc($key) ?>')"
                                            <?= !$shift['isEditable'] ? 'disabled' : '' ?>>
                                        + DT
                                    </button>
                                </div>
                                <div class="table-responsive">
                                    <table class="ng-mini-table mb-0" style="font-size:12px;">
                                        <thead class="table-light text-center">
                                            <tr>
                                                <th>Kategori</th>
                                                <th style="width:70px">Menit</th>
                                                <th style="width:40px"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="dtBody_<?= esc($key) ?>"></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="dt-hidden d-none" id="dtHidden_<?= esc($key) ?>">
                                <?php foreach ($dtDetail as $idx => $d): ?>
                                    <input type="hidden" name="items[<?= esc($key) ?>][dt_details][<?= $idx ?>][downtime_category_id]" value="<?= (int)$d['downtime_category_id'] ?>">
                                    <input type="hidden" name="items[<?= esc($key) ?>][dt_details][<?= $idx ?>][downtime_minute]" value="<?= (int)$d['downtime_minute'] ?>">
                                <?php endforeach; ?>
                            </div>
                        </td>

                        <!-- HIDDEN -->
                        <input type="hidden" name="items[<?= $key ?>][machine_id]" value="<?= (int)$row['machine_id'] ?>">
                        <input type="hidden" name="items[<?= $key ?>][product_id]" value="<?= (int)$row['product_id'] ?>">
                        <input type="hidden" name="items[<?= $key ?>][shift_id]" value="<?= (int)$shift['id'] ?>">
                        <input type="hidden" name="items[<?= $key ?>][date]" value="<?= esc($date) ?>">

                    </tr>
                <?php endforeach; ?>
                </tbody>

                <tfoot class="table-secondary fw-bold">
                <tr>
                    <td colspan="2" class="text-end">TOTAL</td>
                    <td class="text-end"><?= number_format($totalTarget) ?></td>
                    <td class="text-end"><?= number_format($totalFG) ?></td>
                    <td class="text-end"><?= number_format($totalNG) ?></td>
                    <td colspan="7"></td>
                </tr>

                <tr>
                    <td colspan="2" class="text-end">EFFICIENCY</td>
                    <td colspan="8">
                        <?= $totalTarget > 0 ? round(($totalFG / $totalTarget) * 100, 1) : 0 ?> %
                        <span class="ms-3 text-muted">(DT: <?= number_format((int)$totalDT) ?> min)</span>
                    </td>
                </tr>
                </tfoot>

            </table>
        </div>

    <?php endforeach; ?>

    <button class="btn btn-success">
        <i class="bi bi-save"></i> Simpan Koreksi
    </button>
</form>

<!-- Modal Catatan -->
<div class="modal fade" id="remarkModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title mb-0">Atur Catatan per Slot</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="remarkKey">
                <input type="hidden" id="remarkShiftId">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light text-center">
                            <tr>
                                <th style="width: 120px;">Waktu</th>
                                <th>Catatan / Keterangan</th>
                            </tr>
                        </thead>
                        <tbody id="remarkTbody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-1">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="saveRemarkModal()">Simpan</button>
            </div>
        </div>
    </div>
</div>

<script>
    const SHIFT_SLOTS = <?= json_encode(array_map(function($s) {
        return [
            'id' => $s['id'],
            'slots' => array_map(function($slot) {
                return [
                    'id' => (int)$slot['id'],
                    'label' => substr($slot['time_start'], 0, 5) . ' - ' . substr($slot['time_end'], 0, 5)
                ];
            }, $s['slots'] ?? [])
        ];
    }, $shifts)) ?>;

    function getShiftSlots(shiftId) {
        const shift = SHIFT_SLOTS.find(s => parseInt(s.id, 10) === parseInt(shiftId, 10));
        return shift ? shift.slots : [];
    }
    const NG_CATEGORIES = <?= json_encode(array_map(fn($x)=>[
        'id'=>(int)$x['id'],
        'ng_code'=>(int)$x['ng_code'],
        'ng_name'=>(string)$x['ng_name']
    ], $ngCategories)) ?>;

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, function(m) { return {'&': '&amp;','<': '&lt;','>': '&gt;','"': '&quot;',"'": '&#039;'}[m]; });
    }

    function buildNgSelectOptions(selectedId){
        let html = `<option value="0">-- pilih --</option>`;
        NG_CATEGORIES.forEach(c=>{
            const sel = (parseInt(selectedId||0,10) === c.id) ? 'selected' : '';
            html += `<option value="${c.id}" ${sel}>${c.ng_code} - ${escapeHtml(c.ng_name)}</option>`;
        });
        return html;
    }

    function readNgHidden(key){
        const hidden = document.getElementById('ngHidden_'+key);
        const rows = [];
        if(!hidden) return rows;

        const inputs = hidden.querySelectorAll('input');
        const map = {};
        inputs.forEach(inp=>{
            const m = inp.name.match(/\[ng_details\]\[(\d+)\]\[(ng_category_id|qty)\]/);
            if(!m) return;
            const idx = m[1];
            const field = m[2];
            map[idx] = map[idx] || {};
            map[idx][field] = inp.value;
        });

        Object.keys(map).sort((a,b)=>parseInt(a,10)-parseInt(b,10)).forEach(k=>{
            rows.push({
                ng_category_id: parseInt(map[k].ng_category_id || '0',10),
                qty: parseInt(map[k].qty || '0',10),
            });
        });

        return rows;
    }

    function writeNgHiddenFromRows(key, rows){
        const hidden = document.getElementById('ngHidden_'+key);
        if(!hidden) return;
        hidden.innerHTML = '';

        rows.forEach((r,i)=>{
            const ngId = parseInt(r.ng_category_id || 0, 10);
            const qty  = parseInt(r.qty || 0, 10);

            const a = document.createElement('input');
            a.type='hidden';
            a.name=`items[${key}][ng_details][${i}][ng_category_id]`;
            a.value=String(isNaN(ngId)?0:ngId);
            hidden.appendChild(a);

            const b = document.createElement('input');
            b.type='hidden';
            b.name=`items[${key}][ng_details][${i}][qty]`;
            b.value=String(isNaN(qty)?0:qty);
            hidden.appendChild(b);
        });
    }

    function calcTotalNg(rows){
        return rows.reduce((s,r)=>{
            const ngId = parseInt(r.ng_category_id||0,10);
            const qty  = parseInt(r.qty||0,10);
            if(ngId>0 && qty>0) return s + qty;
            return s;
        },0);
    }

    function updateNgTotalUI(key, total, shiftId){
        const badge = document.getElementById('ngTotalBadge_'+key);
        if(badge) badge.textContent = String(total);

        const ngInp = document.getElementById('ngTotalInput_'+key);
        if(ngInp) {
            ngInp.value = String(total);
        }
    }

    function renderNgTable(key, shiftId, isEditable){
        const tbody = document.getElementById('ngBody_'+key);
        if(!tbody) return;

        const rows = readNgHidden(key);
        tbody.innerHTML = '';

        if(rows.length === 0){
            tbody.innerHTML = `<tr><td colspan="4"><div class="ng-empty">Belum ada NG</div></td></tr>`;
            updateNgTotalUI(key, 0, shiftId);
        } else {
            rows.forEach((r, idx)=>{
                const cat = NG_CATEGORIES.find(x=>x.id === (parseInt(r.ng_category_id||0,10))) || null;
                const code = cat ? cat.ng_code : '-';
                const dis = !isEditable ? 'disabled' : '';
                const ro = !isEditable ? 'readonly' : '';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="ng-no">${escapeHtml(code)}</td>
                    <td>
                      <select class="form-select form-select-sm ngSel editable-field" data-key="${escapeHtml(key)}" data-shift-id="${shiftId}" data-idx="${idx}" ${dis}>
                        ${buildNgSelectOptions(r.ng_category_id)}
                      </select>
                    </td>
                    <td class="ng-qty">
                      <input type="number" class="form-control form-control-sm ngQty editable-field" min="0" data-key="${escapeHtml(key)}" data-shift-id="${shiftId}" data-idx="${idx}" value="${parseInt(r.qty||0,10)}" ${ro}>
                    </td>
                    <td class="ng-act">
                      <button type="button" class="btn btn-sm btn-danger py-0 px-2 ng-del-btn editable-field" onclick="deleteNgRowInline('${escapeHtml(key)}', ${idx}, ${shiftId})" ${dis}>
                        <i class="bi bi-x"></i>
                      </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
            updateNgTotalUI(key, calcTotalNg(rows), shiftId);
        }
    }

    function addNgRowInline(key, shiftId){
        const rows = readNgHidden(key);
        rows.push({ ng_category_id: 0, qty: 0 }); 
        writeNgHiddenFromRows(key, rows);
        renderNgTable(key, shiftId, true);
    }

    function deleteNgRowInline(key, idx, shiftId){
        const rows = readNgHidden(key);
        rows.splice(idx, 1);
        writeNgHiddenFromRows(key, rows);
        renderNgTable(key, shiftId, true);
    }

    document.addEventListener('change', function(e){
        const sel = e.target.closest('.ngSel');
        if(!sel) return;

        const key = sel.dataset.key;
        const shiftId = sel.dataset.shiftId;
        const idx = parseInt(sel.dataset.idx||'0',10);
        const rows = readNgHidden(key);
        if(!rows[idx]) return;

        rows[idx].ng_category_id = parseInt(sel.value||'0',10);
        writeNgHiddenFromRows(key, rows);

        const cat = NG_CATEGORIES.find(x=>x.id === rows[idx].ng_category_id) || null;
        const tr = sel.closest('tr');
        const codeTd = tr ? tr.querySelector('.ng-no') : null;
        if(codeTd) codeTd.textContent = cat ? cat.ng_code : '-';

        updateNgTotalUI(key, calcTotalNg(rows), shiftId);
    });

    document.addEventListener('input', function(e){
        const inp = e.target.closest('.ngQty');
        if(!inp) return;

        const key = inp.dataset.key;
        const shiftId = inp.dataset.shiftId;
        const idx = parseInt(inp.dataset.idx||'0',10);
        let v = parseInt(inp.value||'0',10);
        if(isNaN(v) || v < 0) v = 0;
        inp.value = String(v);

        const rows = readNgHidden(key);
        if(!rows[idx]) return;

        rows[idx].qty = v;
        writeNgHiddenFromRows(key, rows);

        updateNgTotalUI(key, calcTotalNg(rows), shiftId);
    });

    const DT_CATEGORIES = <?= json_encode(array_map(fn($x)=>[
        'id'            => (int)$x['id'],
        'downtime_name' => (string)$x['downtime_name'],
    ], $downtimes)) ?>;

    /* ===== DT INLINE ===== */
    function buildDtSelectOptions(selectedId) {
        let html = `<option value="0">-- pilih --</option>`;
        DT_CATEGORIES.forEach(c => {
            const sel = (parseInt(selectedId||0,10) === c.id) ? 'selected' : '';
            html += `<option value="${c.id}" ${sel}>${escapeHtml(c.downtime_name)}</option>`;
        });
        return html;
    }
    function readDtHidden(key) {
        const hidden = document.getElementById('dtHidden_' + key);
        const rows = [];
        if (!hidden) return rows;
        const map = {};
        hidden.querySelectorAll('input').forEach(inp => {
            const m = inp.name.match(/\[dt_details\]\[(\d+)\]\[(downtime_category_id|downtime_minute)\]/);
            if (!m) return;
            const idx = m[1]; const field = m[2];
            map[idx] = map[idx] || {};
            map[idx][field] = inp.value;
        });
        Object.keys(map).sort((a,b)=>parseInt(a)-parseInt(b)).forEach(k => rows.push({
            downtime_category_id: parseInt(map[k].downtime_category_id || '0', 10),
            downtime_minute: parseInt(map[k].downtime_minute || '0', 10)
        }));
        return rows;
    }
    function writeDtHiddenFromRows(key, rows) {
        const hidden = document.getElementById('dtHidden_' + key);
        if (!hidden) return;
        hidden.innerHTML = '';
        rows.forEach((r, i) => {
            const a = document.createElement('input'); a.type='hidden'; a.name=`items[${key}][dt_details][${i}][downtime_category_id]`; a.value=String(r.downtime_category_id||0); hidden.appendChild(a);
            const b = document.createElement('input'); b.type='hidden'; b.name=`items[${key}][dt_details][${i}][downtime_minute]`; b.value=String(r.downtime_minute||0); hidden.appendChild(b);
        });
    }
    function renderDtTable(key, isEditable) {
        const tbody = document.getElementById('dtBody_' + key);
        if (!tbody) return;
        const rows = readDtHidden(key);
        tbody.innerHTML = '';
        if (rows.length === 0) { tbody.innerHTML = `<tr><td colspan="3"><div class="ng-empty">Belum ada DT</div></td></tr>`; return; }
        const dis = !isEditable ? 'disabled' : '';
        rows.forEach((r, idx) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td><select class="form-select form-select-sm dtSel editable-field" data-key="${escapeHtml(key)}" data-idx="${idx}" ${dis}>${buildDtSelectOptions(r.downtime_category_id)}</select></td>
                <td class="ng-qty"><input type="number" class="form-control form-control-sm dtQty editable-field" min="0" data-key="${escapeHtml(key)}" data-idx="${idx}" value="${r.downtime_minute||0}" ${dis}></td>
                <td class="ng-act"><button type="button" class="btn btn-sm btn-danger py-0 px-2 editable-field" onclick="deleteDtRowInline('${escapeHtml(key)}',${idx})" ${dis}><i class="bi bi-x"></i></button></td>`;
            tbody.appendChild(tr);
        });
    }
    function addDtRowInline(key) { const rows=readDtHidden(key); rows.push({downtime_category_id:0,downtime_minute:0}); writeDtHiddenFromRows(key,rows); renderDtTable(key,true); }
    function deleteDtRowInline(key, idx) { const rows=readDtHidden(key); rows.splice(idx,1); writeDtHiddenFromRows(key,rows); renderDtTable(key,true); }
    document.addEventListener('change', function(e) { const sel=e.target.closest('.dtSel'); if(!sel) return; const key=sel.dataset.key,idx=parseInt(sel.dataset.idx||'0',10); const rows=readDtHidden(key); if(!rows[idx]) return; rows[idx].downtime_category_id=parseInt(sel.value||'0',10); writeDtHiddenFromRows(key,rows); });
    document.addEventListener('input', function(e) { const inp=e.target.closest('.dtQty'); if(!inp) return; const key=inp.dataset.key,idx=parseInt(inp.dataset.idx||'0',10); let v=parseInt(inp.value||'0',10); if(isNaN(v)||v<0) v=0; inp.value=String(v); const rows=readDtHidden(key); if(!rows[idx]) return; rows[idx].downtime_minute=v; writeDtHiddenFromRows(key,rows); });

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.ng-inline').forEach(el => {
            const key = el.dataset.key;
            const btn = el.querySelector('.ng-add-btn');
            renderNgTable(key, 0, btn && !btn.disabled);
        });
        document.querySelectorAll('.dt-inline').forEach(el => {
            const key = el.dataset.key;
            const btn = el.querySelector('.dt-add-btn');
            renderDtTable(key, btn && !btn.disabled);
        });

        const adminToggle = document.getElementById('adminOverrideToggle');
        if (adminToggle) {
            adminToggle.addEventListener('change', function() {
                const isChecked = this.checked;
                document.querySelectorAll('.editable-field').forEach(input => {
                    if (isChecked) {
                        if (!input.hasAttribute('data-original-disabled')) input.setAttribute('data-original-disabled', input.disabled || input.readOnly);
                        input.disabled = false;
                        if(input.tagName === 'INPUT') input.readOnly = false;
                    } else {
                        const wasDisabled = input.getAttribute('data-original-disabled');
                        if (wasDisabled === 'true') { input.disabled = true; if(input.tagName === 'INPUT') input.readOnly = true; }
                    }
                });
                document.querySelectorAll('.ng-inline').forEach(el => renderNgTable(el.dataset.key, 0, isChecked));
                document.querySelectorAll('.dt-inline').forEach(el => renderDtTable(el.dataset.key, isChecked));
            });
        }
    });

    /* =========================
     * REMARK MODAL
     * ========================= */
    let remarkModal = null;

    function openRemarkModal(key, shiftId) {
        if (!remarkModal) remarkModal = new bootstrap.Modal(document.getElementById('remarkModal'));
        document.getElementById('remarkKey').value = key;
        document.getElementById('remarkShiftId').value = shiftId;
        const tbody = document.getElementById('remarkTbody');
        tbody.innerHTML = '';
        const slots = getShiftSlots(shiftId);
        if (slots.length === 0) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-muted text-center py-2">Tidak ada slot waktu untuk shift ini.</td></tr>';
            remarkModal.show(); return;
        }
        const hiddenDiv = document.getElementById('remarkHidden_' + key);
        const remarksMap = {};
        if (hiddenDiv) hiddenDiv.querySelectorAll('input').forEach(inp => { remarksMap[parseInt(inp.dataset.slotId||'0',10)] = inp.value; });
        slots.forEach(slot => {
            const rmk = remarksMap[slot.id] || '';
            const tr = document.createElement('tr');
            const td1 = document.createElement('td');
            td1.style.cssText = 'width:110px; text-align:center; font-size:12px; font-weight:bold; vertical-align:middle;';
            td1.textContent = slot.label;
            const td2 = document.createElement('td');
            const inp = document.createElement('input');
            inp.type='text'; inp.className='form-control form-control-sm remark-input';
            inp.dataset.slotId = slot.id; inp.value = rmk; inp.placeholder = 'Isi catatan...';
            td2.appendChild(inp); tr.appendChild(td1); tr.appendChild(td2); tbody.appendChild(tr);
        });
        remarkModal.show();
    }

    function saveRemarkModal() {
        const key = document.getElementById('remarkKey').value;
        const hiddenDiv = document.getElementById('remarkHidden_' + key);
        if (!hiddenDiv) return;
        hiddenDiv.innerHTML = '';
        document.querySelectorAll('#remarkTbody .remark-input').forEach(inp => {
            const slotId = inp.dataset.slotId;
            const hiddenInp = document.createElement('input');
            hiddenInp.type = 'hidden';
            hiddenInp.name = 'items[' + key + '][remarks][' + slotId + ']';
            hiddenInp.value = inp.value;
            hiddenInp.dataset.slotId = slotId;
            hiddenDiv.appendChild(hiddenInp);
        });
        remarkModal.hide();
    }
</script>
<?= $this->endSection() ?>
