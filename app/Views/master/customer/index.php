<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
  .modal .modal-dialog{ max-width: 760px; margin-top: 90px !important; }
  @media (max-width: 768px){ .modal .modal-dialog{ margin-top: 70px !important; } }

  .modal .modal-content{ max-height: calc(100vh - 120px); }
  .modal .modal-body{ overflow: auto; padding-bottom: 12px; }
  .modal .modal-footer{
    position: sticky;
    bottom: 0;
    background: #fff;
    z-index: 2;
    border-top: 1px solid rgba(0,0,0,.1);
  }

  .modal .form-wrap{ max-width: 680px; margin-left: 0; margin-right: auto; }

  .form-grid .row-item{
    display: grid;
    grid-template-columns: 220px 1fr;
    gap: 14px;
    align-items: center;
    margin-bottom: 10px;
  }
  .form-grid label{ font-weight: 600; margin: 0; }
  .form-grid .form-control, .form-grid .form-select{ width: 100%; }
  .table td, .table th { vertical-align: middle; }
</style>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="mb-0">Master Customer</h4>
    <small class="text-muted">Kelola data customer / pelanggan</small>
  </div>

  <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAddCustomer">
    <i class="bi bi-plus"></i> Tambah Customer
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

<?php $errors = session()->getFlashdata('errors'); ?>
<?php if (!empty($errors)): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong>Validasi gagal:</strong>
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?>
        <li><?= esc($e) ?></li>
      <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<!-- FILTER -->
<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get" action="/master/customer" class="row g-2 align-items-end">
      <div class="col-md-5">
        <label class="form-label mb-1">Cari (Code Accurate / Code App / Name)</label>
        <input type="text" name="keyword" class="form-control"
               placeholder="contoh: ACC-001 / CUST-0001 / PT ABC"
               value="<?= esc($keyword ?? '') ?>">
      </div>

      <div class="col-md-2">
        <label class="form-label mb-1">Tampilkan</label>
        <select name="perPage" class="form-select" onchange="this.form.submit()">
          <?php foreach (($perPageOptions ?? [10,25,50,100]) as $opt): ?>
            <option value="<?= $opt ?>" <?= (int)($perPage ?? 10) === (int)$opt ? 'selected' : '' ?>>
              <?= $opt ?> data
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-5 d-flex gap-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Filter</button>
        <a href="/master/customer" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-counterclockwise"></i> Reset
        </a>
      </div>
    </form>
  </div>
</div>

<!-- TABLE -->
<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th style="width: 70px;">No</th>
            <th style="width: 200px;">Customer Code (Accurate)</th>
            <th style="width: 170px;">Customer Code App</th>
            <th>Customer Name</th>
            <th style="width: 140px;">Status</th>
            <th style="width: 190px;" class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($customers)): ?>
            <tr>
              <td colspan="6" class="text-center py-4 text-muted">Data customer belum tersedia</td>
            </tr>
          <?php else: ?>
            <?php
              $page    = (int)($pager->getCurrentPage('customers') ?? 1);
              $pp      = (int)($perPage ?? 10);
              $no      = 1 + (($page - 1) * $pp);
            ?>
            <?php foreach ($customers as $c): ?>
              <tr>
                <td><?= $no++ ?></td>
                <td class="fw-semibold"><?= esc($c['customer_code'] ?? '-') ?></td>
                <td class="fw-semibold"><?= esc($c['customer_code_app'] ?? '-') ?></td>
                <td><?= esc($c['customer_name']) ?></td>
                <td>
                  <?php if ((int)($c['is_active'] ?? 1) === 1): ?>
                    <span class="badge bg-success">Aktif</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Nonaktif</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <button type="button"
                          class="btn btn-sm btn-warning"
                          data-bs-toggle="modal"
                          data-bs-target="#modalEditCustomer"
                          data-id="<?= esc($c['id'], 'attr') ?>"
                          data-customer_code="<?= esc($c['customer_code'] ?? '', 'attr') ?>"
                          data-customer_code_app="<?= esc($c['customer_code_app'] ?? '', 'attr') ?>"
                          data-customer_name="<?= esc($c['customer_name'] ?? '', 'attr') ?>"
                          data-is_active="<?= esc($c['is_active'] ?? 1, 'attr') ?>">
                    <i class="bi bi-pencil"></i> Edit
                  </button>

                  <form action="/master/customer/<?= esc($c['id']) ?>/delete"
                        method="post"
                        class="d-inline"
                        onsubmit="return confirm('Hapus customer ini?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-danger">
                      <i class="bi bi-trash"></i> Hapus
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach ?>
          <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="d-flex justify-content-end p-2">
    <?= $pager->links('customers', 'bootstrap_pagination') ?>
  </div>
</div>

<!-- ========================= MODAL: ADD CUSTOMER ========================= -->
<div class="modal fade" id="modalAddCustomer" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" action="/master/customer/store" id="formAddCustomer">
        <?= csrf_field() ?>

        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-0">Tambah Customer</h5>
            <small class="text-muted">
              Customer Code App otomatis: <b>CUST-0001</b>, <b>CUST-0002</b>, dst.
            </small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="form-wrap form-grid">

            <!-- ✅ Accurate code (bebas) -->
            <div class="row-item">
              <label>Customer Code (Accurate)</label>
              <input type="text" name="customer_code" class="form-control"
                     placeholder="Bebas (kode dari Accurate), contoh: ACC-CUS-001">
            </div>

            <div class="row-item">
              <label>Customer Name <span class="text-danger">*</span></label>
              <input type="text" name="customer_name" class="form-control" required
                     placeholder="Contoh: PT ABC Indonesia">
            </div>

            <div class="row-item">
              <label>Status</label>
              <select name="is_active" class="form-select">
                <option value="1" selected>Aktif</option>
                <option value="0">Nonaktif</option>
              </select>
            </div>

          </div>
        </div>

        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="submit" class="btn btn-success">
            <i class="bi bi-save"></i> Simpan
          </button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ========================= MODAL: EDIT CUSTOMER ========================= -->
<div class="modal fade" id="modalEditCustomer" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" id="formEditCustomer" action="">
        <?= csrf_field() ?>

        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-0">Edit Customer</h5>
            <small class="text-muted">Customer Code App tidak bisa diubah</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="form-wrap form-grid">

            <!-- ✅ Accurate code (bebas) -->
            <div class="row-item">
              <label>Customer Code (Accurate)</label>
              <input type="text" name="customer_code" id="edit_customer_code" class="form-control"
                     placeholder="Bebas (kode dari Accurate)">
            </div>

            <!-- ✅ App code (auto, readonly) -->
            <div class="row-item">
              <label>Customer Code App</label>
              <input type="text" id="edit_customer_code_app" class="form-control" readonly>
            </div>

            <div class="row-item">
              <label>Customer Name <span class="text-danger">*</span></label>
              <input type="text" name="customer_name" id="edit_customer_name" class="form-control" required>
            </div>

            <div class="row-item">
              <label>Status</label>
              <select name="is_active" id="edit_is_active" class="form-select">
                <option value="1">Aktif</option>
                <option value="0">Nonaktif</option>
              </select>
            </div>

          </div>
        </div>

        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="submit" class="btn btn-warning">
            <i class="bi bi-save"></i> Update
          </button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  const modalEditCustomer = document.getElementById('modalEditCustomer');
  modalEditCustomer.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;

    const id = button.getAttribute('data-id');
    const customer_code = button.getAttribute('data-customer_code');
    const customer_code_app = button.getAttribute('data-customer_code_app');
    const customer_name = button.getAttribute('data-customer_name');
    const is_active = button.getAttribute('data-is_active');

    document.getElementById('edit_customer_code').value = customer_code || '';
    document.getElementById('edit_customer_code_app').value = customer_code_app || '';
    document.getElementById('edit_customer_name').value = customer_name || '';
    document.getElementById('edit_is_active').value = (is_active ?? '1');

    document.getElementById('formEditCustomer').action = '/master/customer/update/' + id;
  });
</script>

<?= $this->endSection() ?>
