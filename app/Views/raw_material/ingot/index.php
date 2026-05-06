<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="mb-2 fw-bold">RAW MATERIAL</h4>
    <h5 class="mb-0 text-muted">INGOT RECEIVING</h5>
  </div>
  <form method="get" action="<?= site_url('/raw-material/ingot') ?>" class="d-flex align-items-center gap-2">
    <label for="date" class="fw-bold mb-0">Tanggal:</label>
    <input type="date" name="date" id="date" class="form-control form-control-sm" value="<?= esc($date) ?>" onchange="this.form.submit()" style="width: 150px;">
  </form>
</div>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<form method="post" action="<?= site_url('/raw-material/ingot/store') ?>">
<?= csrf_field() ?>
<input type="hidden" name="date" value="<?= esc($date) ?>">

<div class="card shadow-sm border-0">
  <div class="card-body p-0">
    <table class="table table-bordered table-striped table-hover mb-0 align-middle text-center">
      <thead class="table-secondary">
        <tr>
          <th style="width:100px">No</th>
          <th>Shift Name</th>
          <th style="width:300px">Qty Ingot Diterima (Pcs/Kg/Bundle)</th>
        </tr>
      </thead>
      <tbody>

      <?php if (empty($shifts)): ?>
        <tr>
          <td colspan="3" class="text-muted py-4">Tidak ada data shift aktif.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($shifts as $i => $s): ?>
          <?php
            $r = $receives[$s['id']] ?? [];
            $qty = $r['qty'] ?? 0;
            $unit = $r['unit'] ?? 'Kg';
          ?>
          <tr>
            <td class="fw-bold"><?= $i + 1 ?></td>
            <td class="fs-5"><?= esc($s['shift_name'] ?? '-') ?></td>
            <td>
              <div class="input-group">
                <input
                  type="number"
                  name="items[<?= $s['id'] ?>][qty]"
                  class="form-control form-control-lg text-center"
                  min="0"
                  value="<?= $qty > 0 ? $qty : '' ?>"
                  placeholder="0"
                >
                <select name="items[<?= $s['id'] ?>][unit]" class="form-select form-select-lg" style="max-width: 150px;">
                  <option value="Kg" <?= $unit === 'Kg' ? 'selected' : '' ?>>Kg</option>
                  <option value="Pcs" <?= $unit === 'Pcs' ? 'selected' : '' ?>>Pcs</option>
                  <option value="Bundle" <?= $unit === 'Bundle' ? 'selected' : '' ?>>Bundle</option>
                </select>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>

      </tbody>
    </table>
  </div>
</div>

<div class="mt-4 text-end">
  <button type="submit" class="btn btn-primary btn-lg">
    <i class="bi bi-save me-2"></i> Simpan Data Penerimaan
  </button>
</div>

</form>

<h5 class="mt-5 mb-3 fw-bold border-bottom pb-2">History Penerimaan Ingot</h5>
<div class="card shadow-sm border-0 mb-4">
  <div class="card-body p-0">
    <table class="table table-bordered table-striped mb-0 align-middle text-center">
      <thead class="table-light">
        <tr>
          <th>Tanggal Receive</th>
          <th>Shift</th>
          <th>Qty</th>
          <th>Unit</th>
          <th>Waktu Input</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($history)): ?>
          <tr>
            <td colspan="5" class="text-muted py-4">Belum ada history penerimaan.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($history as $h): ?>
            <tr>
              <td><?= esc($h['receive_date']) ?></td>
              <td class="fw-bold"><?= esc($h['shift_name'] ?? '-') ?></td>
              <td class="fw-bold text-primary"><?= number_format((int)$h['qty_ingot']) ?></td>
              <td><?= esc($h['unit'] ?? 'Kg') ?></td>
              <td class="text-muted small"><?= date('d M Y H:i', strtotime($h['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
