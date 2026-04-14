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

<form method="get" class="d-flex gap-2 align-items-end mb-4" style="max-width: 400px;">
  <div class="flex-grow-1">
    <label class="fw-bold" style="font-size: 14px;">Tanggal Schedule</label>
    <input type="date" name="date" class="form-control" value="<?= esc($date) ?>">
  </div>
  <button type="submit" class="btn btn-outline-primary">
    <i class="bi bi-search"></i> Tampilkan
  </button>
</form>

<form method="post" action="<?= site_url('/shot-blasting/delivery/store') ?>">
<?= csrf_field() ?>

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
// Validasi agar QTY Actual tidak melebihi Ready Stock / Sched Target
document.querySelectorAll('.qty-input').forEach(inp => {
  inp.addEventListener('input', () => {
    const maxVal = parseInt(inp.dataset.max || '0', 10);
    let v = parseInt(inp.value || '0', 10);
    if (isNaN(v)) v = 0;
    if (v > maxVal) inp.value = maxVal;
    if (v < 0) inp.value = 0;
  });
});
</script>

<?= $this->endSection() ?>