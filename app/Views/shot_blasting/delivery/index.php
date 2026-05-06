<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-2 fw-bold">SHOT BLASTING</h4>
<h5 class="mb-4 text-muted">TRANSFER/PROCESS EXECUTION (Berdasarkan Schedule)</h5>

<?php if (!empty($errorMsg)): ?>
  <div class="alert alert-danger"><?= esc($errorMsg) ?></div>
<?php endif; ?>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<div class="d-flex flex-column flex-md-row gap-3 align-items-start align-items-md-end mb-4">
  <form method="get" class="d-flex gap-2 align-items-end" style="max-width: 400px;">
    <div class="flex-grow-1">
      <label class="fw-bold" style="font-size: 14px;">Tanggal Schedule</label>
      <input type="date" name="date" class="form-control" value="<?= esc($date) ?>">
    </div>
    <button type="submit" class="btn btn-outline-primary">
      <i class="bi bi-search"></i> Tampilkan
    </button>
  </form>

  <?php if (isset($isAdmin) && $isAdmin): ?>
  <div class="ms-md-auto">
    <div class="form-check form-switch d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 shadow-sm"
         style="background: linear-gradient(135deg, #fff5f5 0%, #ffe3e3 100%); border: 1px solid #fecaca;">
      <input class="form-check-input bg-danger border-danger" type="checkbox" id="bypassStockToggle" 
             style="cursor: pointer; width: 2.5em; height: 1.25em;">
      <label class="form-check-label fw-bold text-danger ms-1" for="bypassStockToggle" style="cursor: pointer; font-size: 13px;">
        <i class="bi bi-unlock-fill"></i> Admin: Bypass Validasi Stock
      </label>
    </div>
  </div>
  <?php endif; ?>
</div>

<form method="post" action="<?= site_url('/shot-blasting/delivery/store') ?>" id="deliveryForm">
<?= csrf_field() ?>
<input type="hidden" name="bypass_stock" id="bypassStockInput" value="0">

<table class="table table-bordered table-sm align-middle text-center table-hover">
  <thead class="table-secondary">
    <tr>
      <th style="width:40px">No</th>
      <th style="width:120px">Shift</th>
      <th>Part No & Name</th>
      <th style="width:140px">Target Sched</th>
      <th style="width:140px">Ready in SB</th>
      <th style="width:160px">Actual Process</th>
    </tr>
  </thead>
  <tbody>
  <?php if (empty($schedules)): ?>
    <tr>
      <td colspan="6" class="text-muted py-4">
        Tidak ada jadwal proses (In-House) untuk tanggal ini. <br>
        Silakan buat Schedule Shot Blasting terlebih dahulu.
      </td>
    </tr>
  <?php else: ?>
    <?php foreach ($schedules as $i => $row): ?>
      <?php 
        $pid = (int)$row['product_id'];
        $availSb = (int)($availableMap[$pid] ?? 0);
        $schedQty = (int)$row['scheduled_qty'];
        // Limit max input ke nilai terkecil antara Target Schedule dan Stock di SB
        $maxProcess = min($schedQty, $availSb);
      ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><?= esc($row['shift_name'] ?? '-') ?></td>
        <td class="text-start">
          <strong><?= esc($row['part_no']) ?></strong><br>
          <small class="text-muted"><?= esc($row['part_name']) ?></small>
        </td>
        <td class="fw-bold text-primary"><?= number_format($schedQty) ?></td>
        <td class="<?= $availSb > 0 ? 'fw-bold text-success' : 'text-danger fw-bold' ?>">
          <?= number_format($availSb) ?>
        </td>
        <td>
          <input type="hidden" name="items[<?= $i ?>][schedule_item_id]" value="<?= $row['schedule_item_id'] ?>">
          <input type="number" name="items[<?= $i ?>][qty]" class="form-control form-control-sm text-center qty-input" 
                 min="0" max="<?= max(0, $maxProcess) ?>" data-max="<?= max(0, $maxProcess) ?>" 
                 data-sched="<?= $schedQty ?>" data-available="<?= $availSb ?>"
                 placeholder="0 - <?= max(0, $maxProcess) ?>" <?= $maxProcess <= 0 ? 'readonly' : '' ?>>
        </td>
      </tr>
    <?php endforeach ?>
  <?php endif; ?>
  </tbody>
</table>

<?php if (!empty($schedules)): ?>
  <button class="btn btn-success btn-sm mt-3">
    <i class="bi bi-gear-fill"></i> Simpan Actual Process
  </button>
<?php endif; ?>

</form>

<script>
// Bypass Stock Toggle (Admin Only)
const bypassToggle = document.getElementById('bypassStockToggle');
const bypassInput = document.getElementById('bypassStockInput');

function recalcMaxInputs() {
  const isBypass = bypassToggle && bypassToggle.checked;
  document.querySelectorAll('.qty-input').forEach(inp => {
    const schedQty  = parseInt(inp.dataset.sched || '0', 10);
    const available = parseInt(inp.dataset.available || '0', 10);

    if (isBypass) {
      // Bypass mode: max = Target Sched (tanpa batasan stock)
      inp.max = schedQty;
      inp.dataset.max = String(schedQty);
      inp.placeholder = '0 - ' + schedQty;
      inp.readOnly = (schedQty <= 0);
    } else {
      // Normal mode: max = min(sched, available)
      const maxVal = Math.min(schedQty, available);
      inp.max = maxVal;
      inp.dataset.max = String(maxVal);
      inp.placeholder = '0 - ' + maxVal;
      inp.readOnly = (maxVal <= 0);

      // Clamp current value
      let v = parseInt(inp.value || '0', 10);
      if (v > maxVal) inp.value = maxVal;
    }
  });
}

if (bypassToggle) {
  bypassToggle.addEventListener('change', function() {
    bypassInput.value = this.checked ? '1' : '0';
    recalcMaxInputs();
  });
}

// Validasi agar QTY Actual tidak melebihi max
document.querySelectorAll('.qty-input').forEach(inp => {
  inp.addEventListener('input', () => {
    const maxVal = parseInt(inp.dataset.max || '0', 10);
    let v = parseInt(inp.value || '0', 10);
    if (isNaN(v)) v = 0;
    if (v > maxVal) inp.value = maxVal;
    if (v < 0) inp.value = 0;
  });
});

// Submit validation
const deliveryForm = document.getElementById('deliveryForm');
if (deliveryForm) {
  deliveryForm.addEventListener('submit', function(e) {
    const isBypass = bypassToggle && bypassToggle.checked;
    if (!isBypass) {
      const inputs = document.querySelectorAll('.qty-input');
      for (const inp of inputs) {
        const v = parseInt(inp.value || '0', 10);
        const available = parseInt(inp.dataset.available || '0', 10);
        if (v > 0 && v > available) {
          e.preventDefault();
          alert('Qty proses melebihi Ready Stock. Tidak dapat melanjutkan tanpa Bypass Stock.');
          return false;
        }
      }
    }
  });
}
</script>

<?= $this->endSection() ?>