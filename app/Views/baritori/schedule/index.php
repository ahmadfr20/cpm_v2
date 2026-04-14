<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
  .wrapx{ background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px; }
  .title{ font-weight:900;font-size:18px; }
  .sub{ color:#64748b;font-weight:600;margin-top:2px; }
  .grid{ display:grid;grid-template-columns: 140px 140px 200px 1fr 120px;gap:10px; }
  @media(max-width:1100px){ .grid{ grid-template-columns:1fr; } }
  label{ font-size:12px;font-weight:800;color:#64748b; }
  .hint{ font-size:12px;color:#64748b;margin-top:6px; }
  table.tbl{ width:100%; border-collapse:separate;border-spacing:0; }
  table.tbl th{ background:#f1f5f9;border-bottom:1px solid #cbd5e1;padding:10px;text-align:center;font-weight:900; }
  table.tbl td{ border-bottom:1px solid #e5e7eb;padding:10px;vertical-align:middle; }
  .num{ text-align:right;font-variant-numeric:tabular-nums; }
</style>

<div class="mb-2">
  <div class="title">BARITORI – Schedule</div>
  <div class="sub">Buat schedule berdasarkan stock sebelumnya dan alokasikan ke Vendor.</div>
</div>
<div class="d-flex justify-content-end mb-3 gap-2 d-print-none">
    <button type="button" class="btn btn-outline-success btn-sm fw-bold" onclick="exportGenericExcel()">
        <i class="bi bi-file-earmark-excel"></i> Export Excel
    </button>
    <button type="button" class="btn btn-outline-danger btn-sm fw-bold" onclick="window.print()">
        <i class="bi bi-printer"></i> Print / PDF
    </button>
</div>

<form method="get" class="d-flex gap-2 align-items-end mb-3">
  <div>
    <label>Tanggal</label>
    <input type="date" name="date" value="<?= esc($date) ?>" class="form-control form-control-sm">
  </div>
  <button class="btn btn-outline-primary btn-sm">
    <i class="bi bi-funnel"></i> Filter
  </button>
</form>

<?php if (!empty($errorMsg)): ?>
  <div class="alert alert-danger py-2"><?= esc($errorMsg) ?></div>
<?php endif; ?>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success py-2"><?= session()->getFlashdata('success') ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger py-2"><?= session()->getFlashdata('error') ?></div>
<?php endif; ?>

<div class="wrapx mb-3">
  <form method="post" action="<?= site_url('baritori/schedule/store') ?>">
    <?= csrf_field() ?>

    <div class="grid">
      <div>
        <label>Date</label>
        <input class="form-control form-control-sm" name="date" value="<?= esc($date) ?>" readonly>
      </div>

      <div>
        <label>Shift (dari DC)</label>
        <select name="shift_id" class="form-select form-select-sm" required>
          <option value="">-- shift --</option>
          <?php foreach ($shifts as $s): ?>
            <option value="<?= $s['id'] ?>"><?= esc($s['shift_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label>Vendor</label>
        <select name="vendor_id" class="form-select form-select-sm" required>
          <option value="">-- vendor --</option>
          <?php foreach ($vendors as $v): ?>
            <option value="<?= $v['id'] ?>"><?= esc($v['vendor_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label>Product (Hanya yg ada stock)</label>
        <select name="product_id" id="productSelect" class="form-select form-select-sm" required <?= empty($productsAvail) ? 'disabled' : '' ?>>
          <option value="">-- pilih product --</option>
          <?php foreach ($productsAvail as $p): ?>
            <?php
              $pid  = (int)$p['id'];
              $av   = (int)($availableMap[$pid]['available'] ?? 0);
              $prev = (int)($availableMap[$pid]['prev_process_id'] ?? 0);
              $prevName = $prev ? ($processMap[$prev] ?? ("PROCESS ".$prev)) : '-';
              $label = $p['part_no'].' - '.$p['part_name']." (Avail: ".number_format($av).")";
            ?>
            <option value="<?= $pid ?>" data-av="<?= $av ?>" data-prev="<?= esc($prevName) ?>">
              <?= esc($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="hint" id="flowHint">Pilih product untuk melihat stock</div>
      </div>

      <div>
        <label>Qty (Target)</label>
        <input type="number" name="target_shift" id="qtyInput" class="form-control form-control-sm" min="1" required <?= empty($productsAvail) ? 'disabled' : '' ?>>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-3">
      <button class="btn btn-primary btn-sm" <?= empty($productsAvail) ? 'disabled' : '' ?>>
        <i class="bi bi-plus-circle"></i> Simpan Schedule
      </button>
    </div>
  </form>
</div>

<div class="wrapx">
  <table class="tbl">
    <thead>
      <tr>
        <th>Date</th>
        <th>Shift</th>
        <th>Vendor</th>
        <th>Part</th>
        <th>Target</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($schedules)): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">Belum ada schedule untuk tanggal ini.</td></tr>
      <?php else: ?>
        <?php foreach ($schedules as $r): ?>
          <tr>
            <td class="text-center"><?= esc(date('d/m', strtotime($r['schedule_date']))) ?></td>
            <td class="text-center"><?= esc($r['shift_name'] ?? '-') ?></td>
            <td><?= esc($r['vendor_name'] ?? 'Vendor tidak diset') ?></td>
            <td>
              <div style="font-weight:900"><?= esc($r['part_no'] ?? '') ?></div>
              <div style="color:#64748b;font-weight:700;font-size:12px;margin-top:2px"><?= esc($r['part_name'] ?? '') ?></div>
            </td>
            <td class="num" style="font-weight:900"><?= number_format((int)($r['target_per_shift'] ?? 0)) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
(function(){
  const productSelect = document.getElementById('productSelect');
  const qtyInput = document.getElementById('qtyInput');
  const flowHint = document.getElementById('flowHint');

  function refreshHint(){
    if (!productSelect) return;
    const opt = productSelect.options[productSelect.selectedIndex];
    if (!opt || !opt.value){
      if (flowHint) flowHint.textContent = "Pilih product untuk melihat stock.";
      if (qtyInput) qtyInput.removeAttribute('max');
      return;
    }
    const av = parseInt(opt.dataset.av || "0", 10);
    const prev = opt.dataset.prev || "-";
    if (flowHint) flowHint.textContent = `Prev Process: ${prev} | Available: ${av}`;
    if (qtyInput){
      if (av > 0) qtyInput.setAttribute('max', String(av));
      else qtyInput.removeAttribute('max');
    }
  }

  if (productSelect) productSelect.addEventListener('change', refreshHint);
  refreshHint();
})();
</script>
<?= $this->endSection() ?>