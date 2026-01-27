<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">Production Flow Product (Drag & Drop + Checkbox di Chip)</h4>

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
.table-sideways { white-space: nowrap; }
.table-sideways th, .table-sideways td { vertical-align: middle; }

.sticky-col-1 { position: sticky; left: 0; background: #fff; z-index: 3; }
.sticky-col-2 { position: sticky; left: 260px; background: #fff; z-index: 3; }
thead .sticky-col-1, thead .sticky-col-2 { background: #e9ecef; z-index: 5; }

.sticky-right {
  position: sticky;
  right: 0;
  background: #fff;
  z-index: 4;
  min-width: 110px;
}
thead .sticky-right { background:#e9ecef; z-index: 6; }

.product-cell { min-width: 260px; }
.flow-chip-wrap { min-width: 760px; }

.flow-chips{
  display:flex; gap:6px; flex-wrap:wrap;
  padding:8px; min-height:52px;
  border:1px dashed #ced4da; border-radius:10px;
  background:#fff;
}
.flow-chip{
  display:inline-flex; align-items:center; gap:8px;
  padding:6px 10px; border:1px solid #dee2e6; border-radius:999px;
  background:#f8f9fa; font-size:12px; user-select:none;
}
.flow-chip .handle{ font-weight:800; cursor:grab; }
.flow-chip.off{ opacity:.45; text-decoration:line-through; }
.flow-chip .name{
  max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
}
.flow-chip input[type="checkbox"]{ transform:scale(1.05); cursor:pointer; }

@media (max-width: 576px) {
  .product-cell { min-width: 220px; }
  .flow-chip-wrap { min-width: 520px; }
  .flow-chip .name { max-width: 160px; }
}
</style>

<div class="table-responsive">
  <table class="table table-bordered table-sm align-middle table-sideways">
    <thead class="table-secondary text-center">
      <tr>
        <th class="text-start sticky-col-1 product-cell">Product</th>
        <th class="sticky-col-2 flow-chip-wrap">Flow (drag + centang)</th>
        <th class="sticky-right" style="width:110px">Save</th>
      </tr>
    </thead>

    <tbody>
    <?php foreach ($products as $p):
      $pid = (int)$p['id'];
      $selectedOrder = $flowOrder[$pid] ?? [];
    ?>
      <tr id="row_<?= $pid ?>">
        <input type="hidden" name="product_ids[]" value="<?= $pid ?>">
        <input type="hidden" name="flows_order[<?= $pid ?>]" id="order_<?= $pid ?>" value="<?= esc(implode(',', $selectedOrder)) ?>">
        <input type="hidden" name="flows_selected[<?= $pid ?>][]" value="">

        <td class="text-start sticky-col-1 product-cell">
          <strong><?= esc($p['part_no']) ?></strong><br>
          <small class="text-muted"><?= esc($p['part_name']) ?></small>

          <div class="small mt-1" id="status_<?= $pid ?>"></div>
        </td>

        <td class="sticky-col-2 flow-chip-wrap">
          <div class="flow-chips" id="chips_<?= $pid ?>" data-product="<?= $pid ?>">
            <?php
              $rendered = [];

              foreach ($selectedOrder as $procId) {
                foreach ($processes as $pr) {
                  if ((int)$pr['id'] === (int)$procId) {
                    $rendered[] = (int)$pr['id'];
                    $isChecked = isset($flowMap[$pid][(int)$pr['id']]);
            ?>
                    <span class="flow-chip <?= $isChecked ? '' : 'off' ?>" data-id="<?= (int)$pr['id'] ?>">
                      <span class="handle">☰</span>
                      <span class="name"><?= esc($pr['process_name']) ?></span>
                      <input type="checkbox"
                             class="form-check-input chip-check"
                             value="<?= (int)$pr['id'] ?>"
                             <?= $isChecked ? 'checked' : '' ?>>
                    </span>
            <?php
                  }
                }
              }

              foreach ($processes as $pr) {
                $procId = (int)$pr['id'];
                if (in_array($procId, $rendered, true)) continue;
                $isChecked = isset($flowMap[$pid][$procId]);
            ?>
                <span class="flow-chip <?= $isChecked ? '' : 'off' ?>" data-id="<?= $procId ?>">
                  <span class="handle">☰</span>
                  <span class="name"><?= esc($pr['process_name']) ?></span>
                  <input type="checkbox"
                         class="form-check-input chip-check"
                         value="<?= $procId ?>"
                         <?= $isChecked ? 'checked' : '' ?>>
                </span>
            <?php } ?>
          </div>

          <small class="text-muted">Yang disimpan hanya chip yang dicentang.</small>
        </td>

        <td class="text-center sticky-right">
          <button type="button"
                  class="btn btn-success btn-sm btn-save-row"
                  data-product="<?= $pid ?>">
            <i class="bi bi-save"></i> Save
          </button>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</div>

<div class="d-flex justify-content-between mt-3 align-items-center">
  <div><?= $pager->links('products', 'bootstrap_pagination') ?></div>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-primary" type="submit">💾 Bulk Save</button>
  </div>
</div>

</form>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {

  function setStatus(productId, html) {
    const el = document.getElementById('status_' + productId);
    if (el) el.innerHTML = html;
  }

  function updateHiddenOrder(productId) {
    const box = document.getElementById('chips_' + productId);
    if (!box) return;

    const ids = [];
    box.querySelectorAll('.flow-chip').forEach(chip => {
      const cb = chip.querySelector('.chip-check');
      if (cb && cb.checked) ids.push(String(chip.dataset.id));
    });

    const hidden = document.getElementById('order_' + productId);
    if (hidden) hidden.value = ids.join(',');
  }

  function syncChipStyle(chipEl) {
    const cb = chipEl.querySelector('.chip-check');
    if (!cb) return;
    chipEl.classList.toggle('off', !cb.checked);
  }

  // init sortable rows
  const sortableMap = new Map();
  function initRow(productId) {
    const box = document.getElementById('chips_' + productId);
    if (!box) return;

    if (sortableMap.has(productId)) {
      sortableMap.get(productId).destroy();
      sortableMap.delete(productId);
    }

    const inst = new Sortable(box, {
      animation: 150,
      handle: '.handle',
      onEnd: () => {
        updateHiddenOrder(productId);
        setStatus(productId, '<span class="text-warning">● Changed (not saved)</span>');
      },
    });
    sortableMap.set(productId, inst);

    box.querySelectorAll('.flow-chip').forEach(chip => syncChipStyle(chip));
    updateHiddenOrder(productId);
  }

  document.querySelectorAll('.flow-chips[id^="chips_"]').forEach(box => {
    initRow(box.dataset.product);
  });

  // checkbox change (delegation)
  document.addEventListener('change', (e) => {
    const cb = e.target;
    if (!cb.classList.contains('chip-check')) return;

    const chip = cb.closest('.flow-chip');
    if (chip) syncChipStyle(chip);

    const rowBox = cb.closest('.flow-chips[id^="chips_"]');
    if (rowBox) {
      const pid = rowBox.dataset.product;
      updateHiddenOrder(pid);
      setStatus(pid, '<span class="text-warning">● Changed (not saved)</span>');
    }
  });

  // bulk submit: update hidden for all
  document.getElementById('bulkForm').addEventListener('submit', () => {
    document.querySelectorAll('.flow-chips[id^="chips_"]').forEach(box => {
      updateHiddenOrder(box.dataset.product);
    });
  });

  // save per row (AJAX)
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-save-row');
    if (!btn) return;

    const productId = btn.dataset.product;
    const box = document.getElementById('chips_' + productId);
    if (!box) return;

    // kumpulkan selected & order (yang checked)
    const selected = [];
    const order = [];
    box.querySelectorAll('.flow-chip').forEach(chip => {
      const id = String(chip.dataset.id);
      const cb = chip.querySelector('.chip-check');
      if (cb && cb.checked) {
        selected.push(id);
        order.push(id);
      }
    });

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving';
    setStatus(productId, '<span class="text-muted">Saving...</span>');

    const formData = new FormData();
    formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
    formData.append('product_id', productId);
    selected.forEach(v => formData.append('selected[]', v));
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
        // update hidden juga biar konsisten
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

});
</script>

<?= $this->endSection() ?>
