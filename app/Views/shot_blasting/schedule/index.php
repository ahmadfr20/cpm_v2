<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
  .wrapx{ background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px; }
  .title{ font-weight:900;font-size:18px; }
  .sub{ color:#64748b;font-weight:600;margin-top:2px; }
  .grid{ display:grid;grid-template-columns: 160px 260px 1fr 200px;gap:10px; }
  @media(max-width:1100px){ .grid{ grid-template-columns:1fr; } }
  label{ font-size:12px;font-weight:800;color:#64748b; }
  .hint{ font-size:12px;color:#64748b;margin-top:6px; }
  table.tbl{ width:100%; border-collapse:separate;border-spacing:0; }
  table.tbl th{ background:#f1f5f9;border-bottom:1px solid #cbd5e1;padding:10px;text-align:center;font-weight:900; }
  table.tbl td{ border-bottom:1px solid #e5e7eb;padding:10px;vertical-align:middle; }
  .num{ text-align:right;font-variant-numeric:tabular-nums; }
</style>

<div class="mb-2">
  <div class="title">SHOT BLASTING – Schedule (Based on Prev Stock)</div>
  <div class="sub">Pilih product hanya yang punya stock dari process sebelumnya (flow). Opsi: Send ke Next Process.</div>
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

<?php if (empty($productsAvail)): ?>
  <div class="alert alert-warning">
    <b>Tidak ada product yang bisa dijadwalkan.</b><br>
    Stock pada process sebelumnya (flow → Shot Blasting) masih kosong untuk tanggal ini.
  </div>
<?php endif; ?>

<div class="wrapx mb-3">
  <form method="post" action="/shot-blasting/schedule/store">
    <?= csrf_field() ?>

    <div class="grid">
      <div>
        <label>Date</label>
        <input class="form-control form-control-sm" name="date" value="<?= esc($date) ?>" readonly>
      </div>

      <div>
        <label>Shift (diambil dari DC)</label>
        <select name="shift_id" class="form-select form-select-sm" required>
          <option value="">-- pilih shift --</option>
          <?php foreach ($shifts as $s): ?>
            <option value="<?= $s['id'] ?>"><?= esc($s['shift_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label>Product (Hanya yang stock prev > 0)</label>
        <select name="product_id" id="productSelect" class="form-select form-select-sm" required <?= empty($productsAvail) ? 'disabled' : '' ?>>
          <option value="">-- pilih product --</option>
          <?php foreach ($productsAvail as $p): ?>
            <?php
              $pid  = (int)$p['id'];
              $av   = (int)($availableMap[$pid]['available'] ?? 0);
              $prev = (int)($availableMap[$pid]['prev_process_id'] ?? 0);
              $next = (int)($availableMap[$pid]['next_process_id'] ?? 0);

              $prevName = $prev ? ($processMap[$prev] ?? ("PROCESS ".$prev)) : '-';
              $nextName = $next ? ($processMap[$next] ?? ("PROCESS ".$next)) : '-';

              $label = $p['part_no'].' - '.$p['part_name']." (Avail: ".number_format($av).", Prev: ".$prevName.", Next: ".$nextName.")";
            ?>
            <option value="<?= $pid ?>"
                    data-av="<?= $av ?>"
                    data-prev="<?= esc($prevName) ?>"
                    data-next="<?= esc($nextName) ?>">
              <?= esc($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="hint" id="flowHint">Pilih product untuk melihat available & flow.</div>
      </div>

      <div>
        <label>Qty (Target / Shift)</label>
        <input type="number" name="target_shift" id="qtyInput"
               class="form-control form-control-sm"
               min="1" required <?= empty($productsAvail) ? 'disabled' : '' ?>>
        <div class="hint">Tidak boleh melebihi Available.</div>
      </div>
    </div>

    <div class="row g-2 mt-2">
      <div class="col-md-3">
        <label>Target / Hour (optional)</label>
        <input name="target_hour" class="form-control form-control-sm" <?= empty($productsAvail) ? 'disabled' : '' ?>>
      </div>

      <div class="col-md-5 d-flex align-items-end">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" value="1" id="sendNext" name="send_next" <?= empty($productsAvail) ? 'disabled' : '' ?>>
          <label class="form-check-label" for="sendNext" style="font-weight:800;">
            Send to Next Process (sesuai flow)
          </label>
        </div>
      </div>

      <div class="col-md-4 d-flex align-items-end justify-content-end gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnMax" <?= empty($productsAvail) ? 'disabled' : '' ?>>
          Pakai Max Available
        </button>
        <button class="btn btn-primary btn-sm" <?= empty($productsAvail) ? 'disabled' : '' ?>>
          <i class="bi bi-plus-circle"></i> Simpan Schedule
        </button>
      </div>
    </div>
  </form>
</div>

<div class="wrapx">
  <table class="tbl">
    <thead>
      <tr>
        <th style="width:120px">Date</th>
        <th style="width:220px">Shift</th>
        <th>Part</th>
        <th style="width:150px">Available Prev</th>
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
            $pid  = (int)($r['product_id'] ?? 0);
            $av   = (int)($availableMap[$pid]['available'] ?? 0);
            $nextId   = (int)($availableMap[$pid]['next_process_id'] ?? 0);
            $nextName = $nextId ? ($processMap[$nextId] ?? ("PROCESS ".$nextId)) : '-';
          ?>
          <tr>
            <td class="text-center"><?= esc(date('d/m', strtotime($r['schedule_date']))) ?></td>
            <td><?= esc($r['shift_name'] ?? '-') ?></td>
            <td>
              <div style="font-weight:900"><?= esc($r['part_no'] ?? '') ?></div>
              <div style="color:#64748b;font-weight:700;font-size:12px;margin-top:2px">
                <?= esc($r['part_name'] ?? '') ?>
              </div>
            </td>
            <td class="num"><?= number_format($av) ?></td>
            <td class="num" style="font-weight:900"><?= number_format((int)($r['target_per_shift'] ?? 0)) ?></td>
            <td><?= esc($nextName) ?></td>
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
  const btnMax = document.getElementById('btnMax');

  function refreshHint(){
    if (!productSelect) return;
    const opt = productSelect.options[productSelect.selectedIndex];
    if (!opt || !opt.value){
      if (flowHint) flowHint.textContent = "Pilih product untuk melihat available & flow.";
      if (qtyInput) qtyInput.removeAttribute('max');
      return;
    }
    const av = parseInt(opt.dataset.av || "0", 10);
    const prev = opt.dataset.prev || "-";
    const next = opt.dataset.next || "-";
    if (flowHint) flowHint.textContent = `Prev: ${prev} → Shot Blasting → Next: ${next} | Available: ${av}`;
    if (qtyInput){
      if (av > 0) qtyInput.setAttribute('max', String(av));
      else qtyInput.removeAttribute('max');
    }
  }

  if (productSelect) productSelect.addEventListener('change', refreshHint);

  if (btnMax) btnMax.addEventListener('click', function(){
    if (!productSelect || !qtyInput) return;
    const opt = productSelect.options[productSelect.selectedIndex];
    if (!opt || !opt.value) return;
    const av = parseInt(opt.dataset.av || "0", 10);
    if (av > 0) qtyInput.value = av;
  });

  refreshHint();
})();
</script>

<?= $this->endSection() ?>
