<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-1 fw-bold">BARITORI – Schedule (Daily Schedules)</h4>
<div class="text-muted mb-3">Product hanya muncul jika stock proses sebelumnya (flow) tersedia.</div>

<?php if (!empty($errorMsg)): ?>
  <div class="alert alert-danger py-2"><?= esc($errorMsg) ?></div>
<?php endif; ?>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success py-2"><?= session()->getFlashdata('success') ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger py-2"><?= session()->getFlashdata('error') ?></div>
<?php endif; ?>

<form method="get" class="d-flex gap-2 align-items-end mb-3">
  <div>
    <label class="form-label small fw-bold">Tanggal</label>
    <input type="date" name="date" value="<?= esc($date) ?>" class="form-control form-control-sm">
  </div>
  <button class="btn btn-outline-primary btn-sm">
    <i class="bi bi-funnel"></i> Filter
  </button>
</form>

<?php if (empty($productsAvail)): ?>
  <div class="alert alert-warning">
    <div class="fw-bold">Tidak ada product yang bisa dijadwalkan.</div>
    <div>Stock pada proses sebelumnya (flow → Baritori) kosong untuk tanggal ini.</div>
  </div>
<?php endif; ?>

<div class="card p-3 mb-3">
  <form method="post" action="/baritori/schedule/store">
    <?= csrf_field() ?>

    <div class="row g-2">
      <div class="col-md-2">
        <label class="form-label small fw-bold">Date</label>
        <input class="form-control form-control-sm" name="date" value="<?= esc($date) ?>" readonly>
      </div>

      <div class="col-md-3">
        <label class="form-label small fw-bold">Shift (diambil dari DC)</label>
        <select name="shift_id" class="form-select form-select-sm" required>
          <option value="">-- pilih shift --</option>
          <?php foreach ($shifts as $s): ?>
            <option value="<?= $s['id'] ?>"><?= esc($s['shift_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-5">
        <label class="form-label small fw-bold">Product (stock prev > 0)</label>
        <select name="product_id" id="productSelect" class="form-select form-select-sm" <?= empty($productsAvail) ? 'disabled' : '' ?> required>
          <option value="">-- pilih product --</option>
          <?php foreach ($productsAvail as $p): ?>
            <?php
              $pid = (int)$p['id'];
              $av  = (int)($availableMap[$pid]['available'] ?? 0);
              $prevId = (int)($availableMap[$pid]['prev_process_id'] ?? 0);
              $nextId = (int)($availableMap[$pid]['next_process_id'] ?? 0);

              $prevName = $prevId ? ($processMap[$prevId] ?? ("PROCESS ".$prevId)) : '-';
              $nextName = $nextId ? ($processMap[$nextId] ?? ("PROCESS ".$nextId)) : '-';

              $label = $p['part_no'].' - '.$p['part_name'].' (Avail: '.number_format($av).', Prev: '.$prevName.', Next: '.$nextName.')';
            ?>
            <option value="<?= $pid ?>"
                    data-av="<?= $av ?>"
                    data-prev="<?= esc($prevName) ?>"
                    data-next="<?= esc($nextName) ?>">
              <?= esc($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="small text-muted mt-1" id="flowHint">Pilih product untuk melihat available & flow.</div>
      </div>

      <div class="col-md-2">
        <label class="form-label small fw-bold">Qty (Target/Shift)</label>
        <input type="number" name="target_shift" id="qtyInput" class="form-control form-control-sm"
               min="1" <?= empty($productsAvail) ? 'disabled' : '' ?> required>
        <div class="small text-muted mt-1">Tidak boleh melebihi Available.</div>
      </div>

      <div class="col-md-2">
        <label class="form-label small fw-bold">Target/Hour (opsional)</label>
        <input type="number" name="target_hour" class="form-control form-control-sm" <?= empty($productsAvail) ? 'disabled' : '' ?>>
      </div>

      <div class="col-md-4 d-flex align-items-end">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" value="1" id="sendNext" name="send_next" <?= empty($productsAvail) ? 'disabled' : '' ?>>
          <label class="form-check-label fw-bold" for="sendNext">
            Send to Next Process (sesuai flow)
          </label>
        </div>
      </div>

      <div class="col-md-6 d-flex align-items-end justify-content-end gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnMax" <?= empty($productsAvail) ? 'disabled' : '' ?>>
          Pakai Max Available
        </button>
        <button class="btn btn-primary btn-sm" <?= empty($productsAvail) ? 'disabled' : '' ?>>
          <i class="bi bi-save"></i> Simpan Schedule
        </button>
      </div>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-header fw-bold">Schedule Baritori (dari Daily Schedules)</div>
  <div class="table-responsive">
    <table class="table table-bordered table-sm align-middle mb-0">
      <thead class="table-light">
        <tr class="text-center">
          <th style="width:90px">Tanggal</th>
          <th style="width:220px">Shift</th>
          <th>Part</th>
          <th style="width:140px">Available Prev</th>
          <th style="width:120px">Target</th>
          <th style="width:220px">Next Process</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($schedules)): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada schedule untuk tanggal ini.</td></tr>
      <?php else: ?>
        <?php foreach ($schedules as $r): ?>
          <?php
            $pid = (int)($r['product_id'] ?? 0);
            $av  = (int)($availableMap[$pid]['available'] ?? 0);
            $target = (int)($r['target_per_shift'] ?? 0);

            $nextId = (int)($availableMap[$pid]['next_process_id'] ?? 0);
            $nextName = $nextId ? ($processMap[$nextId] ?? ("PROCESS ".$nextId)) : '-';
          ?>
          <tr>
            <td class="text-center"><?= esc(date('d/m', strtotime($r['schedule_date']))) ?></td>
            <td><?= esc($r['shift_name'] ?? '-') ?></td>
            <td>
              <div class="fw-bold"><?= esc($r['part_no'] ?? '') ?></div>
              <div class="text-muted small"><?= esc($r['part_name'] ?? '') ?></div>
            </td>
            <td class="text-end"><?= number_format($av) ?></td>
            <td class="text-end fw-bold"><?= number_format($target) ?></td>
            <td><?= esc($nextName) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function(){
  const productSelect = document.getElementById('productSelect');
  const qtyInput = document.getElementById('qtyInput');
  const flowHint = document.getElementById('flowHint');
  const btnMax = document.getElementById('btnMax');

  if (!productSelect || !qtyInput) return;

  function refreshHint(){
    const opt = productSelect.options[productSelect.selectedIndex];
    if (!opt || !opt.value){
      flowHint.textContent = "Pilih product untuk melihat available & flow.";
      qtyInput.removeAttribute('max');
      return;
    }
    const av = parseInt(opt.dataset.av || "0", 10);
    const prev = opt.dataset.prev || "-";
    const next = opt.dataset.next || "-";
    flowHint.textContent = `Prev: ${prev} → Baritori → Next: ${next} | Available: ${av}`;
    if (av > 0) qtyInput.setAttribute('max', String(av));
    else qtyInput.removeAttribute('max');
  }

  productSelect.addEventListener('change', refreshHint);

  btnMax && btnMax.addEventListener('click', function(){
    const opt = productSelect.options[productSelect.selectedIndex];
    if (!opt || !opt.value) return;
    const av = parseInt(opt.dataset.av || "0", 10);
    if (av > 0) qtyInput.value = av;
  });

  refreshHint();
})();
</script>

<?= $this->endSection() ?>
