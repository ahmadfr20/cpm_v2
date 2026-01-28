<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">WIP SUMMARY</h4>

<form method="get" class="row g-2 mb-3 align-items-end" style="max-width:500px">
  <div class="col-md-6">
    <label class="form-label fw-bold">Tanggal</label>
    <input type="date" name="date" value="<?= esc($date) ?>" class="form-control">
  </div>
  <div class="col-md-6">
    <button class="btn btn-primary w-100">Filter</button>
  </div>
</form>

<?php if (empty($summary)): ?>
  <div class="alert alert-warning">Tidak ada WIP untuk tanggal ini.</div>
<?php else: ?>

  <?php foreach ($summary as $shift): ?>
    <hr>
    <h5 class="mt-3 mb-2"><?= esc($shift['shift_name']) ?></h5>

    <div class="table-responsive">
      <table class="table table-bordered table-sm align-middle text-center">
        <thead class="table-secondary">
          <tr>
            <th style="width:220px" class="text-start">Section</th>
            <th style="width:120px">In</th>
            <th style="width:120px">Out</th>
            <th style="width:120px">Stock</th>
            <th style="width:260px">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($shift['sections'] ?? []) as $secName => $v): ?>
            <?php
              $in    = (int)($v['in'] ?? 0);
              $out   = (int)($v['out'] ?? 0);
              $stock = (int)($v['stock'] ?? 0);

              $st = $v['status'] ?? [];
              $w  = (int)($st['WAITING'] ?? 0);
              $sc = (int)($st['SCHEDULED'] ?? 0);
              $d  = (int)($st['DONE'] ?? 0);
              $o  = (int)($st['OTHER'] ?? 0);
            ?>
            <tr>
              <td class="text-start fw-bold"><?= esc($secName) ?></td>
              <td><?= number_format($in) ?></td>
              <td><?= number_format($out) ?></td>
              <td class="fw-bold"><?= number_format($stock) ?></td>
              <td class="text-start">
                <span class="badge bg-warning text-dark me-1">WAITING: <?= number_format($w) ?></span>
                <span class="badge bg-info text-dark me-1">SCHEDULED: <?= number_format($sc) ?></span>
                <span class="badge bg-success me-1">DONE: <?= number_format($d) ?></span>
                <?php if ($o > 0): ?>
                  <span class="badge bg-secondary">OTHER: <?= number_format($o) ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endforeach; ?>

<?php endif; ?>

<?= $this->endSection() ?>
