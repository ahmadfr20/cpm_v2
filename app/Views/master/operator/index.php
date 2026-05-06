<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="mb-0">Manajemen Operator</h4>
    <small class="text-muted">Admin dapat menambah, mengedit, dan menghapus nama operator produksi</small>
  </div>

  <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAddOperator">
    <i class="bi bi-plus"></i> Tambah Operator
  </button>
</div>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= esc(session()->getFlashdata('success')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- TABLE -->
<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:70px;">No</th>
            <th>Nama Operator</th>
            <th style="width:200px;">Section</th>
            <th style="width:200px;" class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($operators)): ?>
            <tr>
              <td colspan="4" class="text-center py-4 text-muted">Data operator belum tersedia</td>
            </tr>
          <?php else: ?>
            <?php $no = 1; foreach ($operators as $o): ?>
              <tr>
                <td><?= $no++ ?></td>
                <td class="fw-semibold"><?= esc($o['operator_name']) ?></td>
                <td>
                    <span class="badge bg-primary"><?= esc($o['section']) ?></span>
                </td>
                <td class="text-end">
                  <button type="button"
                          class="btn btn-sm btn-warning"
                          data-bs-toggle="modal"
                          data-bs-target="#modalEditOperator"
                          data-id="<?= esc($o['id'], 'attr') ?>"
                          data-operator_name="<?= esc($o['operator_name'] ?? '', 'attr') ?>"
                          data-section="<?= esc($o['section'] ?? '', 'attr') ?>">
                    <i class="bi bi-pencil"></i> Edit
                  </button>

                  <form action="/master/operator/delete/<?= esc($o['id']) ?>"
                        method="post"
                        class="d-inline"
                        onsubmit="return confirm('Hapus operator ini?')">
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
</div>

<!-- ========================= MODAL: ADD ========================= -->
<div class="modal fade" id="modalAddOperator" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="/master/operator/store">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Tambah Operator</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
              <label>Nama Operator <span class="text-danger">*</span></label>
              <input type="text" name="operator_name" class="form-control" required placeholder="contoh: Budi Santoso">
            </div>
            <div class="mb-3">
              <label>Section <span class="text-danger">*</span></label>
              <select name="section" class="form-select" required>
                  <option value="Die Casting">Die Casting</option>
                  <option value="Machining">Machining</option>
              </select>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
          <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ========================= MODAL: EDIT ========================= -->
<div class="modal fade" id="modalEditOperator" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="formEditOperator" action="">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Edit Operator</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
              <label>Nama Operator <span class="text-danger">*</span></label>
              <input type="text" name="operator_name" id="edit_operator_name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label>Section <span class="text-danger">*</span></label>
              <select name="section" id="edit_section" class="form-select" required>
                  <option value="Die Casting">Die Casting</option>
                  <option value="Machining">Machining</option>
              </select>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
          <button type="submit" class="btn btn-warning"><i class="bi bi-save"></i> Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  const modalEdit = document.getElementById('modalEditOperator');
  modalEdit?.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    const id = btn.getAttribute('data-id');
    const operator_name = btn.getAttribute('data-operator_name') || '';
    const section = btn.getAttribute('data-section') || 'Die Casting';

    document.getElementById('edit_operator_name').value = operator_name;
    document.getElementById('edit_section').value = section;

    document.getElementById('formEditOperator').action = '/master/operator/update/' + id;
  });
</script>

<?= $this->endSection() ?>
