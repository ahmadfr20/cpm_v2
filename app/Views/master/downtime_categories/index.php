<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
  .modal .modal-dialog{ max-width: 760px; margin-top: 90px !important; }
  @media (max-width: 768px){ .modal .modal-dialog{ margin-top: 70px !important; } }

  .modal .modal-content{ max-height: calc(100vh - 120px); }
  .modal .modal-body{ overflow: auto; padding-bottom: 12px; }
  .modal .modal-footer{
    position: sticky; bottom: 0; background: #fff; z-index: 2; border-top: 1px solid rgba(0,0,0,.1);
  }

  .modal .form-wrap{ max-width: 680px; margin-left: 0; margin-right: auto; }

  .form-grid .row-item{
    display: grid; grid-template-columns: 220px 1fr; gap: 14px; align-items: center; margin-bottom: 10px;
  }
  .form-grid label{ font-weight: 600; margin: 0; }
  .form-grid .form-control, .form-grid .form-select{ width: 100%; }

  .table td, .table th { vertical-align: middle; }
</style>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="mb-0">Master Downtime Categories</h4>
    <small class="text-muted">Kelola kategori Downtime per proses (Terintegrasi ke Master Process)</small>
  </div>

  <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAddDowntime">
    <i class="bi bi-plus"></i> Tambah Downtime Category
  </button>
</div>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <?= esc(session()->getFlashdata('success')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger alert-dismissible fade show">
    <?= esc(session()->getFlashdata('error')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<?php $errors = session()->getFlashdata('errors'); ?>
<?php if (!empty($errors)): ?>
  <div class="alert alert-danger alert-dismissible fade show">
    <strong>Validasi gagal:</strong>
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?>
        <li><?= esc($e) ?></li>
      <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get" action="/master/downtime-categories" class="row g-2 align-items-end">
      
      <div class="col-md-4">
        <label class="form-label mb-1">Cari (Code / Name / Process)</label>
        <input type="text" name="keyword" class="form-control" placeholder="contoh: 20 / Set Up" value="<?= esc($keyword ?? '') ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label mb-1">Filter Process</label>
        <select name="process_id" class="form-select" onchange="this.form.submit()">
          <option value="">-- Semua Process --</option>
          <?php foreach ($processes as $p): ?>
            <option value="<?= $p['id'] ?>" <?= ($processId ?? '') == $p['id'] ? 'selected' : '' ?>>
              <?= esc($p['process_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label mb-1">Filter Status</label>
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
            <option value="<?= $opt ?>" <?= (int)($perPage ?? 10) === (int)$opt ? 'selected' : '' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Cari</button>
        <a href="/master/downtime-categories" class="btn btn-outline-secondary" title="Reset Filter"><i class="bi bi-arrow-counterclockwise"></i></a>
      </div>
      
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:70px;">No</th>
            <th style="width:220px;">Process</th>
            <th style="width:140px;">Downtime Code</th>
            <th>Downtime Name</th>
            <th style="width:120px;">Value (Qty)</th> 
            <th style="width:140px;">Status</th>
            <th style="width:190px;" class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="7" class="text-center py-4 text-muted">Data Downtime Category belum tersedia atau tidak ditemukan</td></tr>
          <?php else: ?>
            <?php
              $page = (int)($pager->getCurrentPage('downtimecats') ?? 1);
              $pp   = (int)($perPage ?? 10);
              $no   = 1 + (($page - 1) * $pp);
            ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= $no++ ?></td>
                <td class="fw-semibold text-primary"><?= esc($r['process_name'] ?? 'Unknown Process') ?></td>
                <td class="fw-bold text-danger"><?= esc($r['downtime_code'] ?? '-') ?></td>
                <td><?= esc($r['downtime_name'] ?? '-') ?></td>
                <td class="fw-bold"><?= esc($r['value'] ?? 10) ?></td> 
                <td>
                  <?php if ((int)($r['is_active'] ?? 1) === 1): ?>
                    <span class="badge bg-success">Aktif</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Nonaktif</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <button type="button" class="btn btn-sm btn-warning"
                          data-bs-toggle="modal"
                          data-bs-target="#modalEditDowntime"
                          data-id="<?= esc($r['id'], 'attr') ?>"
                          data-process_id="<?= esc($r['process_id'] ?? '', 'attr') ?>"
                          data-downtime_code="<?= esc($r['downtime_code'] ?? '', 'attr') ?>"
                          data-downtime_name="<?= esc($r['downtime_name'] ?? '', 'attr') ?>"
                          data-value="<?= esc($r['value'] ?? 10, 'attr') ?>" 
                          data-is_active="<?= esc($r['is_active'] ?? 1, 'attr') ?>">
                    <i class="bi bi-pencil"></i> Edit
                  </button>

                  <form action="/master/downtime-categories/<?= esc($r['id']) ?>/delete" method="post" class="d-inline" onsubmit="return confirm('Hapus Downtime Category ini?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> Hapus</button>
                  </form>
                </td>
              </tr>
            <?php endforeach ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  
  <div class="d-flex justify-content-end p-2 border-top">
    <?= $pager->links('downtimecats', 'bootstrap_pagination') ?>
  </div>
</div>

<div class="modal fade" id="modalAddDowntime" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" action="/master/downtime-categories/store">
        <?= csrf_field() ?>
        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-0">Tambah Downtime Category</h5>
            <small class="text-muted">Gunakan kode numerik (contoh: 21, 22, dst)</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="form-wrap form-grid">
            <div class="row-item">
              <label>Pilih Process <span class="text-danger">*</span></label>
              <select name="process_id" class="form-select" required>
                <option value="">-- Pilih Process --</option>
                <?php foreach ($processes as $p): ?>
                  <option value="<?= $p['id'] ?>" <?= old('process_id') == $p['id'] ? 'selected' : '' ?>>
                    <?= esc($p['process_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="row-item">
              <label>Downtime Code <span class="text-danger">*</span></label>
              <input type="number" name="downtime_code" class="form-control" required placeholder="Contoh: 10" value="<?= esc(old('downtime_code') ?? '') ?>">
            </div>
            <div class="row-item">
              <label>Downtime Name <span class="text-danger">*</span></label>
              <input type="text" name="downtime_name" class="form-control" required placeholder="Contoh: Setting Cetakan" value="<?= esc(old('downtime_name') ?? '') ?>">
            </div>
            <div class="row-item">
              <label>Value Pengurang Qty <span class="text-danger">*</span></label>
              <input type="number" name="value" class="form-control" required placeholder="Contoh: 10" value="<?= esc(old('value') ?? 10) ?>">
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
          <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Simpan</button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalEditDowntime" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" id="formEditDowntime">
        <?= csrf_field() ?>
        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-0">Edit Downtime Category</h5>
            <small class="text-muted">Kode boleh diubah, tapi harus unik per process</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="form-wrap form-grid">
            <div class="row-item">
              <label>Pilih Process <span class="text-danger">*</span></label>
              <select name="process_id" id="edit_process_id" class="form-select" required>
                <option value="">-- Pilih Process --</option>
                <?php foreach ($processes as $p): ?>
                  <option value="<?= $p['id'] ?>"><?= esc($p['process_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="row-item">
              <label>Downtime Code <span class="text-danger">*</span></label>
              <input type="number" name="downtime_code" id="edit_downtime_code" class="form-control" required>
            </div>
            <div class="row-item">
              <label>Downtime Name <span class="text-danger">*</span></label>
              <input type="text" name="downtime_name" id="edit_downtime_name" class="form-control" required>
            </div>
            <div class="row-item">
              <label>Value Pengurang Qty <span class="text-danger">*</span></label>
              <input type="number" name="value" id="edit_value" class="form-control" required>
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
          <button type="submit" class="btn btn-warning"><i class="bi bi-save"></i> Update</button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  const modalEditDowntime = document.getElementById('modalEditDowntime');
  modalEditDowntime.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;

    document.getElementById('edit_process_id').value = button.getAttribute('data-process_id') || '';
    document.getElementById('edit_downtime_code').value = button.getAttribute('data-downtime_code') || '';
    document.getElementById('edit_downtime_name').value = button.getAttribute('data-downtime_name') || '';
    document.getElementById('edit_value').value = button.getAttribute('data-value') || '10';
    document.getElementById('edit_is_active').value = button.getAttribute('data-is_active') ?? '1';

    document.getElementById('formEditDowntime').action = '/master/downtime-categories/update/' + button.getAttribute('data-id');
  });
</script>

<?= $this->endSection() ?>