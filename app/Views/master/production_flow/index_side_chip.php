<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">Production Flow Product (Table Excel Style)</h4>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<form method="get" class="row g-2 mb-3">
  <div class="col-md-4">
    <input type="text" name="keyword" class="form-control"
           placeholder="Search part no / name"
           value="<?= esc($keyword ?? '') ?>">
  </div>

  <div class="col-md-2">
    <select name="per_page" class="form-control" onchange="this.form.submit()">
      <?php foreach ([10,25,50,100] as $n): ?>
        <option value="<?= $n ?>" <?= ((int)$perPage === (int)$n) ? 'selected' : '' ?>>
          <?= $n ?> data
        </option>
      <?php endforeach ?>
    </select>
  </div>

  <div class="col-md-2">
    <button class="btn btn-primary">Filter</button>
  </div>
</form>

<form id="bulkForm" method="post" action="<?= site_url('master/production-flow/bulk-update') ?>">
<?= csrf_field() ?>

<style>
/* ====== Excel-like table ====== */
.table-excel {
  white-space: nowrap;
  font-size: 12px;
}
.table-excel th, .table-excel td {
  vertical-align: middle;
  padding: 6px 8px;
}

thead.excel-head th{
  background: #fff200; /* kuning excel */
  color: #000;
  text-transform: uppercase;
  font-weight: 800;
  border: 1px solid #333 !important;
  text-align: center;
}

.table-excel td{
  border: 1px solid #333 !important;
}

/* sticky product columns */
.sticky-col-no   { position: sticky; left: 0;   background: #fff; z-index: 4; min-width: 60px; }
.sticky-col-prod { position: sticky; left: 60px; background: #fff; z-index: 4; min-width: 320px; }
thead .sticky-col-no, thead .sticky-col-prod { z-index: 10; }

/* sticky action column */
.sticky-col-action { position: sticky; right: 0; background: #fff; z-index: 5; min-width: 140px; }
thead .sticky-col-action { z-index: 11; }

/* dot checkbox style */
.flow-cell { text-align: center; min-width: 110px; }
.flow-dot-wrap { display:inline-flex; align-items:center; justify-content:center; }

.flow-check {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.flow-dot {
  width: 22px;
  height: 22px;
  border-radius: 999px;
  border: 2px solid #0f5132;
  background: #fff;
  display: inline-block;
  cursor: pointer;
  box-shadow: inset 0 0 0 2px #fff;
}

.flow-check:checked + .flow-dot {
  background: #16a34a; /* hijau */
  border-color: #0f5132;
}

.flow-check:not(:checked) + .flow-dot {
  background: #f8f9fa;
  border-color: #6c757d;
}

.small-muted { font-size: 11px; color: #6c757d; }

@media (max-width: 768px){
  .sticky-col-prod { min-width: 260px; }
  .flow-cell { min-width: 95px; }
}
</style>

<div class="table-responsive">
  <table class="table table-sm table-excel mb-0">
    <thead class="excel-head">
      <tr>
        <th class="sticky-col-no">NO.</th>
        <th class="sticky-col-prod text-start">PRODUCT</th>

        <?php foreach (($processes ?? []) as $pr): ?>
          <th class="flow-cell">
            <?= esc($pr['process_name']) ?>
          </th>
        <?php endforeach; ?>

        <th class="sticky-col-action">SAVE</th>
      </tr>
    </thead>

    <tbody>
      <?php $no=1; foreach (($products ?? []) as $p): ?>
        <?php
          $pid = (int)$p['id'];
          $selectedOrder = $flowOrder[$pid] ?? []; // hanya untuk display info awal (tidak ada input nomor)
        ?>
        <tr id="row_<?= $pid ?>">
          <!-- hidden fields untuk bulk save -->
          <input type="hidden" name="product_ids[]" value="<?= $pid ?>">
          <input type="hidden" name="flows_order[<?= $pid ?>]" id="order_<?= $pid ?>" value="<?= esc(implode(',', $selectedOrder)) ?>">
          <input type="hidden" name="flows_selected[<?= $pid ?>][]" value="">

          <td class="sticky-col-no text-center fw-bold"><?= $no++ ?></td>

          <td class="sticky-col-prod text-start">
            <div class="fw-bold"><?= esc($p['part_no']) ?></div>
            <div class="small text-muted"><?= esc($p['part_name']) ?></div>
            <div class="small mt-1" id="status_<?= $pid ?>"></div>
          </td>

          <?php foreach (($processes ?? []) as $pr): ?>
            <?php
              $procId = (int)$pr['id'];
              $isChecked = isset($flowMap[$pid][$procId]);
              $cbId = "cb_{$pid}_{$procId}";
            ?>
            <td class="flow-cell">
              <span class="flow-dot-wrap">
                <input
                  type="checkbox"
                  class="form-check-input flow-check flow-check-<?= $pid ?>"
                  id="<?= $cbId ?>"
                  name="flows_selected[<?= $pid ?>][]"
                  value="<?= $procId ?>"
                  <?= $isChecked ? 'checked' : '' ?>
                >
                <label class="flow-dot" for="<?= $cbId ?>" title="<?= esc($pr['process_name']) ?>"></label>
              </span>
            </td>
          <?php endforeach; ?>

          <td class="sticky-col-action text-center">
            <button type="button" class="btn btn-success btn-sm btn-save-row" data-product="<?= $pid ?>">
              <i class="bi bi-save"></i> Save
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="d-flex justify-content-between mt-3 align-items-center">
  <div><?= $pager->links('products', 'bootstrap_pagination') ?></div>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-primary" type="submit">
      💾 Bulk Save
    </button>
  </div>
</div>

</form>

<script>
document.addEventListener('DOMContentLoaded', () => {

  function setStatus(productId, html) {
    const el = document.getElementById('status_' + productId);
    if (el) el.innerHTML = html;
  }

  // ambil order sesuai urutan kolom (kiri->kanan)
  function getRowOrder(productId){
    const checks = document.querySelectorAll('.flow-check-' + productId);
    const ids = [];
    checks.forEach(cb => {
      if (cb.checked) ids.push(String(cb.value));
    });
    return ids;
  }

  function updateHiddenOrder(productId){
    const ids = getRowOrder(productId);
    const hidden = document.getElementById('order_' + productId);
    if (hidden) hidden.value = ids.join(',');
  }

  // tanda changed saat checkbox diubah
  document.addEventListener('change', (e) => {
    const cb = e.target;
    if (!cb.classList.contains('flow-check')) return;

    // ambil productId dari class flow-check-{pid}
    const cls = Array.from(cb.classList).find(c => c.startsWith('flow-check-'));
    if (!cls) return;
    const pid = cls.replace('flow-check-','');

    updateHiddenOrder(pid);
    setStatus(pid, '<span class="text-warning">● Changed (not saved)</span>');
  });

  // bulk submit: update hidden order untuk semua row
  document.getElementById('bulkForm').addEventListener('submit', () => {
    document.querySelectorAll('input[id^="order_"]').forEach(h => {
      const pid = h.id.replace('order_','');
      updateHiddenOrder(pid);
    });
  });

  // save per row (AJAX)
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-save-row');
    if (!btn) return;

    const productId = btn.dataset.product;
    const order = getRowOrder(productId);

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving';
    setStatus(productId, '<span class="text-muted">Saving...</span>');

    const formData = new FormData();
    formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
    formData.append('product_id', productId);

    // selected[] dan order string sama (urut sesuai kolom)
    order.forEach(v => formData.append('selected[]', v));
    formData.append('order', order.join(','));

    try {
      const res = await fetch("<?= site_url('master/production-flow/save-individual') ?>", {
        method: 'POST',
        body: formData
      });
      const json = await res.json();

      if (!json.ok) {
        setStatus(productId, `<span class="text-danger">✖ ${json.message}</span>`);
      } else {
        updateHiddenOrder(productId);
        setStatus(productId, '<span class="text-success">✔ Saved</span>');
      }
    } catch (err) {
      setStatus(productId, `<span class="text-danger">✖ Gagal: ${err}</span>`);
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-save"></i> Save';
    }
  });

  // init: set hidden order awal agar konsisten
  document.querySelectorAll('input[id^="order_"]').forEach(h => {
    const pid = h.id.replace('order_','');
    updateHiddenOrder(pid);
  });

});
</script>

<?= $this->endSection() ?>
