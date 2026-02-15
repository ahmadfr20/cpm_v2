<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
.modal-dialog { margin-top: 90px !important; }
@media (max-width: 768px){ .modal-dialog { margin-top: 70px !important; } }
.table td, .table th { vertical-align: middle; }
.select2-container .select2-selection--single{ height: 38px; }
.select2-container--default .select2-selection--single .select2-selection__rendered{ line-height: 38px; }
.select2-container--default .select2-selection--single .select2-selection__arrow{ height: 38px; }
.select2-container{ width:100% !important; }
</style>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<h4 class="mb-3">Master Production Standards</h4>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success"><?= session()->getFlashdata('success') ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger"><?= session()->getFlashdata('error') ?></div>
<?php endif ?>

<div class="d-flex flex-wrap gap-2 mb-3">
  <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAddStandard">
    + Tambah Standard
  </button>

  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalBulkStandard">
    + Bulk Add
  </button>

  <button class="btn btn-warning" type="button" id="btnOpenBulkEdit" disabled
          data-bs-toggle="modal" data-bs-target="#modalBulkEdit">
    Bulk Edit
  </button>

  <button class="btn btn-danger" type="button" id="btnOpenBulkDelete" disabled
          data-bs-toggle="modal" data-bs-target="#modalBulkDelete">
    Bulk Delete
  </button>

  <span class="ms-auto text-muted align-self-center">
    Selected: <b id="selectedCount">0</b>
  </span>
</div>

<div class="table-responsive">
  <table class="table table-bordered table-sm align-middle">
    <thead class="table-light">
      <tr>
        <th width="40" class="text-center"><input type="checkbox" id="checkAll"></th>
        <th width="50" class="text-center">No</th>
        <th>Machine</th>

        <th class="text-end" style="width:140px;">CT Mesin (sec)</th>
        <th class="text-end" style="width:140px;">CT Produk DC (sec)</th>
        <th class="text-end" style="width:160px;">CT Produk Machining (sec)</th>

        <th>Product</th>

        <th class="text-end" style="width:120px;">Ascas (gr)</th>
        <th class="text-end" style="width:120px;">Runner (gr)</th>
        <th class="text-end" style="width:150px;">Die Casting (gr)</th>
        <th class="text-end" style="width:150px;">Machining (gr)</th>

        <th width="140">Aksi</th>
      </tr>
    </thead>
    <tbody>
    <?php
      $page    = $pager->getCurrentPage('standards') ?? 1;
      $perPage = $pager->getPerPage('standards') ?? 15;
      $no = 1 + ($page - 1) * $perPage;
    ?>

    <?php if (!empty($standards)): ?>
      <?php foreach ($standards as $s): ?>
        <tr>
          <td class="text-center">
            <input type="checkbox" class="rowCheck" value="<?= (int)$s['id'] ?>"
                   data-machine-id="<?= (int)($s['machine_id'] ?? 0) ?>"
                   data-product-id="<?= (int)($s['product_id'] ?? 0) ?>"
                   data-cycle="<?= (int)($s['cycle_time_sec'] ?? 0) ?>">
          </td>

          <td class="text-center"><?= $no++ ?></td>
          <td><?= esc($s['machine_code'] ?? '-') ?></td>

          <td class="text-end"><?= (int)($s['cycle_time_sec'] ?? 0) ?></td>
          <td class="text-end"><?= (int)($s['cycle_time_die_casting_sec'] ?? 0) ?></td>
          <td class="text-end"><?= (int)($s['cycle_time_machining_sec'] ?? 0) ?></td>

          <td><?= esc($s['part_no'] ?? '-') ?> - <?= esc($s['part_name'] ?? '-') ?></td>

          <td class="text-end"><?= number_format((float)($s['weight_ascas'] ?? 0), 0) ?></td>
          <td class="text-end"><?= number_format((float)($s['weight_runner'] ?? 0), 0) ?></td>
          <td class="text-end"><?= number_format((float)($s['weight_die_casting'] ?? 0), 0) ?></td>
          <td class="text-end"><?= number_format((float)($s['weight_machining'] ?? 0), 0) ?></td>

          <td class="d-flex gap-1">
            <button type="button"
                    class="btn btn-sm btn-warning btnEdit"
                    data-bs-toggle="modal"
                    data-bs-target="#modalEditStandard"
                    data-id="<?= (int)$s['id'] ?>"
                    data-machine-id="<?= (int)($s['machine_id'] ?? 0) ?>"
                    data-product-id="<?= (int)($s['product_id'] ?? 0) ?>"
                    data-cycle="<?= (int)($s['cycle_time_sec'] ?? 0) ?>">
              Edit
            </button>

            <a href="/master/production-standard/delete/<?= (int)$s['id'] ?>"
               class="btn btn-sm btn-danger"
               onclick="return confirm('Hapus standard ini?')">
               Hapus
            </a>
          </td>
        </tr>
      <?php endforeach ?>
    <?php else: ?>
      <tr>
        <td colspan="12" class="text-center text-muted">Tidak ada data</td>
      </tr>
    <?php endif ?>
    </tbody>
  </table>
</div>

<div class="d-flex justify-content-end">
  <?= $pager->links('standards', 'bootstrap_pagination') ?>
</div>

<?php
// Buat HTML option product dengan data CT
$productOptionsHtml = '';
foreach ($products as $p) {
  $pid = (int)$p['id'];
  $ctDc = (int)($p['cycle_time'] ?? 0);
  $ctMc = (int)($p['cycle_time_machining'] ?? 0);
  $label = esc(($p['part_no'] ?? '-') . ' - ' . ($p['part_name'] ?? '-'), 'attr');
  $productOptionsHtml .= "<option value=\"{$pid}\" data-ct-dc=\"{$ctDc}\" data-ct-mc=\"{$ctMc}\">{$label}</option>";
}
?>

<!-- ===================== MODAL ADD SINGLE ===================== -->
<div class="modal fade" id="modalAddStandard" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" action="/master/production-standard/store" id="formAddStandard">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Tambah Production Standard</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3 mb-3">
            <div class="col-md-12">
              <label class="form-label">Machine</label>
              <select name="machine_id" id="add_machine_id" class="form-select" required>
                <option value="">-- pilih machine --</option>
                <?php foreach ($machines as $m): ?>
                  <option value="<?= (int)$m['id'] ?>"
                          data-line="<?= esc($m['production_line'] ?? '', 'attr') ?>">
                    <?= esc($m['machine_code'] ?? '-') ?> (<?= esc($m['production_line'] ?? '-') ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted d-block mt-1" id="add_machine_hint"></small>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width:60px;" class="text-center">No</th>
                  <th>Product</th>
                  <th style="width:180px;" class="text-end">CT Produk DC</th>
                  <th style="width:200px;" class="text-end">CT Produk Machining</th>
                  <th style="width:220px;" class="text-end">CT Mesin (sec)</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="text-center">1</td>
                  <td>
                    <select name="product_id" id="add_product_id" class="form-select js-product" required>
                      <option value="">-- pilih product --</option>
                      <?= $productOptionsHtml ?>
                    </select>
                  </td>

                  <td>
                    <input type="number" id="add_ct_dc" class="form-control text-end bg-light" readonly value="0">
                  </td>

                  <td>
                    <input type="number" id="add_ct_mc" class="form-control text-end bg-light" readonly value="0">
                  </td>

                  <td>
                    <input type="number" name="cycle_time_sec" id="add_cycle_time_sec"
                           class="form-control text-end" min="0" value="0">
                    <small class="text-muted d-block mt-1" id="add_ct_hint"></small>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <small class="text-muted d-block mt-2">
            CT Produk (DC & Machining) otomatis dari master product. Standard menyimpan CT mesin + snapshot CT produk.
          </small>
        </div>

        <div class="modal-footer">
          <button class="btn btn-primary" type="submit">Simpan</button>
          <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Tutup</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== MODAL EDIT SINGLE ===================== -->
<div class="modal fade" id="modalEditStandard" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" id="formEditStandard" action="">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Edit Production Standard</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3 mb-3">
            <div class="col-md-12">
              <label class="form-label">Machine</label>
              <select name="machine_id" id="edit_machine_id" class="form-select" required>
                <option value="">-- pilih machine --</option>
                <?php foreach ($machines as $m): ?>
                  <option value="<?= (int)$m['id'] ?>"
                          data-line="<?= esc($m['production_line'] ?? '', 'attr') ?>">
                    <?= esc($m['machine_code'] ?? '-') ?> (<?= esc($m['production_line'] ?? '-') ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted d-block mt-1" id="edit_machine_hint"></small>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width:60px;" class="text-center">No</th>
                  <th>Product</th>
                  <th style="width:180px;" class="text-end">CT Produk DC</th>
                  <th style="width:200px;" class="text-end">CT Produk Machining</th>
                  <th style="width:220px;" class="text-end">CT Mesin (sec)</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="text-center">1</td>
                  <td>
                    <select name="product_id" id="edit_product_id" class="form-select js-product" required>
                      <option value="">-- pilih product --</option>
                      <?= $productOptionsHtml ?>
                    </select>
                  </td>

                  <td>
                    <input type="number" id="edit_ct_dc" class="form-control text-end bg-light" readonly value="0">
                  </td>

                  <td>
                    <input type="number" id="edit_ct_mc" class="form-control text-end bg-light" readonly value="0">
                  </td>

                  <td>
                    <input type="number" name="cycle_time_sec" id="edit_cycle_time_sec"
                           class="form-control text-end" min="0" value="0">
                    <small class="text-muted d-block mt-1" id="edit_ct_hint"></small>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <small class="text-muted d-block mt-2">
            CT Produk otomatis dari master product (akan tersimpan sebagai snapshot saat update).
          </small>
        </div>

        <div class="modal-footer">
          <button class="btn btn-primary" type="submit">Update</button>
          <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Tutup</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== MODAL BULK ADD ===================== -->
<div class="modal fade" id="modalBulkStandard" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" action="/master/production-standard/bulk-store" id="formBulkStandard">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Bulk Add Production Standards</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Machine (1 machine untuk semua row)</label>
              <select name="machine_id" id="bulk_machine_id" class="form-select" required>
                <option value="">-- pilih machine --</option>
                <?php foreach ($machines as $m): ?>
                  <option value="<?= (int)$m['id'] ?>"
                          data-line="<?= esc($m['production_line'] ?? '', 'attr') ?>">
                    <?= esc($m['machine_code'] ?? '-') ?> (<?= esc($m['production_line'] ?? '-') ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted" id="bulk_machine_hint"></small>
            </div>

            <div class="col-md-6 d-flex align-items-end justify-content-end">
              <button type="button" class="btn btn-outline-primary" id="btnAddBulkRow">+ tambah baris</button>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle" id="bulkTable">
              <thead class="table-light">
                <tr>
                  <th width="50" class="text-center">No</th>
                  <th>Product</th>
                  <th width="160" class="text-end">CT Produk DC</th>
                  <th width="190" class="text-end">CT Produk Machining</th>
                  <th width="220" class="text-end">CT Mesin (sec)</th>
                  <th width="80" class="text-center">Aksi</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>

          <small class="text-muted">
            CT Produk otomatis dari master product. CT mesin: Die Casting auto 0 (readonly), Machining wajib >0.
          </small>
        </div>

        <div class="modal-footer">
          <button class="btn btn-primary" type="submit">Simpan Bulk</button>
          <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Tutup</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== MODAL BULK EDIT ===================== -->
<div class="modal fade" id="modalBulkEdit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" action="/master/production-standard/bulk-update" id="formBulkEdit">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Bulk Edit Production Standards</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="alert alert-info py-2">
            Data terpilih: <b id="bulkEditCount">0</b>
          </div>

          <div id="bulkEditIds"></div>

          <div class="row g-3">
            <div class="col-md-12">
              <label class="form-label">Pindah Machine (optional)</label>
              <select name="machine_id" id="bulk_edit_machine_id" class="form-select">
                <option value="">-- tidak diubah --</option>
                <?php foreach ($machines as $m): ?>
                  <option value="<?= (int)$m['id'] ?>"
                          data-line="<?= esc($m['production_line'] ?? '', 'attr') ?>">
                    <?= esc($m['machine_code'] ?? '-') ?> (<?= esc($m['production_line'] ?? '-') ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted" id="bulk_edit_machine_hint"></small>
            </div>

            <div class="col-md-12">
              <label class="form-label">Set CT Mesin (sec) (optional)</label>
              <input type="number" name="cycle_time_sec" id="bulk_edit_cycle"
                     class="form-control" min="0" placeholder="Kosongkan jika tidak ubah CT mesin">
              <small class="text-muted" id="bulk_edit_ct_hint"></small>
            </div>
          </div>

          <small class="text-muted d-block mt-3">
            Bulk edit ini fokus CT mesin / pindah machine. CT produk akan direfresh dari master product.
          </small>
        </div>

        <div class="modal-footer">
          <button class="btn btn-primary" type="submit">Simpan Bulk Edit</button>
          <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Tutup</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== MODAL BULK DELETE ===================== -->
<div class="modal fade" id="modalBulkDelete" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" action="/master/production-standard/bulk-delete" id="formBulkDelete">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Bulk Delete</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <p>Yakin ingin menghapus <b id="bulkDeleteCount">0</b> data terpilih?</p>
          <div id="bulkDeleteIds"></div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-danger" type="submit">Ya, Hapus</button>
          <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  function isDieCastingFromSelect(selectEl){
    const opt = selectEl?.options?.[selectEl.selectedIndex];
    const line = (opt?.dataset?.line || '').toLowerCase().trim();
    return line === 'die casting';
  }

  function getSelectedOption(selectEl){
    return selectEl?.options?.[selectEl.selectedIndex] || null;
  }

  function applyProductCycleToFields(selectEl, ctDcEl, ctMcEl){
    const opt = getSelectedOption(selectEl);
    const dc = parseInt(opt?.dataset?.ctDc || '0', 10) || 0;
    const mc = parseInt(opt?.dataset?.ctMc || '0', 10) || 0;
    if (ctDcEl) ctDcEl.value = dc;
    if (ctMcEl) ctMcEl.value = mc;
  }

  function initSelect2InModal(modalEl){
    $(modalEl).find('.js-product').select2({
      dropdownParent: $(modalEl),
      width: '100%',
      placeholder: '-- pilih product --',
      allowClear: true
    });
  }

  $('#modalAddStandard').on('shown.bs.modal', function(){ initSelect2InModal(this); });
  $('#modalEditStandard').on('shown.bs.modal', function(){ initSelect2InModal(this); });

  // ===== selection =====
  const checkAll = document.getElementById('checkAll');
  const selectedCountEl = document.getElementById('selectedCount');
  const btnBulkEdit = document.getElementById('btnOpenBulkEdit');
  const btnBulkDelete = document.getElementById('btnOpenBulkDelete');

  function rowChecks(){ return document.querySelectorAll('.rowCheck'); }
  function getSelectedIds(){
    const ids = [];
    rowChecks().forEach(ch=>{ if(ch.checked) ids.push(parseInt(ch.value,10)); });
    return ids.filter(Boolean);
  }
  function syncSelectionUI(){
    const ids = getSelectedIds();
    selectedCountEl.textContent = ids.length;
    btnBulkEdit.disabled = ids.length === 0;
    btnBulkDelete.disabled = ids.length === 0;

    const total = rowChecks().length;
    const checked = ids.length;
    if (checkAll){
      checkAll.checked = (total > 0 && checked === total);
      checkAll.indeterminate = (checked > 0 && checked < total);
    }
  }
  if(checkAll){
    checkAll.addEventListener('change', ()=>{
      rowChecks().forEach(ch=> ch.checked = checkAll.checked);
      syncSelectionUI();
    });
  }
  rowChecks().forEach(ch=> ch.addEventListener('change', syncSelectionUI));
  syncSelectionUI();

  // ===== ADD rules =====
  const addMachine = document.getElementById('add_machine_id');
  const addCt = document.getElementById('add_cycle_time_sec');
  const addCtHint = document.getElementById('add_ct_hint');
  const addMachineHint = document.getElementById('add_machine_hint');

  const addProduct = document.getElementById('add_product_id');
  const addCtDc = document.getElementById('add_ct_dc');
  const addCtMc = document.getElementById('add_ct_mc');

  function syncAddCt(){
    if(!addMachine || !addCt) return;
    const isDC = isDieCastingFromSelect(addMachine);

    if (addMachineHint){
      addMachineHint.textContent = isDC ? 'Die Casting dipilih.' : 'Machining dipilih.';
    }

    if (isDC){
      addCt.value = 0;
      addCt.readOnly = true;
      addCt.classList.add('bg-light');
      addCtHint.textContent = 'Die Casting: CT mesin auto 0.';
    } else {
      addCt.readOnly = false;
      addCt.classList.remove('bg-light');
      addCtHint.textContent = 'Machining: isi CT mesin (>0).';
    }
  }

  addMachine?.addEventListener('change', syncAddCt);
  syncAddCt();

  addProduct?.addEventListener('change', ()=> applyProductCycleToFields(addProduct, addCtDc, addCtMc));
  applyProductCycleToFields(addProduct, addCtDc, addCtMc);

  // ===== EDIT fill =====
  const editForm = document.getElementById('formEditStandard');
  const editMachine = document.getElementById('edit_machine_id');
  const editCt = document.getElementById('edit_cycle_time_sec');
  const editCtHint = document.getElementById('edit_ct_hint');
  const editMachineHint = document.getElementById('edit_machine_hint');

  const editProduct = document.getElementById('edit_product_id');
  const editCtDc = document.getElementById('edit_ct_dc');
  const editCtMc = document.getElementById('edit_ct_mc');

  function syncEditCt(){
    if(!editMachine || !editCt) return;
    const isDC = isDieCastingFromSelect(editMachine);

    if (editMachineHint){
      editMachineHint.textContent = isDC ? 'Die Casting dipilih.' : 'Machining dipilih.';
    }

    if (isDC){
      editCt.value = 0;
      editCt.readOnly = true;
      editCt.classList.add('bg-light');
      editCtHint.textContent = 'Die Casting: CT mesin auto 0.';
    } else {
      editCt.readOnly = false;
      editCt.classList.remove('bg-light');
      editCtHint.textContent = 'Machining: isi CT mesin (>0).';
    }
  }

  editMachine?.addEventListener('change', syncEditCt);
  editProduct?.addEventListener('change', ()=> applyProductCycleToFields(editProduct, editCtDc, editCtMc));

  document.querySelectorAll('.btnEdit').forEach(btn=>{
    btn.addEventListener('click', function(){
      const id  = this.dataset.id;
      const mid = this.dataset.machineId;
      const pid = this.dataset.productId;
      const ct  = this.dataset.cycle;

      editForm.action = '/master/production-standard/update/' + id;

      editMachine.value = mid || '';
      editCt.value = ct || 0;

      editProduct.value = pid || '';
      $(editProduct).trigger('change');

      syncEditCt();
      applyProductCycleToFields(editProduct, editCtDc, editCtMc);
    });
  });

  // ===== BULK ADD =====
  const bulkMachine = document.getElementById('bulk_machine_id');
  const bulkMachineHint = document.getElementById('bulk_machine_hint');

  function syncBulkRowCtReadOnly(){
    const isDC = isDieCastingFromSelect(bulkMachine);
    if (bulkMachineHint){
      bulkMachineHint.textContent = isDC ? 'Die Casting dipilih.' : 'Machining dipilih.';
    }

    document.querySelectorAll('.bulk-ct-machine').forEach(inp=>{
      if (isDC){
        inp.value = 0;
        inp.readOnly = true;
        inp.classList.add('bg-light');
      } else {
        inp.readOnly = false;
        inp.classList.remove('bg-light');
      }
    });
  }
  bulkMachine?.addEventListener('change', syncBulkRowCtReadOnly);

  const bulkTbody = document.querySelector('#bulkTable tbody');
  const btnAddRow = document.getElementById('btnAddBulkRow');

  function initSelect2ForBulkRow(selectEl){
    $(selectEl).select2({
      dropdownParent: $('#modalBulkStandard'),
      width: '100%',
      placeholder: '-- pilih product --',
      allowClear: true
    });
  }

  function renumberRows(){
    [...bulkTbody.querySelectorAll('tr')].forEach((tr, idx)=>{
      tr.querySelector('.row-no').textContent = idx+1;

      const sel = tr.querySelector('select.bulk-product');
      const ctMachine = tr.querySelector('input.bulk-ct-machine');

      if(sel) sel.name = `rows[${idx}][product_id]`;
      if(ctMachine) ctMachine.name  = `cycle_time_sec`; // 1 CT mesin untuk semua row (sesuai backend)
    });
  }

  function addBulkRow(){
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="text-center row-no">1</td>
      <td>
        <select class="form-select form-select-sm bulk-product js-product-bulk" required>
          <option value="">-- pilih product --</option>
          <?= $productOptionsHtml ?>
        </select>
      </td>
      <td>
        <input type="number" class="form-control form-control-sm text-end bg-light bulk-ct-dc" readonly value="0">
      </td>
      <td>
        <input type="number" class="form-control form-control-sm text-end bg-light bulk-ct-mc" readonly value="0">
      </td>
      <td>
        <input type="number" class="form-control form-control-sm text-end bulk-ct-machine" min="0" value="0">
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-danger btnDelRow">Hapus</button>
      </td>
    `;
    bulkTbody.appendChild(tr);

    const sel = tr.querySelector('select.bulk-product');
    const ctDc = tr.querySelector('.bulk-ct-dc');
    const ctMc = tr.querySelector('.bulk-ct-mc');

    initSelect2ForBulkRow(sel);

    sel.addEventListener('change', ()=> applyProductCycleToFields(sel, ctDc, ctMc));

    tr.querySelector('.btnDelRow').addEventListener('click', ()=>{
      $(sel).select2('destroy');
      tr.remove();
      renumberRows();
    });

    renumberRows();
    syncBulkRowCtReadOnly();
  }

  btnAddRow?.addEventListener('click', addBulkRow);

  $('#modalBulkStandard').on('shown.bs.modal', function(){
    if (bulkTbody.children.length === 0) addBulkRow();
    syncBulkRowCtReadOnly();
  });

  // ===== bulk edit/delete inject ids =====
  const bulkEditIdsWrap = document.getElementById('bulkEditIds');
  const bulkEditCount = document.getElementById('bulkEditCount');
  const bulkDeleteIdsWrap = document.getElementById('bulkDeleteIds');
  const bulkDeleteCount = document.getElementById('bulkDeleteCount');

  function fillBulkEditIds(){
    const ids = getSelectedIds();
    bulkEditCount.textContent = ids.length;
    bulkEditIdsWrap.innerHTML = ids.map(id => `<input type="hidden" name="ids[]" value="${id}">`).join('');
  }
  function fillBulkDeleteIds(){
    const ids = getSelectedIds();
    bulkDeleteCount.textContent = ids.length;
    bulkDeleteIdsWrap.innerHTML = ids.map(id => `<input type="hidden" name="ids[]" value="${id}">`).join('');
  }

  $('#modalBulkEdit').on('shown.bs.modal', fillBulkEditIds);
  $('#modalBulkDelete').on('shown.bs.modal', fillBulkDeleteIds);

})();
</script>

<?= $this->endSection() ?>
