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
    <h4 class="mb-0">Master NG Categories</h4>
    <small class="text-muted">Kelola kategori NG per process (Dipakai di hourly & NG editor)</small>
  </div>

  <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAddNg">
    <i class="bi bi-plus"></i> Tambah NG Category
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
    <form method="get" action="/master/ng-categories" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label mb-1">Cari (Process / Code / Name)</label>
        <input type="text"
               name="keyword"
               class="form-control"
               placeholder="contoh: Die Casting / 10 / flow line"
               value="<?= esc($keyword ?? '') ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label mb-1">Process</label>
        <select name="process" class="form-select" onchange="this.form.submit()">
          <option value="">-- semua --</option>
          <?php foreach (($processOptions ?? []) as $p): ?>
            <option value="<?= esc($p) ?>" <?= ($process ?? '') === $p ? 'selected' : '' ?>>
              <?= esc($p) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label mb-1">Status</label>
        <select name="status" class="form-select" onchange="this.form.submit()">
          <option value=""  <?= ($status ?? '') === '' ? 'selected' : '' ?>>Semua</option>
          <option value="1" <?= ($status ?? '') === '1' ? 'selected' : '' ?>>Aktif</option>
          <option value="0" <?= ($status ?? '') === '0' ? 'selected' : '' ?>>Nonaktif</option>
        </select>
      </div>

      <div class="col-md-1">
        <label class="form-label mb-1">Tampil</label>
        <select name="perPage" class="form-select" onchange="this.form.submit()">
          <?php foreach (($perPageOptions ?? [10,25,50,100]) as $opt): ?>
            <option value="<?= $opt ?>" <?= (int)($perPage ?? 10) === (int)$opt ? 'selected' : '' ?>>
              <?= $opt ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-search"></i>
        </button>
        <a href="/master/ng-categories" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-counterclockwise"></i>
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
            <th style="width:70px;">No</th>
            <th style="width:220px;">Process</th>
            <th style="width:110px;">NG Code</th>
            <th>NG Name</th>
            <th style="width:140px;">Status</th>
            <th style="width:190px;" class="text-end">Aksi</th>
          </tr>
        </thead>

        <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="6" class="text-center py-4 text-muted">Data NG Category belum tersedia</td>
            </tr>
          <?php else: ?>
            <?php
              $page = (int)($pager->getCurrentPage('ngcats') ?? 1);
              $pp   = (int)($perPage ?? 10);
              $no   = 1 + (($page - 1) * $pp);
            ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= $no++ ?></td>
                <td class="fw-semibold"><?= esc($r['process_name'] ?? '-') ?></td>
                <td class="fw-semibold"><?= esc($r['ng_code'] ?? '-') ?></td>
                <td><?= esc($r['ng_name'] ?? '-') ?></td>
                <td>
                  <?php if ((int)($r['is_active'] ?? 1) === 1): ?>
                    <span class="badge bg-success">Aktif</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Nonaktif</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <button type="button"
                          class="btn btn-sm btn-warning"
                          data-bs-toggle="modal"
                          data-bs-target="#modalEditNg"
                          data-id="<?= esc($r['id'], 'attr') ?>"
                          data-process_name="<?= esc($r['process_name'] ?? '', 'attr') ?>"
                          data-ng_code="<?= esc($r['ng_code'] ?? '', 'attr') ?>"
                          data-ng_name="<?= esc($r['ng_name'] ?? '', 'attr') ?>"
                          data-is_active="<?= esc($r['is_active'] ?? 1, 'attr') ?>">
                    <i class="bi bi-pencil"></i> Edit
                  </button>

                  <form action="/master/ng-categories/<?= esc($r['id']) ?>/delete"
                        method="post"
                        class="d-inline"
                        onsubmit="return confirm('Hapus NG Category ini?')">
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
    <?= $pager->links('ngcats', 'bootstrap_pagination') ?>
  </div>
</div>

<!-- ========================= MODAL: ADD ========================= -->
<div class="modal fade" id="modalAddNg" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <form method="post" action="/master/ng-categories/store" id="formAddNg">
        <?= csrf_field() ?>

        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-0">Tambah NG Category</h5>
            <small class="text-muted">NG Code numerik (contoh: 10, 11, 12 ...)</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="form-wrap form-grid">

            <div class="row-item">
              <label>Process Name <span class="text-danger">*</span></label>
              <input type="text" name="process_name" class="form-control" required
                     placeholder="Contoh: Die Casting" value="<?= esc(old('process_name') ?? '') ?>">
            </div>

            <div class="row-item">
              <label>NG Code <span class="text-danger">*</span></label>
              <input type="number" name="ng_code" class="form-control" required
                     placeholder="Contoh: 10" value="<?= esc(old('ng_code') ?? '') ?>">
            </div>

            <div class="row-item">
              <label>NG Name <span class="text-danger">*</span></label>
              <input type="text" name="ng_name" class="form-control" required
                     placeholder="Contoh: flow line" value="<?= esc(old('ng_name') ?? '') ?>">
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

<!-- ========================= MODAL: EDIT ========================= -->
<div class="modal fade" id="modalEditNg" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <form method="post" id="formEditNg" action="">
        <?= csrf_field() ?>

        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-0">Edit NG Category</h5>
            <small class="text-muted">NG Code boleh diubah, tapi harus unik per process</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="form-wrap form-grid">

            <div class="row-item">
              <label>Process Name <span class="text-danger">*</span></label>
              <input type="text" name="process_name" id="edit_process_name" class="form-control" required>
            </div>

            <div class="row-item">
              <label>NG Code <span class="text-danger">*</span></label>
              <input type="number" name="ng_code" id="edit_ng_code" class="form-control" required>
            </div>

            <div class="row-item">
              <label>NG Name <span class="text-danger">*</span></label>
              <input type="text" name="ng_name" id="edit_ng_name" class="form-control" required>
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
  const modalEditNg = document.getElementById('modalEditNg');
  modalEditNg.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;

    const id           = button.getAttribute('data-id');
    const process_name = button.getAttribute('data-process_name');
    const ng_code      = button.getAttribute('data-ng_code');
    const ng_name      = button.getAttribute('data-ng_name');
    const is_active    = button.getAttribute('data-is_active');

    document.getElementById('edit_process_name').value = process_name || '';
    document.getElementById('edit_ng_code').value = ng_code || '';
    document.getElementById('edit_ng_name').value = ng_name || '';
    document.getElementById('edit_is_active').value = (is_active ?? '1');

    document.getElementById('formEditNg').action = '/master/ng-categories/update/' + id;
  });
</script>

<?= $this->endSection() ?>
