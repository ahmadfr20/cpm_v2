<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0 fw-bold">SCHEDULE DELIVERY FINISHED GOOD</h4>
        <small class="text-muted">Manajemen pengiriman produk Finished Good (RIT 1-2 Normal, RIT 1-5 Special)</small>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success py-2"><i class="bi bi-check-circle"></i> <?= session()->getFlashdata('success') ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle"></i> <?= session()->getFlashdata('error') ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="d-flex align-items-end gap-2 mb-3">
            <div>
                <label class="form-label small mb-1 fw-bold">Tanggal Pengiriman</label>
                <input type="date" name="date" class="form-control form-control-sm" value="<?= esc($date) ?>" onchange="this.form.submit()">
            </div>
        </form>

        <form method="post" action="<?= site_url('/finished-good/delivery-schedule/store') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="delivery_date" value="<?= esc($date) ?>">

            <div class="mb-3 text-end d-none" id="customControls">
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRow"><i class="bi bi-plus-lg"></i> Tambah Item Lainnya</button>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle text-center" id="scheduleTable">
                    <thead class="table-light">
                        <tr>
                            <th rowspan="2" class="align-middle px-2">Part No</th>
                            <th rowspan="2" class="align-middle px-2">Part Name</th>
                            <th rowspan="2" class="align-middle px-2" style="width:100px;">FG Stock</th>
                            <th rowspan="2" class="align-middle px-2">Customer</th>
                            <th rowspan="2" class="align-middle px-2" style="width:120px;">Tipe Delivery</th>
                            <th rowspan="2" class="align-middle px-2 text-primary" style="width:100px;">Target 1 Hari</th>
                            <th colspan="5" class="py-1">RIT Target</th>
                            <th rowspan="2" class="align-middle px-2" style="width:50px;">Aksi</th>
                        </tr>
                        <tr>
                            <th style="width:80px">RIT 1</th>
                            <th style="width:80px">RIT 2</th>
                            <th style="width:80px" class="bg-warning-subtle text-warning-emphasis">RIT 3</th>
                            <th style="width:80px" class="bg-warning-subtle text-warning-emphasis">RIT 4</th>
                            <th style="width:80px" class="bg-warning-subtle text-warning-emphasis">RIT 5</th>
                        </tr>
                    </thead>
                    <tbody id="scheduleBody">
                        <!-- ================= FIXED ROWS ================= -->
                        <?php foreach ($fixedData as $grp): ?>
                            <?php if (empty($grp['parts'])) continue; ?>
                            <tr class="table-secondary border-secondary">
                                <td colspan="12" class="text-start fw-bold px-3">
                                    <i class="bi bi-building me-2"></i><?= esc($grp['customer_name']) ?> <span class="badge bg-success ms-2">Fixed Target</span>
                                </td>
                            </tr>
                            <?php foreach ($grp['parts'] as $p): 
                                $pid = (int)$p['id'];
                                $sched = $scheduleMap[$pid] ?? null;
                                $dtype = $sched['delivery_type'] ?? 'biasa';
                            ?>
                                <tr class="fixed-row">
                                    <td class="text-start fw-bold px-2"><?= esc($p['part_no'] ?: '-') ?></td>
                                    <td class="text-start text-muted px-2 fw-medium"><small><?= esc($p['fixed_part_name']) ?></small></td>
                                    <td class="text-center fw-bold text-success"><?= number_format($p['stock'] ?? 0) ?></td>
                                    <td class="px-2">
                                        <input type="hidden" name="items[<?= $pid ?>][product_id]" value="<?= $pid ?>">
                                        <input type="hidden" name="items[<?= $pid ?>][customer_id]" value="<?= $grp['customer_id'] ?>">
                                        <span class="text-muted small fw-bold">Fixed Customer</span>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm delivery_type" name="items[<?= $pid ?>][delivery_type]" data-target="<?= $pid ?>">
                                            <option value="biasa" <?= $dtype === 'biasa' ? 'selected' : '' ?>>Biasa</option>
                                            <option value="special" <?= $dtype === 'special' ? 'selected' : '' ?>>Special</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm text-center fw-bold text-primary" name="items[<?= $pid ?>][target_per_shift]" value="<?= (int)($sched['target_per_shift'] ?? 0) ?>" min="0">
                                    </td>
                                    
                                    <td>
                                        <input type="number" class="form-control form-control-sm text-center fixed-rit sum-rit" name="items[<?= $pid ?>][rit_1]" value="<?= (int)($sched['rit_1'] ?? 0) ?>" min="0" data-row="<?= $pid ?>">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm text-center fixed-rit sum-rit" name="items[<?= $pid ?>][rit_2]" value="<?= (int)($sched['rit_2'] ?? 0) ?>" min="0" data-row="<?= $pid ?>">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm text-center fixed-rit sum-rit bg-warning-subtle special-rit-<?= $pid ?>" name="items[<?= $pid ?>][rit_3]" value="<?= (int)($sched['rit_3'] ?? 0) ?>" min="0" data-row="<?= $pid ?>" <?= $dtype === 'biasa' ? 'readonly' : '' ?>>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm text-center fixed-rit sum-rit bg-warning-subtle special-rit-<?= $pid ?>" name="items[<?= $pid ?>][rit_4]" value="<?= (int)($sched['rit_4'] ?? 0) ?>" min="0" data-row="<?= $pid ?>" <?= $dtype === 'biasa' ? 'readonly' : '' ?>>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm text-center fixed-rit sum-rit bg-warning-subtle special-rit-<?= $pid ?>" name="items[<?= $pid ?>][rit_5]" value="<?= (int)($sched['rit_5'] ?? 0) ?>" min="0" data-row="<?= $pid ?>" <?= $dtype === 'biasa' ? 'readonly' : '' ?>>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border px-2 py-1"><i class="bi bi-lock-fill"></i></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        
                        <!-- ================= CUSTOM ROWS (RENDERED IF EXISTS) ================= -->
                        <?php if (count($customSchedules) > 0): ?>
                            <tr class="table-light border-secondary custom-header-tr">
                                <td colspan="12" class="text-start fw-bold px-3 text-primary">
                                    <i class="bi bi-journal-plus me-2"></i>Other Customers 
                                </td>
                            </tr>
                            <?php foreach ($customSchedules as $idx => $sched): 
                                $uid = 'CUST' . $idx . rand(100,999);
                                $dtype = $sched['delivery_type'] ?? 'biasa';
                            ?>
                                <tr class="custom-row" id="row_<?= $uid ?>">
                                    <td colspan="2" class="px-2">
                                        <select class="form-select form-select-sm fw-bold product-select" name="items[<?= $uid ?>][product_id]" required>
                                            <option value="">-- Pilih Product --</option>
                                            <?php foreach ($products as $pr): ?>
                                                <option value="<?= $pr['id'] ?>" data-stock="<?= (int)($pr['stock'] ?? 0) ?>" <?= ($sched['product_id'] == $pr['id']) ? 'selected' : '' ?>>
                                                    <?= esc($pr['part_no'] ?: '-') ?> - <?= esc($pr['part_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="text-center fw-bold text-success stock-col">
                                        <?php if ($sched['product_id']): ?>
                                            <?= number_format(array_reduce($products, function($carry, $pr) use ($sched) {
                                                if ($pr['id'] == $sched['product_id']) return $pr['stock'] ?? 0;
                                                return $carry;
                                            }, 0)) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-2">
                                        <select class="form-select form-select-sm cust-select" name="items[<?= $uid ?>][customer_id]" required>
                                            <option value="">-- Pilih Customer --</option>
                                            <?php foreach ($customers as $cr): ?>
                                                <option value="<?= $cr['id'] ?>" <?= ($sched['customer_id'] == $cr['id']) ? 'selected' : '' ?>>
                                                    <?= esc($cr['customer_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm delivery_type" name="items[<?= $uid ?>][delivery_type]" data-target="<?= $uid ?>">
                                            <option value="biasa" <?= $dtype === 'biasa' ? 'selected' : '' ?>>Biasa</option>
                                            <option value="special" <?= $dtype === 'special' ? 'selected' : '' ?>>Special</option>
                                        </select>
                                    </td>
                                    <td><input type="number" class="form-control form-control-sm text-center fw-bold text-primary" name="items[<?= $uid ?>][target_per_shift]" value="<?= (int)($sched['target_per_shift'] ?? 0) ?>" min="0"></td>
                                    <td><input type="number" class="form-control form-control-sm text-center sum-rit" name="items[<?= $uid ?>][rit_1]" value="<?= (int)($sched['rit_1'] ?? 0) ?>" min="0" data-row="<?= $uid ?>"></td>
                                    <td><input type="number" class="form-control form-control-sm text-center sum-rit" name="items[<?= $uid ?>][rit_2]" value="<?= (int)($sched['rit_2'] ?? 0) ?>" min="0" data-row="<?= $uid ?>"></td>
                                    <td><input type="number" class="form-control form-control-sm text-center sum-rit bg-warning-subtle special-rit-<?= $uid ?>" name="items[<?= $uid ?>][rit_3]" value="<?= (int)($sched['rit_3'] ?? 0) ?>" min="0" data-row="<?= $uid ?>" <?= $dtype === 'biasa' ? 'readonly' : '' ?>></td>
                                    <td><input type="number" class="form-control form-control-sm text-center sum-rit bg-warning-subtle special-rit-<?= $uid ?>" name="items[<?= $uid ?>][rit_4]" value="<?= (int)($sched['rit_4'] ?? 0) ?>" min="0" data-row="<?= $uid ?>" <?= $dtype === 'biasa' ? 'readonly' : '' ?>></td>
                                    <td><input type="number" class="form-control form-control-sm text-center sum-rit bg-warning-subtle special-rit-<?= $uid ?>" name="items[<?= $uid ?>][rit_5]" value="<?= (int)($sched['rit_5'] ?? 0) ?>" min="0" data-row="<?= $uid ?>" <?= $dtype === 'biasa' ? 'readonly' : '' ?>></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-3 d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-primary fw-bold" id="btnAddRowBtn"><i class="bi bi-plus-lg me-1"></i> Tambah Item Lainnya</button>
                <button type="submit" class="btn btn-success fw-bold"><i class="bi bi-save me-1"></i> Simpan Schedule Delivery</button>
            </div>
        </form>
    </div>
</div>

<script>
const productsJson = <?= json_encode($products) ?>;
const customersJson = <?= json_encode($customers) ?>;

document.addEventListener('DOMContentLoaded', function() {
    
    // Toggle read-only for Special RITs
    document.querySelector('#scheduleBody').addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('delivery_type')) {
            let rowId = e.target.getAttribute('data-target');
            let specials = document.querySelectorAll('.special-rit-' + rowId);
            if(e.target.value === 'special') {
                specials.forEach(s => s.removeAttribute('readonly'));
            } else {
                specials.forEach(s => {
                    s.setAttribute('readonly', true);
                    s.value = '0';
                });
            }
        }
    });

    document.querySelector('#scheduleBody').addEventListener('click', function(e) {
        let btn = e.target.closest('.btn-remove');
        if (btn) {
            btn.closest('tr').remove();
            checkCustomHeader();
        }
    });

    // Auto calculate target based on RIT additions
    document.querySelector('#scheduleBody').addEventListener('input', function(e) {
        if (e.target && e.target.classList.contains('sum-rit')) {
            let rowId = e.target.getAttribute('data-row');
            let total = 0;
            document.querySelectorAll(`.sum-rit[data-row="${rowId}"]`).forEach(el => {
                total += parseInt(el.value || 0);
            });
            let targetInput = document.querySelector(`input[name="items[${rowId}][target_per_shift]"]`);
            if (targetInput) targetInput.value = total;
        }
    });

    document.getElementById('btnAddRowBtn').addEventListener('click', function() {
        let tbody = document.getElementById('scheduleBody');
        let uid = 'NEW' + Date.now() + Math.floor(Math.random() * 100);

        // Ensure custom header exists
        if (!tbody.querySelector('.custom-header-tr')) {
            let trH = document.createElement('tr');
            trH.className = 'table-light border-secondary custom-header-tr';
            trH.innerHTML = `<td colspan="12" class="text-start fw-bold px-3 text-primary"><i class="bi bi-journal-plus me-2"></i>Other Customers </td>`;
            tbody.appendChild(trH);
        }

        let pOpts = '<option value="">-- Pilih Product --</option>';
        productsJson.forEach(p => {
            pOpts += `<option value="${p.id}" data-stock="${p.stock || 0}">${p.part_no||'-'} - ${p.part_name}</option>`;
        });

        let cOpts = '<option value="">-- Pilih Customer --</option>';
        customersJson.forEach(c => {
            cOpts += `<option value="${c.id}">${c.customer_name}</option>`;
        });

        let tr = document.createElement('tr');
        tr.className = 'custom-row';
        tr.id = 'row_' + uid;
        tr.innerHTML = `
            <td colspan="2" class="px-2">
                <select class="form-select form-select-sm fw-bold product-select" name="items[${uid}][product_id]" required onchange="updateStock(this)">
                    ${pOpts}
                </select>
            </td>
            <td class="text-center fw-bold text-success stock-col">-</td>
            <td class="px-2">
                <select class="form-select form-select-sm cust-select" name="items[${uid}][customer_id]" required>
                    ${cOpts}
                </select>
            </td>
            <td>
                <select class="form-select form-select-sm delivery_type" name="items[${uid}][delivery_type]" data-target="${uid}">
                    <option value="biasa">Biasa</option>
                    <option value="special">Special</option>
                </select>
            </td>
            <td><input type="number" class="form-control form-control-sm text-center fw-bold text-primary" name="items[${uid}][target_per_shift]" value="0" min="0"></td>
            <td><input type="number" class="form-control form-control-sm text-center sum-rit" name="items[${uid}][rit_1]" value="0" min="0" data-row="${uid}"></td>
            <td><input type="number" class="form-control form-control-sm text-center sum-rit" name="items[${uid}][rit_2]" value="0" min="0" data-row="${uid}"></td>
            <td><input type="number" class="form-control form-control-sm text-center sum-rit bg-warning-subtle special-rit-${uid}" name="items[${uid}][rit_3]" value="0" min="0" readonly data-row="${uid}"></td>
            <td><input type="number" class="form-control form-control-sm text-center sum-rit bg-warning-subtle special-rit-${uid}" name="items[${uid}][rit_4]" value="0" min="0" readonly data-row="${uid}"></td>
            <td><input type="number" class="form-control form-control-sm text-center sum-rit bg-warning-subtle special-rit-${uid}" name="items[${uid}][rit_5]" value="0" min="0" readonly data-row="${uid}"></td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove"><i class="bi bi-trash"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    function checkCustomHeader() {
        let counts = document.querySelectorAll('.custom-row').length;
        if (counts === 0) {
            let hdr = document.querySelector('.custom-header-tr');
            if (hdr) hdr.remove();
        }
    }
});

function updateStock(selectEl) {
    let opt = selectEl.options[selectEl.selectedIndex];
    let row = selectEl.closest('tr');
    let stockCol = row.querySelector('.stock-col');
    if (opt && opt.value) {
        let val = parseInt(opt.getAttribute('data-stock') || '0');
        stockCol.textContent = val.toLocaleString('en-US');
    } else {
        stockCol.textContent = '-';
    }
}
</script>

<?= $this->endSection() ?>
