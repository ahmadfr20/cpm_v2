<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>DIE CASTING – DAILY PRODUCTION SCHEDULE</h4>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<form method="get" class="mb-3">
  <input type="date" name="date" value="<?= esc($date) ?>"
         class="form-control w-25" onchange="this.form.submit()">
</form>

<form method="post" action="<?= site_url('/die-casting/daily-schedule/store') ?>">
<?= csrf_field() ?>

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

  <table class="table table-bordered table-sm text-center align-middle">
    <thead class="table-secondary">
      <tr>
        <th>Mesin</th>
        <th>Total Menit</th>
        <th>Part</th>
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

        <td>
          <select class="form-select form-select-sm product"
                  data-machine="<?= (int)$m['id'] ?>"
                  data-shift="<?= (int)$shift['id'] ?>"
                  data-selected="<?= $p['product_id'] ?? '' ?>"
                  name="items[<?= esc($key) ?>][product_id]">
            <option value="">-- pilih --</option>
          </select>
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
                 class="form-control form-control-sm text-end"
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

        <!-- hidden required -->
        <input type="hidden" name="items[<?= esc($key) ?>][machine_id]" value="<?= (int)$m['id'] ?>">
        <input type="hidden" name="items[<?= esc($key) ?>][shift_id]" value="<?= (int)$shift['id'] ?>">
        <input type="hidden" name="items[<?= esc($key) ?>][date]" value="<?= esc($date) ?>">

        <!-- weights from dropdown -->
        <input type="hidden" class="wa" name="items[<?= esc($key) ?>][weight_ascas]" value="0">
        <input type="hidden" class="wr" name="items[<?= esc($key) ?>][weight_runner]" value="0">

        <!-- actual stored for controller too -->
        <input type="hidden" class="qty-a" name="items[<?= esc($key) ?>][qty_a]" value="<?= (int)($p['qty_a'] ?? 0) ?>">
        <input type="hidden" name="items[<?= esc($key) ?>][qty_ng]" value="<?= (int)($p['qty_ng'] ?? 0) ?>">
      </tr>
    <?php endforeach ?>

    </tbody>
  </table>
<?php endforeach ?>

<div class="mt-3 d-flex gap-2">
  <button class="btn btn-success">💾 Simpan</button>
  <a href="<?= site_url('/die-casting/daily-schedule/view?date=' . esc($date)) ?>"
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

/**
 * Ascas = PLAN(qty_p) * weight_ascas
 * Runner = ACTUAL(qty_a) * weight_runner
 */
function calculate(tr){
  const shiftId = tr.dataset.shift;

  const qtyP = +tr.querySelector('.qty-p')?.value || 0;
  const qtyA = +tr.querySelector('.qty-a')?.value || 0;

  const wa   = +tr.querySelector('.wa')?.value || 0;
  const wr   = +tr.querySelector('.wr')?.value || 0;

  const ascasKg  = (qtyP * wa) / 1000;
  const runnerKg = (qtyA * wr) / 1000;

  tr.querySelector('.ascas').innerText  = ascasKg.toFixed(2);
  tr.querySelector('.runner').innerText = runnerKg.toFixed(2);

  recalcShiftTotals(shiftId);
}

// Load dropdown products
document.querySelectorAll('.product').forEach(sel => {
  const tr = sel.closest('tr');
  const selected = sel.dataset.selected;

  fetch(`${productUrl}?machine_id=${sel.dataset.machine}&shift_id=${sel.dataset.shift}`)
    .then(r => r.json())
    .then(res => {
      sel.innerHTML = '<option value="">-- pilih --</option>';

      res.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = `${p.part_no} - ${p.part_name}`;
        opt.dataset.ascas = p.weight_ascas || 0;
        opt.dataset.runner = p.weight_runner || 0;
        opt.dataset.target = p.target || 0;

        if (String(selected) === String(p.id)) opt.selected = true;
        sel.appendChild(opt);
      });

      // kalau sudah ada selected dari DB, set weight lalu calculate
      if (selected) {
        const opt = sel.selectedOptions[0];
        if (opt) {
          tr.querySelector('.wa').value = opt.dataset.ascas || 0;
          tr.querySelector('.wr').value = opt.dataset.runner || 0;
        }
        calculate(tr);
      } else {
        // reset tampilan
        calculate(tr);
      }
    });
});

// Saat ganti part
document.addEventListener('change', e => {
  if (!e.target.classList.contains('product')) return;

  const tr = e.target.closest('tr');
  const opt = e.target.selectedOptions[0];
  if (!opt) return;

  tr.querySelector('.wa').value = opt.dataset.ascas || 0;
  tr.querySelector('.wr').value = opt.dataset.runner || 0;

  // auto-fill plan kalau masih 0
  const qtyP = tr.querySelector('.qty-p');
  if (!qtyP.value || qtyP.value == 0) qtyP.value = opt.dataset.target || 0;

  calculate(tr);
});

// Saat plan berubah
document.addEventListener('input', e => {
  if (!e.target.classList.contains('qty-p')) return;
  calculate(e.target.closest('tr'));
});
</script>

<?= $this->endSection() ?>
