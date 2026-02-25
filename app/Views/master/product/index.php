<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
  /* =========================================================
     MODAL SCROLL FIX (HP / SMALL RES) - MASTER PRODUCT
     - pakai class modal-prod
     - pakai modal-dialog-scrollable (Bootstrap)
     - modal-content flex + max-height
     - modal-body overflow auto
     ========================================================= */

  /* offset supaya modal tidak ketutup navbar */
  .modal.modal-prod{
    padding-top: 80px;
  }
  @media (max-width: 768px){
    .modal.modal-prod{ padding-top: 65px; }
  }

  .modal.modal-prod .modal-dialog{
    max-width: 980px;
    margin: .5rem auto; /* biar ruang body cukup */
  }

  /* max-height pakai dvh agar stabil di mobile */
  .modal.modal-prod .modal-dialog.modal-dialog-scrollable .modal-content{
    max-height: calc(100dvh - 110px);
    display: flex;
    flex-direction: column;
    overflow: scroll;   /* body yang scroll, header/footer tetap */
    background: #fff;
    border-radius: 12px;
  }
  @supports not (height: 100dvh) {
    .modal.modal-prod .modal-dialog.modal-dialog-scrollable .modal-content{
      max-height: calc(100vh - 110px);
    }
  }

  .modal.modal-prod .modal-header,
  .modal.modal-prod .modal-footer{
    background: #fff !important;
  }

  /* body benar-benar scroll */
  .modal.modal-prod .modal-body{
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    flex: 1 1 auto;
    padding-bottom: 24px; /* aman supaya tidak mentok footer */
  }

  /* footer tetap solid */
  .modal.modal-prod .modal-footer{
    border-top: 1px solid rgba(0,0,0,.1);
    box-shadow: 0 -6px 16px rgba(0,0,0,.06);
    flex: 0 0 auto;
  }

  /* =========================================================
     FORM GRID
     ========================================================= */
  .modal .form-wrap{ max-width: 760px; margin-left: 0; margin-right: auto; }

  .form-grid .row-item{
    display:grid;
    grid-template-columns: 220px 1fr;
    gap: 14px;
    align-items:center;
    margin-bottom: 10px;
  }

  @media (max-width: 576px){
    .form-grid .row-item{
      grid-template-columns: 1fr;
      gap: 6px;
    }
  }

  .form-grid label{ font-weight: 600; margin: 0; }
  .req{ color:#dc3545; font-weight:700; margin-left:4px; }

  /* =========================================================
     TABLE
     ========================================================= */
  .table-wrap{
    overflow:auto;
    border:1px solid #e7e7e7;
    border-radius: 12px;
  }
  table.product-table{
    min-width: 1700px;
    margin-bottom:0;
  }
  table.product-table thead th{
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f8f9fa;
    border-bottom: 1px solid #ddd;
    white-space: nowrap;
    vertical-align: middle;
  }
  table.product-table td{ vertical-align: middle; white-space: nowrap; }
  table.product-table tbody tr:hover td{ background: #fffbe0; }

  .num{ text-align:right; font-variant-numeric: tabular-nums; }
  .ctr{ text-align:center; font-variant-numeric: tabular-nums; }
  .name-col{ min-width: 260px; }
  .cust-col{ min-width: 220px; }
  .prod-col{ min-width: 180px; }

  .modal-backdrop{ opacity: .5; }
</style>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="mb-0">Master Product</h4>
    <small class="text-muted">Kelola master produk (part, customer, parameter produksi)</small>
  </div>

  <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAddProduct">
    <i class="bi bi-plus"></i> Tambah Product
  </button>
</div>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= esc(session()->getFlashdata('success')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= esc(session()->getFlashdata('error')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<!-- FILTER CARD -->
<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get" action="/master/product" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label mb-1">Cari (Part No / Name)</label>
        <input type="text" name="keyword" class="form-control"
               placeholder="contoh: 12345 / Gear" value="<?= esc($keyword ?? '') ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label mb-1">Customer</label>
        <select name="customer_id" class="form-select">
          <option value="">-- Semua Customer --</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (($customerId ?? '') == $c['id']) ? 'selected' : '' ?>>
              <?= esc($c['customer_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label mb-1">Tampilkan</label>
        <select name="perPage" class="form-select" onchange="this.form.submit()">
          <?php foreach (($perPageOptions ?? [10,15,25,50,100]) as $opt): ?>
            <option value="<?= (int)$opt ?>" <?= ((int)($perPage ?? 15) === (int)$opt) ? 'selected' : '' ?>>
              <?= (int)$opt ?> data
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-search"></i> Filter
        </button>
        <a href="/master/product" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-counterclockwise"></i> Reset
        </a>
      </div>
    </form>
  </div>
</div>

<!-- TABLE CARD -->
<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-wrap">
      <table class="table table-striped table-hover product-table align-middle">
        <thead>
          <tr>
            <th style="width:70px;" class="ctr">No</th>
            <th style="width:140px;">Part No (Accurate)</th>
            <th class="prod-col">Part Prod</th>
            <th class="name-col">Product Name</th>
            <th class="cust-col">Customer</th>
            <th style="width:120px;" class="num">Ascas (gr)</th>
            <th style="width:120px;" class="num">Runner (gr)</th>
            <th style="width:140px;" class="num">Die Casting (gr)</th>
            <th style="width:140px;" class="num">Machining (gr)</th>
            <th style="width:110px;" class="ctr">CT DC (sec)</th>
            <th style="width:130px;" class="ctr">CT Mach (sec)</th>
            <th style="width:90px;" class="ctr">Cavity</th>
            <th style="width:110px;" class="ctr">Eff (%)</th>
            <th style="width:200px;" class="num">Aksi</th>
          </tr>
        </thead>

        <tbody>
        <?php
          $page    = (int)($pager->getCurrentPage('products') ?? 1);
          $perP    = (int)($pager->getPerPage('products') ?? (int)($perPage ?? 15));
          $no      = 1 + (($page - 1) * $perP);
        ?>

        <?php if (!empty($products) && count($products) > 0): ?>
          <?php foreach ($products as $p): ?>
            <?php
              $asc = (float)($p['weight_ascas'] ?? 0);
              $run = (float)($p['weight_runner'] ?? 0);
              $die = $asc + $run;
            ?>
            <tr id="row-<?= (int)$p['id'] ?>">
              <td class="ctr"><?= $no++ ?></td>
              <td class="fw-semibold"><?= esc($p['part_no'] ?? '') ?></td>
              <td><?= esc($p['part_prod'] ?? '') ?></td>
              <td><?= esc($p['part_name'] ?? '') ?></td>
              <td><?= esc($p['customer_name'] ?? '-') ?></td>
              <td class="num"><?= number_format($asc, 0) ?></td>
              <td class="num"><?= number_format($run, 0) ?></td>
              <td class="num"><?= number_format($die, 0) ?></td>
              <td class="num"><?= number_format((float)($p['weight_machining'] ?? 0), 0) ?></td>
              <td class="ctr"><?= esc($p['cycle_time'] ?? 0) ?></td>
              <td class="ctr"><?= esc($p['cycle_time_machining'] ?? 0) ?></td>
              <td class="ctr"><?= esc($p['cavity'] ?? 0) ?></td>
              <td class="ctr"><?= esc($p['efficiency_rate'] ?? 0) ?></td>

              <td class="num">
                <button
                  type="button"
                  class="btn btn-sm btn-warning btnEditProduct"
                  data-bs-toggle="modal"
                  data-bs-target="#modalEditProduct"
                  data-id="<?= esc($p['id'] ?? '', 'attr') ?>"
                  data-part_no="<?= esc($p['part_no'] ?? '', 'attr') ?>"
                  data-part_prod="<?= esc($p['part_prod'] ?? '', 'attr') ?>"
                  data-part_name="<?= esc($p['part_name'] ?? '', 'attr') ?>"
                  data-customer_id="<?= esc($p['customer_id'] ?? '', 'attr') ?>"
                  data-weight_ascas="<?= esc($p['weight_ascas'] ?? 0, 'attr') ?>"
                  data-weight_runner="<?= esc($p['weight_runner'] ?? 0, 'attr') ?>"
                  data-weight_machining="<?= esc($p['weight_machining'] ?? 0, 'attr') ?>"
                  data-cycle_time="<?= esc($p['cycle_time'] ?? 0, 'attr') ?>"
                  data-cycle_time_machining="<?= esc($p['cycle_time_machining'] ?? 0, 'attr') ?>"
                  data-cavity="<?= esc($p['cavity'] ?? 0, 'attr') ?>"
                  data-efficiency_rate="<?= esc($p['efficiency_rate'] ?? 0, 'attr') ?>"
                >
                  <i class="bi bi-pencil"></i> Edit
                </button>

                <a href="/master/product/delete/<?= (int)$p['id'] ?>"
                   class="btn btn-sm btn-danger"
                   onclick="return confirm('Hapus product ini?')">
                  <i class="bi bi-trash"></i> Hapus
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="14" class="text-center text-muted py-4">Tidak ada data.</td>
          </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card-footer bg-white d-flex justify-content-end">
    <?= $pager->links('products', 'bootstrap_pagination') ?>
  </div>
</div>

<!-- ========================= MODAL: ADD PRODUCT ========================= -->
<div class="modal fade modal-prod" id="modalAddProduct" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Tambah Product</h5>
          <small class="text-muted">Field bertanda <span class="req">*</span> wajib diisi</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form method="post" action="/master/product/store" id="formAddProduct">
        <?= csrf_field() ?>

        <div class="modal-body">
          <div class="form-wrap form-grid">

            <div class="row-item">
              <label>Part No <span class="req">*</span></label>
              <input type="text" name="part_no" class="form-control" required>
            </div>

            <div class="row-item">
              <label>Part Prod</label>
              <input type="text" name="part_prod" class="form-control" placeholder="contoh: PROD-001">
            </div>

            <div class="row-item">
              <label>Product Name <span class="req">*</span></label>
              <input type="text" name="part_name" class="form-control" required>
            </div>

            <div class="row-item">
              <label>Customer <span class="req">*</span></label>
              <select name="customer_id" class="form-select" required>
                <option value="">-- Pilih Customer --</option>
                <?php foreach ($customers as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"><?= esc($c['customer_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="row-item">
              <label>Ascas (gr)</label>
              <input type="number" step="0.01" name="weight_ascas" class="form-control" value="0" id="add_weight_ascas">
            </div>

            <div class="row-item">
              <label>Runner (gr)</label>
              <input type="number" step="0.01" name="weight_runner" class="form-control" value="0" id="add_weight_runner">
            </div>

            <div class="row-item">
              <label>Weight Shot (Die Cast)</label>
              <div>
                <input type="number" step="0.01" class="form-control" id="add_weight_die_casting" value="0" readonly>
                <small class="text-muted">Auto = Ascas + Runner</small>
              </div>
            </div>

            <div class="row-item">
              <label>Weight (Machining)</label>
              <input type="number" step="0.01" name="weight_machining" class="form-control" value="0">
            </div>

            <div class="row-item">
              <label>CT DC (sec)</label>
              <input type="number" step="1" name="cycle_time" class="form-control" value="0">
            </div>

            <div class="row-item">
              <label>CT Machining (sec)</label>
              <input type="number" step="1" name="cycle_time_machining" class="form-control" value="0">
            </div>

            <div class="row-item">
              <label>Cavity</label>
              <input type="number" step="1" name="cavity" class="form-control" value="0">
            </div>

            <div class="row-item">
              <label>Eff (%)</label>
              <input type="number" step="0.01" name="efficiency_rate" class="form-control" value="100">
            </div>

          </div>
        </div>

        <div class="modal-footer d-flex justify-content-end gap-2">
          <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Simpan</button>
          <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Tutup</button>
        </div>
      </form>

    </div>
  </div>
</div>

<!-- ========================= MODAL: EDIT PRODUCT ========================= -->
<div class="modal fade modal-prod" id="modalEditProduct" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Edit Product</h5>
          <small class="text-muted">Field bertanda <span class="req">*</span> wajib diisi</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form method="post" id="formEditProduct" action="">
        <?= csrf_field() ?>

        <div class="modal-body">
          <div class="form-wrap form-grid">

            <div class="row-item">
              <label>Part No <span class="req">*</span></label>
              <input type="text" id="edit_part_no" name="part_no" class="form-control" required>
            </div>

            <div class="row-item">
              <label>Part Prod</label>
              <input type="text" id="edit_part_prod" name="part_prod" class="form-control">
            </div>

            <div class="row-item">
              <label>Product Name <span class="req">*</span></label>
              <input type="text" id="edit_part_name" name="part_name" class="form-control" required>
            </div>

            <div class="row-item">
              <label>Customer <span class="req">*</span></label>
              <select id="edit_customer_id" name="customer_id" class="form-select" required>
                <option value="">-- Pilih Customer --</option>
                <?php foreach ($customers as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"><?= esc($c['customer_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="row-item">
              <label>Ascas (gr)</label>
              <input type="number" step="0.01" id="edit_weight_ascas" name="weight_ascas" class="form-control" value="0">
            </div>

            <div class="row-item">
              <label>Runner (gr)</label>
              <input type="number" step="0.01" id="edit_weight_runner" name="weight_runner" class="form-control" value="0">
            </div>

            <div class="row-item">
              <label>Weight Shot (Die Cast)</label>
              <div>
                <input type="number" step="0.01" id="edit_weight_die_casting" class="form-control" value="0" readonly>
                <small class="text-muted">Auto = Ascas + Runner</small>
              </div>
            </div>

            <div class="row-item">
              <label>Weight (Machining)</label>
              <input type="number" step="0.01" id="edit_weight_machining" name="weight_machining" class="form-control" value="0">
            </div>

            <div class="row-item">
              <label>CT DC (sec)</label>
              <input type="number" step="1" id="edit_cycle_time" name="cycle_time" class="form-control" value="0">
            </div>

            <div class="row-item">
              <label>CT Machining (sec)</label>
              <input type="number" step="1" id="edit_cycle_time_machining" name="cycle_time_machining" class="form-control" value="0">
            </div>

            <div class="row-item">
              <label>Cavity</label>
              <input type="number" step="1" id="edit_cavity" name="cavity" class="form-control" value="0">
            </div>

            <div class="row-item">
              <label>Eff (%)</label>
              <input type="number" step="0.01" id="edit_efficiency_rate" name="efficiency_rate" class="form-control" value="100">
            </div>

          </div>
        </div>

        <div class="modal-footer d-flex justify-content-end gap-2">
          <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Update</button>
          <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Tutup</button>
        </div>
      </form>

    </div>
  </div>
</div>

<script>
  function calcDieCasting(ascasEl, runnerEl, outEl){
    const a = parseFloat(ascasEl.value || 0);
    const r = parseFloat(runnerEl.value || 0);
    outEl.value = (a + r).toFixed(2);
  }

  document.addEventListener('DOMContentLoaded', function () {
    const editForm = document.getElementById('formEditProduct');

    // ADD
    const addAscas  = document.getElementById('add_weight_ascas');
    const addRunner = document.getElementById('add_weight_runner');
    const addDie    = document.getElementById('add_weight_die_casting');

    [addAscas, addRunner].forEach(el => {
      if (!el) return;
      el.addEventListener('input', () => calcDieCasting(addAscas, addRunner, addDie));
    });
    if (addAscas && addRunner && addDie) calcDieCasting(addAscas, addRunner, addDie);

    // EDIT
    function bindEditButtons() {
      document.querySelectorAll('.btnEditProduct').forEach(btn => {
        btn.onclick = function () {
          const d = this.dataset;
          editForm.setAttribute('action', '/master/product/update/' + d.id);

          document.getElementById('edit_part_no').value = d.part_no || '';
          document.getElementById('edit_part_prod').value = d.part_prod || '';
          document.getElementById('edit_part_name').value = d.part_name || '';
          document.getElementById('edit_customer_id').value = d.customer_id || '';

          const ea = document.getElementById('edit_weight_ascas');
          const er = document.getElementById('edit_weight_runner');
          const ed = document.getElementById('edit_weight_die_casting');

          ea.value = d.weight_ascas || 0;
          er.value = d.weight_runner || 0;
          calcDieCasting(ea, er, ed);

          document.getElementById('edit_weight_machining').value = d.weight_machining || 0;
          document.getElementById('edit_cycle_time').value = d.cycle_time || 0;
          document.getElementById('edit_cycle_time_machining').value = d.cycle_time_machining || 0;
          document.getElementById('edit_cavity').value = d.cavity || 0;
          document.getElementById('edit_efficiency_rate').value = d.efficiency_rate || 0;

          ea.oninput = () => calcDieCasting(ea, er, ed);
          er.oninput = () => calcDieCasting(ea, er, ed);
        };
      });
    }

    bindEditButtons();
  });
</script>

<?= $this->endSection() ?>
