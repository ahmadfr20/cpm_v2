<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">DIE CASTING – DAILY PRODUCTION SCHEDULE</h4>
  <a href="<?= base_url('die-casting/daily-schedule/inventory?date='.$date) ?>" class="btn btn-outline-info fw-bold btn-sm">
    <i class="bi bi-box-seam me-1"></i> Lihat Stock Die Casting
  </a>
</div>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<form method="get" class="mb-3">
  <input type="date" name="date" value="<?= esc($date) ?>"
         class="form-control" style="max-width:240px" onchange="this.form.submit()">
</form>

<form method="post" action="<?= site_url('/die-casting/daily-schedule/store') ?>">
<?= csrf_field() ?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
  .select2-container .select2-selection--single{ height: 31px; }
  .select2-container--default .select2-selection--single .select2-selection__rendered{ line-height: 31px; font-size: 12px; }
  .select2-container--default .select2-selection--single .select2-selection__arrow{ height: 31px; }
</style>

<?php foreach ($shifts as $shift): ?>
  <h5 class="mt-4 d-flex align-items-center justify-content-between">
    <span>
      <?= esc($shift['shift_name']) ?>
      <small class="text-muted">(Total <?= (int)$shift['total_minute'] ?> menit)</small>
    </span>
    <span class="badge bg-secondary">
      Total Ascas: <span class="shift-ascas-total" data-shift="<?= (int)$shift['id'] ?>">0.00</span> kg
      &nbsp;|&nbsp;
      Total Runner: <span class="shift-runner-total" data-shift="<?= (int)$shift['id'] ?>">0.00</span> kg
    </span>
  </h5>

  <div class="table-responsive">
    <table class="table table-bordered table-sm text-center align-middle">
      <thead class="table-secondary">
        <tr>
          <th>Mesin</th>
          <th>Total Menit</th>
          <th style="min-width:320px">Part</th>
          <th>Plan</th>
          <th>Ascas (kg)</th>
          <th>A (Actual)</th>
          <th>Runner (kg)</th>
          <th>NG</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>

      <?php foreach ($machines as $m):
        $p   = $map[$shift['id']][$m['id']] ?? null;
        $key = $shift['id'].'_'.$m['id'];
      ?>
        <tr data-shift="<?= (int)$shift['id'] ?>">
          <td><?= esc($m['machine_code']) ?></td>
          <td class="fw-bold bg-light"><?= (int)$shift['total_minute'] ?></td>

          <td class="text-start">
            <select class="form-select form-select-sm product product-select"
                    data-machine="<?= (int)$m['id'] ?>"
                    data-shift="<?= (int)$shift['id'] ?>"
                    data-selected="<?= $p['product_id'] ?? '' ?>"
                    name="items[<?= esc($key) ?>][product_id]">
              <option value="">-- pilih --</option>
            </select>

            <input type="hidden" name="items[<?= esc($key) ?>][machine_id]" value="<?= (int)$m['id'] ?>">
            <input type="hidden" name="items[<?= esc($key) ?>][shift_id]" value="<?= (int)$shift['id'] ?>">
            <input type="hidden" name="items[<?= esc($key) ?>][date]" value="<?= esc($date) ?>">

            <input type="hidden" class="wa" name="items[<?= esc($key) ?>][weight_ascas]" value="<?= (float)($p['weight_ascas'] ?? 0) ?>">
            <input type="hidden" class="wr" name="items[<?= esc($key) ?>][weight_runner]" value="<?= (float)($p['weight_runner'] ?? 0) ?>">

            <input type="hidden" class="qty-a" name="items[<?= esc($key) ?>][qty_a]" value="<?= (int)($p['qty_a'] ?? 0) ?>">
            <input type="hidden" class="qty-ng" name="items[<?= esc($key) ?>][qty_ng]" value="<?= (int)($p['qty_ng'] ?? 0) ?>">
          </td>

          <td>
            <input type="number"
                   class="form-control form-control-sm qty-p text-end"
                   name="items[<?= esc($key) ?>][qty_p]"
                   value="<?= (int)($p['qty_p'] ?? 0) ?>"
                   min="0" max="1200">
          </td>

          <td class="ascas text-end">0.00</td>

          <td>
            <input type="number"
                   class="form-control form-control-sm text-end qty-a-display"
                   value="<?= (int)($p['qty_a'] ?? 0) ?>"
                   readonly>
          </td>

          <td class="runner text-end">0.00</td>

          <td>
            <input type="number"
                   class="form-control form-control-sm text-end qty-ng-display"
                   value="<?= (int)($p['qty_ng'] ?? 0) ?>"
                   readonly>
          </td>

          <td>
            <select class="form-select form-select-sm"
                    name="items[<?= esc($key) ?>][status]">
              <?php foreach (['Normal','Recovery','Trial','OFF'] as $s): ?>
                <option value="<?= esc($s) ?>" <?= (($p['status'] ?? 'Normal') === $s) ? 'selected' : '' ?>>
                  <?= esc($s) ?>
                </option>
              <?php endforeach ?>
            </select>
          </td>
        </tr>
      <?php endforeach ?>

      </tbody>
    </table>
  </div>
<?php endforeach ?>

<div class="mt-3 d-flex gap-2">
  <button class="btn btn-success" type="submit">💾 Simpan</button>
  <a href="<?= site_url('/die-casting/daily-schedule/inventory?date=' . esc($date)) ?>"
     class="btn btn-outline-primary">👁 View Result</a>
</div>

</form>

<script>
const productUrl = "<?= site_url('die-casting/daily-schedule/getProductAndTarget') ?>";

function recalcShiftTotals(shiftId){
  let totalAscas = 0;
  let totalRunner = 0;

  document.querySelectorAll(`tr[data-shift="${shiftId}"]`).forEach(tr => {
    totalAscas  += parseFloat(tr.querySelector('.ascas')?.innerText || '0') || 0;
    totalRunner += parseFloat(tr.querySelector('.runner')?.innerText || '0') || 0;
  });

  const a = document.querySelector(`.shift-ascas-total[data-shift="${shiftId}"]`);
  const r = document.querySelector(`.shift-runner-total[data-shift="${shiftId}"]`);
  if (a) a.innerText = totalAscas.toFixed(2);
  if (r) r.innerText = totalRunner.toFixed(2);
}

function calculate(tr){
  const shiftId = tr.dataset.shift;

  const qtyP = +tr.querySelector('.qty-p')?.value || 0;
  const qtyA = +tr.querySelector('.qty-a-display')?.value || 0;

  const wa   = +tr.querySelector('.wa')?.value || 0;
  const wr   = +tr.querySelector('.wr')?.value || 0;

  const ascasKg  = (qtyP * wa) / 1000;
  const runnerKg = (qtyA * wr) / 1000;

  tr.querySelector('.ascas').innerText  = ascasKg.toFixed(2);
  tr.querySelector('.runner').innerText = runnerKg.toFixed(2);

  const hidA = tr.querySelector('.qty-a');
  if (hidA) hidA.value = qtyA;

  const hidNG = tr.querySelector('.qty-ng');
  const ngDisplay = tr.querySelector('.qty-ng-display');
  if (hidNG && ngDisplay) hidNG.value = (+ngDisplay.value || 0);

  recalcShiftTotals(shiftId);
}

function applySelectedOptionToRow(sel){
  const tr = sel.closest('tr');
  const opt = sel.selectedOptions[0];
  if (!opt || !tr) return;

  tr.querySelector('.wa').value = opt.dataset.ascas || 0;
  tr.querySelector('.wr').value = opt.dataset.runner || 0;

  const qtyP = tr.querySelector('.qty-p');
  if (qtyP && (!qtyP.value || qtyP.value == 0)) {
    qtyP.value = opt.dataset.target || 0;
  }

  calculate(tr);
}

// Load dropdown products + init select2 searchable
document.querySelectorAll('.product-select').forEach(sel => {
  const selected = sel.dataset.selected || '';
  const machineId = sel.dataset.machine;
  const shiftId = sel.dataset.shift;

  fetch(`${productUrl}?machine_id=${machineId}&shift_id=${shiftId}`)
    .then(r => r.json())
    .then(res => {
      sel.innerHTML = '<option value="">-- pilih --</option>';

      res.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = `${p.part_prod} - ${p.part_name}`;
        opt.dataset.ascas  = p.weight_ascas || 0;
        opt.dataset.runner = p.weight_runner || 0;
        opt.dataset.target = p.target || 0;

        if (String(selected) === String(p.id)) opt.selected = true;
        sel.appendChild(opt);
      });

      $(sel).select2({
        width: '100%',
        placeholder: '-- pilih --',
        allowClear: true
      });

      if (selected) {
        applySelectedOptionToRow(sel);
      } else {
        calculate(sel.closest('tr'));
      }
    })
    .catch(() => {
      calculate(sel.closest('tr'));
    });
});

$(document).on('change', '.product-select', function() {
  applySelectedOptionToRow(this);
});

document.addEventListener('input', e => {
  if (!e.target.classList.contains('qty-p')) return;
  calculate(e.target.closest('tr'));
});

document.querySelectorAll('tr[data-shift]').forEach(tr => calculate(tr));
</script>

<?= $this->endSection() ?>