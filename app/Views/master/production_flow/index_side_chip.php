<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">Production Flow Product</h4>

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
/* ===== Excel-like ===== */
.table-excel { white-space: nowrap; font-size: 12px; }
.table-excel th, .table-excel td { vertical-align: middle; padding: 6px 8px; }

thead.excel-head th{
  background: #fff200;
  color: #000;
  text-transform: uppercase;
  font-weight: 800;
  border: 1px solid #333 !important;
  text-align: center;
}
.table-excel td{ border: 1px solid #333 !important; }

/* sticky cols */
.sticky-col-no   { position: sticky; left: 0; background: #fff; z-index: 4; min-width: 60px; }
.sticky-col-prod { position: sticky; left: 60px; background: #fff; z-index: 4; min-width: 320px; }
thead .sticky-col-no, thead .sticky-col-prod { z-index: 10; }

.sticky-col-action { position: sticky; right: 0; background: #fff; z-index: 5; min-width: 140px; }
thead .sticky-col-action { z-index: 11; }

.flow-cell { text-align: center; min-width: 110px; }

/* checkbox look nicer */
.flow-check{
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.small-muted { font-size: 11px; color: #6c757d; }
@media (max-width: 768px){
  .sticky-col-prod { min-width: 260px; }
  .flow-cell { min-width: 95px; }
}
</style>

<?php
  /**
   * ✅ Susun ulang kolom proses:
   * RAW MATERIAL -> DIE CASTING -> BURRYTORY -> SAND BLASTING -> MACHINING -> (sisanya tetap urutan awal)
   */
  $wantedNames = [
    'RAW MATERIAL',
    'DIE CASTING',
    'BURRYTORY',
    'SAND BLASTING',
    'MACHINING',
  ];

  $normalize = function($s){
    $s = strtoupper(trim((string)$s));
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
  };

  $byName = [];
  foreach (($processes ?? []) as $pr) {
    $byName[$normalize($pr['process_name'] ?? '')] = $pr;
  }

  $orderedProcesses = [];

  // ambil yang diinginkan sesuai urutan baru
  foreach ($wantedNames as $nm) {
    $key = $normalize($nm);
    if (isset($byName[$key])) {
      $orderedProcesses[] = $byName[$key];
      unset($byName[$key]);
    }
  }

  // sisanya, append sesuai urutan awal dari DB (tidak diacak)
  foreach (($processes ?? []) as $pr) {
    $key = $normalize($pr['process_name'] ?? '');
    if (isset($byName[$key])) {
      $orderedProcesses[] = $pr;
      unset($byName[$key]);
    }
  }

  // pakai urutan baru untuk table render
  $processes = $orderedProcesses;
?>

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
          $selectedOrder = $flowOrder[$pid] ?? [];
        ?>

        <tr id="row_<?= $pid ?>">

          <td class="sticky-col-no text-center fw-bold">
            <?= $no++ ?>
            <input type="hidden" name="product_ids[]" value="<?= $pid ?>">
            <input type="hidden" name="flows_order[<?= $pid ?>]" id="order_<?= $pid ?>" value="<?= esc(implode(',', $selectedOrder)) ?>">
            <input type="hidden" name="flows_selected[<?= $pid ?>][]" value="">
          </td>

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
              <input
                type="checkbox"
                class="form-check-input flow-check"
                id="<?= $cbId ?>"
                name="flows_selected[<?= $pid ?>][]"
                value="<?= $procId ?>"
                data-product="<?= $pid ?>"
                data-process="<?= $procId ?>"
                <?= $isChecked ? 'checked' : '' ?>
                title="<?= esc($pr['process_name']) ?>"
              >
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
  const CSRF_NAME = "<?= csrf_token() ?>";

  function getCsrfInput(){
    return document.querySelector(`#bulkForm input[name="${CSRF_NAME}"]`);
  }
  function getCsrfValue(){
    const inp = getCsrfInput();
    return inp ? inp.value : "";
  }
  function setCsrfValue(newHash){
    const inp = getCsrfInput();
    if (inp && newHash) inp.value = newHash;
  }
  function setStatus(productId, html) {
    const el = document.getElementById('status_' + productId);
    if (el) el.innerHTML = html;
  }

  function getRowOrder(productId){
    const row = document.getElementById('row_' + productId);
    if (!row) return [];
    const checked = row.querySelectorAll('input.flow-check:checked');
    const ids = [];
    checked.forEach(cb => ids.push(String(cb.value)));
    return ids;
  }

  function updateHiddenOrder(productId){
    const ids = getRowOrder(productId);
    const hidden = document.getElementById('order_' + productId);
    if (hidden) hidden.value = ids.join(',');
  }

  document.addEventListener('change', (e) => {
    const cb = e.target;
    if (!(cb instanceof HTMLElement)) return;
    if (!cb.classList.contains('flow-check')) return;

    const pid = cb.getAttribute('data-product');
    if (!pid) return;

    updateHiddenOrder(pid);
    setStatus(pid, '<span class="text-warning">● Changed (not saved)</span>');
  });

  document.getElementById('bulkForm').addEventListener('submit', () => {
    document.querySelectorAll('tr[id^="row_"]').forEach(tr => {
      const pid = tr.id.replace('row_','');
      updateHiddenOrder(pid);
    });
  });

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-save-row');
    if (!btn) return;

    const productId = btn.dataset.product;
    const order = getRowOrder(productId);

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving';
    setStatus(productId, '<span class="text-muted">Saving...</span>');

    const formData = new FormData();
    formData.append(CSRF_NAME, getCsrfValue());
    formData.append('product_id', productId);

    order.forEach(v => formData.append('selected[]', v));
    formData.append('order', order.join(','));

    try {
      const res = await fetch("<?= site_url('master/production-flow/save-individual') ?>", {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: formData
      });

      if (!res.ok) {
        setStatus(productId, `<span class="text-danger">✖ HTTP ${res.status} (cek CSRF)</span>`);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-save"></i> Save';
        return;
      }

      const json = await res.json();
      if (json && json.csrfHash) setCsrfValue(json.csrfHash);

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

  document.querySelectorAll('tr[id^="row_"]').forEach(tr => {
    const pid = tr.id.replace('row_','');
    updateHiddenOrder(pid);
  });
});
</script>

<?= $this->endSection() ?>
