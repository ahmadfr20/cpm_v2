<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
  /* biar Select2 tinggi sama dengan form-select-sm bootstrap */
  .select2-container .select2-selection--single {
    height: 31px;
    padding: 2px 6px;
    border: 1px solid #ced4da;
    border-radius: .2rem;
  }
  .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 26px;
    padding-left: 2px;
  }
  .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 29px;
  }
</style>

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
          <th style="width:360px">Part</th>
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

          <td class="text-start">
            <select class="form-select form-select-sm product-select js-product-select w-100"
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

<!-- jQuery + Select2 JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
const productUrl = "/machining/daily-schedule/product-target";

/** init select2 untuk satu select */
function initSelect2(selectEl) {
  const $el = $(selectEl);
  if ($el.data('select2')) return; // sudah init

  $el.select2({
    width: '100%',
    placeholder: '-- pilih part --',
    allowClear: true,
    dropdownAutoWidth: true
  });
}

/** load options dari server, lalu set selected dan trigger change */
async function loadProducts(selectEl) {
  const machineId  = selectEl.dataset.machine;
  const shiftId    = selectEl.dataset.shift;
  const selectedId = selectEl.dataset.selected;

  try {
    const res  = await fetch(`${productUrl}?machine_id=${machineId}&shift_id=${shiftId}`);
    const data = await res.json();

    // reset option
    selectEl.innerHTML = '<option value=""></option>'; // untuk allowClear & placeholder select2

    data.forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = `${p.part_no} - ${p.part_name}`;
      opt.dataset.ct = p.cycle_time || '';
      opt.dataset.targetShift = p.target_per_shift || 0;
      selectEl.appendChild(opt);
    });

    // init select2 setelah options ada
    initSelect2(selectEl);

    // set selected dari DB (kalau ada)
    if (selectedId) {
      $(selectEl).val(String(selectedId)).trigger('change');
    } else {
      // trigger change supaya CT/plan bisa reset
      $(selectEl).trigger('change');
    }

  } catch (e) {
    console.error("Gagal load product machining", e);
    initSelect2(selectEl);
  }
}

// event change (select2 tetap menembakkan 'change')
$(document).on('change', '.product-select', function() {
  const selectEl = this;
  const opt = selectEl.selectedOptions[0];
  const row = selectEl.closest('tr');

  // kalau kosong (clear)
  if (!opt || !selectEl.value) {
    row.querySelector('.cycle-time').value = '';
    // plan tidak dipaksa kosong supaya user tidak kehilangan input manual
    return;
  }

  // set CT
  row.querySelector('.cycle-time').value = opt.dataset.ct || '';

  // set Plan default (kalau kosong/0)
  const planEl = row.querySelector('.plan-input');
  if (!planEl.value || planEl.value == 0) {
    planEl.value = opt.dataset.targetShift || 0;
  }
});

// init semua select + load data
document.querySelectorAll('.product-select').forEach(selectEl => {
  initSelect2(selectEl);     // biar langsung bisa search (meskipun option masih loading)
  loadProducts(selectEl);    // load options dari server
});
</script>

<?= $this->endSection() ?>
