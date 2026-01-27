<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">Production Flow Product (Sideways + Drag & Drop)</h4>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<!-- ================= FILTER ================= -->
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

<form method="post" action="<?= site_url('master/production-flow/bulk-update') ?>">
<?= csrf_field() ?>

<style>
.table-sideways { white-space: nowrap; }
.table-sideways th, .table-sideways td { vertical-align: middle; }

.sticky-col-1 {
  position: sticky; left: 0; background: #fff; z-index: 3;
}
.sticky-col-2 {
  position: sticky; left: 260px; background: #fff; z-index: 3;
}
thead .sticky-col-1, thead .sticky-col-2 {
  background: #e9ecef; z-index: 5;
}

.product-cell { min-width: 260px; }
.flow-chip-wrap { min-width: 760px; } /* biar panjang ke kanan, enak scroll */

.flow-chips {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  padding: 8px;
  min-height: 52px;
  border: 1px dashed #ced4da;
  border-radius: 10px;
  background: #fff;
}

.flow-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 10px;
  border: 1px solid #dee2e6;
  border-radius: 999px;
  background: #f8f9fa;
  font-size: 12px;
  cursor: grab;
  user-select: none;
}
.flow-chip .handle {
  font-weight: 800;
  cursor: grab;
}
.flow-chip.off {
  opacity: .45;
  text-decoration: line-through;
}
.flow-chip input[type="checkbox"]{
  transform: scale(1.05);
}
.flow-chip .name{
  max-width: 220px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>

<div class="table-responsive">
  <table class="table table-bordered table-sm align-middle table-sideways">
    <thead class="table-secondary text-center">
      <tr>
        <th class="text-start sticky-col-1 product-cell">Product</th>
        <th class="sticky-col-2 flow-chip-wrap">Flow (drag urutan + centang aktif)</th>
        <th style="width:90px">Edit</th>
      </tr>
    </thead>

    <tbody>
    <?php foreach ($products as $p):
      $pid = (int)$p['id'];
      $selectedOrder = $flowOrder[$pid] ?? []; // urutan existing
    ?>
      <tr>
        <input type="hidden" name="product_ids[]" value="<?= $pid ?>">

        <!-- hidden order untuk bulk save -->
        <input type="hidden" name="flows_order[<?= $pid ?>]" id="order_<?= $pid ?>" value="<?= esc(implode(',', $selectedOrder)) ?>">

        <!-- hidden agar flows_selected[pid][] tetap terkirim walau semua off -->
        <input type="hidden" name="flows_selected[<?= $pid ?>][]" value="">

        <td class="text-start sticky-col-1 product-cell">
          <strong><?= esc($p['part_no']) ?></strong><br>
          <small class="text-muted"><?= esc($p['part_name']) ?></small>
        </td>

        <td class="sticky-col-2 flow-chip-wrap">
          <div class="flow-chips" id="chips_<?= $pid ?>" data-product="<?= $pid ?>">
            <?php
              // render chip mengikuti urutan existing dulu
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
                             name="flows_selected[<?= $pid ?>][]"
                             value="<?= (int)$pr['id'] ?>"
                             <?= $isChecked ? 'checked' : '' ?>>
                    </span>
                    <?php
                  }
                }
              }

              // render proses lain (belum masuk urutan)
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
                         name="flows_selected[<?= $pid ?>][]"
                         value="<?= $procId ?>"
                         <?= $isChecked ? 'checked' : '' ?>>
                </span>
                <?php
              }
            ?>
          </div>

          <small class="text-muted">
            Drag chip untuk urutan. Centang untuk aktif. Urutan yang disimpan hanya yang aktif.
          </small>
        </td>

        <td class="text-center">
          <button type="button"
                  class="btn btn-outline-primary btn-sm btn-edit"
                  data-product="<?= $pid ?>"
                  data-partno="<?= esc($p['part_no']) ?>"
                  data-partname="<?= esc($p['part_name']) ?>">
            Edit
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
    <button class="btn btn-success" type="submit">💾 Bulk Save</button>
  </div>
</div>

</form>

<!-- ================= MODAL EDIT INDIVIDUAL ================= -->
<div class="modal fade" id="flowModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Flow</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="mb-2">
          <div><strong id="m_partno"></strong></div>
          <div class="text-muted" id="m_partname"></div>
        </div>

        <input type="hidden" id="m_product_id">

        <div class="mb-2">
          <div class="flow-chips" id="m_chips"></div>
          <small class="text-muted">Drag chip untuk urutan. Centang untuk aktif.</small>
        </div>

        <div id="m_msg" class="mt-2"></div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" id="btnSaveOne">Save This Product</button>
      </div>
    </div>
  </div>
</div>

<!-- SortableJS CDN -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<script>
function updateHiddenOrder(productId) {
  const chips = document.getElementById('chips_' + productId);
  const ids = [];

  chips.querySelectorAll('.flow-chip').forEach(chip => {
    const cb = chip.querySelector('.chip-check');
    if (cb && cb.checked) ids.push(String(chip.dataset.id));
  });

  const hidden = document.getElementById('order_' + productId);
  if (hidden) hidden.value = ids.join(',');
}

function syncChipStyleFromItsCheckbox(chipEl) {
  const cb = chipEl.querySelector('.chip-check');
  if (!cb) return;
  chipEl.classList.toggle('off', !cb.checked);
}

document.querySelectorAll('.flow-chips[id^="chips_"]').forEach(box => {
  const productId = box.dataset.product;

  new Sortable(box, {
    animation: 150,
    handle: '.handle',
    onSort: () => updateHiddenOrder(productId),
  });

  box.querySelectorAll('.flow-chip').forEach(chip => {
    const cb = chip.querySelector('.chip-check');
    if (cb) {
      cb.addEventListener('change', () => {
        syncChipStyleFromItsCheckbox(chip);
        updateHiddenOrder(productId);
      });
      syncChipStyleFromItsCheckbox(chip);
    }
  });

  updateHiddenOrder(productId);
});

// ===== Modal Edit Individual =====
const modalEl = document.getElementById('flowModal');
const modal   = new bootstrap.Modal(modalEl);
let modalSortable = null;

document.querySelectorAll('.btn-edit').forEach(btn => {
  btn.addEventListener('click', () => {
    const productId = btn.dataset.product;

    document.getElementById('m_product_id').value = productId;
    document.getElementById('m_partno').textContent = btn.dataset.partno;
    document.getElementById('m_partname').textContent = btn.dataset.partname;
    document.getElementById('m_msg').innerHTML = '';

    // clone chips dari row
    const rowChips = document.getElementById('chips_' + productId);
    const mChips = document.getElementById('m_chips');
    mChips.innerHTML = rowChips.innerHTML;

    // sortable modal
    if (modalSortable) modalSortable.destroy();
    modalSortable = new Sortable(mChips, { animation: 150, handle: '.handle' });

    // bind modal checkbox change
    mChips.querySelectorAll('.flow-chip').forEach(chip => {
      const cb = chip.querySelector('.chip-check');
      if (cb) {
        cb.addEventListener('change', () => syncChipStyleFromItsCheckbox(chip));
        syncChipStyleFromItsCheckbox(chip);
      }
    });

    modal.show();
  });
});

function getModalPayload() {
  const productId = document.getElementById('m_product_id').value;
  const mChips = document.getElementById('m_chips');

  const selected = [];
  const order = [];

  mChips.querySelectorAll('.flow-chip').forEach(chip => {
    const id = String(chip.dataset.id);
    const cb = chip.querySelector('.chip-check');
    if (cb && cb.checked) {
      selected.push(id);
      order.push(id);
    }
  });

  return { productId, selected, order: order.join(',') };
}

document.getElementById('btnSaveOne').addEventListener('click', async () => {
  const payload = getModalPayload();
  const msgEl = document.getElementById('m_msg');
  msgEl.innerHTML = '';

  const formData = new FormData();
  formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
  formData.append('product_id', payload.productId);
  payload.selected.forEach(v => formData.append('selected[]', v));
  formData.append('order', payload.order);

  try {
    const res = await fetch("<?= site_url('master/production-flow/save-individual') ?>", {
      method: 'POST',
      body: formData
    });
    const json = await res.json();

    if (!json.ok) {
      msgEl.innerHTML = `<div class="alert alert-danger">${json.message}</div>`;
      return;
    }

    msgEl.innerHTML = `<div class="alert alert-success">${json.message}</div>`;

    // sync balik ke row
    const productId = payload.productId;
    const rowChips = document.getElementById('chips_' + productId);
    rowChips.innerHTML = document.getElementById('m_chips').innerHTML;

    // reinit sortable row
    new Sortable(rowChips, { animation: 150, handle: '.handle' });

    // bind row checkbox
    rowChips.querySelectorAll('.flow-chip').forEach(chip => {
      const cb = chip.querySelector('.chip-check');
      if (cb) {
        cb.addEventListener('change', () => {
          syncChipStyleFromItsCheckbox(chip);
          updateHiddenOrder(productId);
        });
        syncChipStyleFromItsCheckbox(chip);
      }
    });

    updateHiddenOrder(productId);

  } catch (e) {
    msgEl.innerHTML = `<div class="alert alert-danger">Gagal simpan: ${e}</div>`;
  }
});
</script>

<?= $this->endSection() ?>
