<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-2 fw-bold">SHOT BLASTING</h4>
<h5 class="mb-4 text-muted">RECEIVING (Hasil Proses Internal)</h5>

<?php if (!empty($errorMsg)): ?>
  <div class="alert alert-danger py-2"><?= esc($errorMsg) ?></div>
<?php endif; ?>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<form method="post" action="<?= site_url('/shot-blasting/receiving/store') ?>">
<?= csrf_field() ?>

<table class="table table-bordered table-sm align-middle text-center">
  <thead class="table-secondary">
    <tr>
      <th style="width:60px">No</th>
      <th>Shift</th>
      <th>Part No</th>
      <th>Part Name</th>
      <th>Qty Processed</th>
      <th>Qty Finished</th>
      <th>Outstanding</th>
      <th style="width:130px">Status</th>
      <th style="width:150px">Qty Receive</th>
    </tr>
  </thead>
  <tbody>

  <?php if (empty($deliveries)): ?>
    <tr>
      <td colspan="9" class="text-muted">Tidak ada data proses yang berjalan / outstanding.</td>
    </tr>
  <?php else: ?>
    <?php foreach ($deliveries as $i => $d): ?>
      <?php
        $no = $i + 1;
        $outstanding = (int)($d['outstanding'] ?? 0);
        $status = (string)($d['status'] ?? 'IN PROCESS');
        $isCompleted = ($status === 'COMPLETED');
      ?>
      <tr>
        <td><?= $no ?></td>
        <td><?= esc($d['shift_name'] ?? '-') ?></td>
        <td><?= esc($d['part_no'] ?? '') ?></td>
        <td class="text-start"><?= esc($d['part_name'] ?? '') ?></td>
        <td><?= number_format((int)($d['qty_out'] ?? 0)) ?></td>
        <td><?= number_format((int)($d['qty_in'] ?? 0)) ?></td>
        <td class="fw-bold"><?= number_format($outstanding) ?></td>

        <td>
          <?php if ($isCompleted): ?>
            <span class="badge bg-success">COMPLETED</span>
          <?php else: ?>
            <span class="badge bg-warning text-dark">IN PROCESS</span>
          <?php endif; ?>
        </td>

        <td>
          <input
            type="number"
            name="items[<?= $i ?>][qty]"
            class="form-control form-control-sm text-center qty-input"
            min="0"
            max="<?= $outstanding ?>"
            data-outstanding="<?= $outstanding ?>"
            placeholder="<?= $isCompleted ? '0' : '0 - '.$outstanding ?>"
            <?= $isCompleted ? 'disabled' : '' ?>
          >

          <input type="hidden" name="items[<?= $i ?>][product_id]" value="<?= (int)($d['product_id'] ?? 0) ?>">
          <input type="hidden" name="items[<?= $i ?>][shift_id]" value="<?= (int)($d['shift_id'] ?? 0) ?>">
        </td>
      </tr>
    <?php endforeach; ?>
  <?php endif; ?>

  </tbody>
</table>

<?php if (!empty($deliveries)): ?>
<button class="btn btn-primary btn-sm mt-3">
  <i class="bi bi-box-arrow-in-down"></i>
  Simpan Receiving Shot Blasting
</button>
<?php endif; ?>

</form>

<script>
document.querySelectorAll('.qty-input').forEach(inp => {
  inp.addEventListener('input', () => {
    const max = parseInt(inp.dataset.outstanding || '0', 10);
    let v = parseInt(inp.value || '0', 10);
    if (isNaN(v)) v = 0;
    if (v > max) inp.value = max;
    if (v < 0) inp.value = 0;
  });
});
</script>

<?= $this->endSection() ?>