<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">DAILY PRODUCTION SCHEDULE – MACHINING</h4>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<form method="get" class="mb-3" style="max-width:220px">
  <label class="form-label fw-bold">Tanggal</label>
  <input type="date"
         name="date"
         value="<?= esc($date) ?>"
         class="form-control"
         onchange="this.form.submit()">
</form>

<div class="mb-3">
  <a href="/machining/daily-schedule/result?date=<?= esc($date) ?>"
     class="btn btn-outline-primary btn-sm">
    <i class="bi bi-graph-up"></i> Lihat Hasil & Efektivitas
  </a>
</div>

<?php foreach ($shifts as $shift): ?>
  <hr>
  <h5 class="mt-4 mb-3"><?= esc($shift['shift_name']) ?></h5>

  <form method="post" action="/machining/daily-schedule/store">
    <?= csrf_field() ?>
    <input type="hidden" name="date" value="<?= esc($date) ?>">

    <table class="table table-bordered table-sm align-middle text-center">
      <thead class="table-secondary">
        <tr>
          <th style="width:60px">Line</th>
          <th style="width:120px">Alamat Mesin</th>
          <th>Tipe Mesin</th>
          <th style="width:320px">Part</th>
          <th style="width:80px">CT</th>
          <th style="width:120px">Planning</th>
          <th style="width:80px">Actual</th>
        </tr>
      </thead>

      <tbody>
      <?php foreach ($machines as $idx => $machine):

          $keyPlan = $shift['id'].'_'.$machine['id'];
          $plan    = $planMap[$keyPlan] ?? null;

          $actKey  = $shift['id'].'_'.$machine['id'].'_'.($plan['product_id'] ?? 0);
          $actual  = $actualMap[$actKey]['act'] ?? 0;
      ?>
        <tr>
          <td><?= esc($machine['line_position']) ?></td>

          <td class="fw-bold text-primary">
            <?= esc($machine['machine_code']) ?>
          </td>

          <td class="text-start">
            <?= esc($machine['machine_name']) ?>
          </td>

          <td>
            <select class="form-select form-select-sm product-select"
                    data-machine="<?= (int)$machine['id'] ?>"
                    data-shift="<?= (int)$shift['id'] ?>"
                    data-selected="<?= esc($plan['product_id'] ?? '') ?>"
                    name="items[<?= $idx ?>][product_id]">
              <option value="">-- pilih part --</option>
            </select>
          </td>

          <td>
            <input type="text"
                   class="form-control form-control-sm text-center cycle-time"
                   value="<?= esc($plan['cycle_time'] ?? '') ?>"
                   readonly>
          </td>

          <td>
            <input type="number"
                   class="form-control form-control-sm text-center plan-input"
                   name="items[<?= $idx ?>][plan]"
                   max="1200"
                   value="<?= esc($plan['target_per_shift'] ?? '') ?>">
          </td>

          <td>
            <input type="text"
                   class="form-control form-control-sm text-center"
                   value="<?= esc($actual) ?>"
                   readonly>
          </td>

          <!-- hidden wajib -->
          <input type="hidden" name="items[<?= $idx ?>][machine_id]" value="<?= (int)$machine['id'] ?>">
          <input type="hidden" name="items[<?= $idx ?>][shift_id]" value="<?= (int)$shift['id'] ?>">
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>

    <button class="btn btn-success btn-sm mb-4">
      <i class="bi bi-save"></i> Simpan <?= esc($shift['shift_name']) ?>
    </button>
  </form>
<?php endforeach ?>

<script>
const productUrl = "/machining/daily-schedule/product-target";

async function loadProducts(selectEl) {
  const machineId  = selectEl.dataset.machine;
  const shiftId    = selectEl.dataset.shift;
  const selectedId = selectEl.dataset.selected;

  try {
    const res  = await fetch(`${productUrl}?machine_id=${machineId}&shift_id=${shiftId}`);
    const data = await res.json();

    selectEl.innerHTML = '<option value="">-- pilih part --</option>';

    data.forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = `${p.part_no} - ${p.part_name}`;
      opt.dataset.ct = p.cycle_time || '';
      opt.dataset.targetShift = p.target_per_shift || 0;

      if (String(selectedId) === String(p.id)) opt.selected = true;
      selectEl.appendChild(opt);
    });

    // trigger change jika ada selected existing
    if (selectedId) {
      selectEl.dispatchEvent(new Event('change'));
    }

  } catch (e) {
    console.error("Gagal load product machining", e);
  }
}

document.querySelectorAll('.product-select').forEach(selectEl => {
  loadProducts(selectEl);

  selectEl.addEventListener('change', () => {
    const opt = selectEl.selectedOptions[0];
    if (!opt) return;

    const row = selectEl.closest('tr');

    // set CT
    row.querySelector('.cycle-time').value = opt.dataset.ct || '';

    // set Plan default (kalau kosong)
    const planEl = row.querySelector('.plan-input');
    if (!planEl.value || planEl.value == 0) {
      planEl.value = opt.dataset.targetShift || 0;
    }
  });
});
</script>

<?= $this->endSection() ?>
