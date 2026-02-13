<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
  /* ✅ modal tidak ketutup navbar + lebar */
  .modal .modal-dialog{ max-width: 760px; margin-top: 90px !important; }
  @media (max-width: 768px){ .modal .modal-dialog{ margin-top: 70px !important; } }

  /* ✅ body modal scroll, footer sticky */
  .modal .modal-content{ max-height: calc(100vh - 120px); }
  .modal .modal-body{ overflow: auto; padding-bottom: 12px; }
  .modal .modal-footer{
    position: sticky;
    bottom: 0;
    background: #fff;
    z-index: 2;
    border-top: 1px solid rgba(0,0,0,.1);
  }

  /* ✅ form rata kiri */
  .modal .form-wrap{ max-width: 680px; margin-left: 0; margin-right: auto; }

  /* ✅ struktur label kiri, input kanan (seperti product master) */
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
    <h4 class="mb-0">Master Vendor</h4>
    <small class="text-muted">Kelola data vendor / pemasok</small>
  </div>

  <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAddVendor">
    <i class="bi bi-plus"></i> Tambah Vendor
  </button>
</div>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= esc(session()->getFlashdata('success')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= esc(session()->getFlashdata('error')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- FILTER -->
<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get" action="/master/vendor" class="row g-2 align-items-end">
      <div class="col-md-5">
        <label class="form-label mb-1">Cari (Code / Name)</label>
        <input type="text"
               name="keyword"
               class="form-control"
               placeholder="contoh: VEN-0001 / PT ABC"
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
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-search"></i> Filter
        </button>
        <a href="/master/vendor" class="btn btn-outline-secondary">
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
            <th style="width: 180px;">Vendor Code</th>
            <th>Vendor Name</th>
            <th style="width: 140px;">Status</th>
            <th style="width: 190px;" class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($vendors)): ?>
            <tr>
              <td colspan="5" class="text-center py-4 text-muted">Data vendor belum tersedia</td>
            </tr>
          <?php else: ?>
            <?php
              $page    = (int)($pager->getCurrentPage('vendors') ?? 1);
              $pp      = (int)($perPage ?? 10);
              $no      = 1 + (($page - 1) * $pp);
            ?>
            <?php foreach ($vendors as $v): ?>
              <tr>
                <td><?= $no++ ?></td>
                <td class="fw-semibold"><?= esc($v['vendor_code']) ?></td>
                <td><?= esc($v['vendor_name']) ?></td>
                <td>
                  <?php if ((int)($v['is_active'] ?? 1) === 1): ?>
                    <span class="badge bg-success">Aktif</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Nonaktif</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <button type="button"
                          class="btn btn-sm btn-warning"
                          data-bs-toggle="modal"
                          data-bs-target="#modalEditVendor"
                          data-id="<?= esc($v['id'], 'attr') ?>"
                          data-vendor_code="<?= esc($v['vendor_code'] ?? '', 'attr') ?>"
                          data-vendor_name="<?= esc($v['vendor_name'] ?? '', 'attr') ?>"
                          data-is_active="<?= esc($v['is_active'] ?? 1, 'attr') ?>">
                    <i class="bi bi-pencil"></i> Edit
                  </button>

                  <form action="/master/vendor/<?= esc($v['id']) ?>/delete"
                        method="post"
                        class="d-inline"
                        onsubmit="return confirm('Hapus vendor ini?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-danger">
                      <i class="bi bi-trash"></i> Hapus
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="d-flex justify-content-end p-2">
    <?= $pager->links('vendors', 'bootstrap_pagination') ?>
  </div>
</div>

<!-- ========================= MODAL: ADD VENDOR ========================= -->
<div class="modal fade" id="modalAddVendor" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <form method="post" action="/master/vendor/store" id="formAddVendor">
        <?= csrf_field() ?>

        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-0">Tambah Vendor</h5>
            <small class="text-muted">Vendor Code otomatis: VEN-0001, VEN-0002, dst.</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="form-wrap form-grid">

            <div class="row-item">
              <label>Vendor Name <span class="text-danger">*</span></label>
              <input type="text" name="vendor_name" class="form-control" required
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

<!-- ========================= MODAL: EDIT VENDOR ========================= -->
<div class="modal fade" id="modalEditVendor" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <form method="post" id="formEditVendor" action="">
        <?= csrf_field() ?>

        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-0">Edit Vendor</h5>
            <small class="text-muted">Vendor Code tidak bisa diubah</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="form-wrap form-grid">

            <div class="row-item">
              <label>Vendor Code</label>
              <input type="text" id="edit_vendor_code" class="form-control" readonly>
            </div>

            <div class="row-item">
              <label>Vendor Name <span class="text-danger">*</span></label>
              <input type="text" name="vendor_name" id="edit_vendor_name" class="form-control" required>
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
  const modalEditVendor = document.getElementById('modalEditVendor');
  modalEditVendor.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;

    const id          = button.getAttribute('data-id');
    const vendor_code = button.getAttribute('data-vendor_code');
    const vendor_name = button.getAttribute('data-vendor_name');
    const is_active   = button.getAttribute('data-is_active');

    document.getElementById('edit_vendor_code').value = vendor_code || '';
    document.getElementById('edit_vendor_name').value = vendor_name || '';
    document.getElementById('edit_is_active').value = (is_active ?? '1');

    document.getElementById('formEditVendor').action = '/master/vendor/update/' + id;
  });
</script>

<?= $this->endSection() ?>
