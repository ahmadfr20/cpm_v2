<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
  /* Modal offset supaya tidak ketutup navbar fixed-top */
  .modal.modal-navbar-offset .modal-dialog {
    margin-top: 80px !important; /* sesuaikan tinggi navbar */
  }
</style>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-0">Master Machine</h4>
        <small class="text-muted">Kelola data mesin dan prosesnya</small>
    </div>

    <!-- Button tambah (modal) -->
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAddMachine">
        <i class="bi bi-plus"></i> Tambah Machine
    </button>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= session()->getFlashdata('success') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= session()->getFlashdata('error') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- FILTER CARD -->
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="get" action="/master/machine" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label mb-1">Cari (Code / Name)</label>
                <input type="text"
                       name="keyword"
                       class="form-control"
                       placeholder="contoh: MC01 / Mazak"
                       value="<?= esc($keyword ?? '') ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label mb-1">Process</label>
                <select name="process_id" class="form-select">
                    <option value="">-- Semua Process --</option>
                    <?php foreach ($processes ?? [] as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($processId ?? '') == $p['id'] ? 'selected' : '' ?>>
                            <?= esc($p['process_name']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label mb-1">Tampilkan</label>
                <select name="perPage" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($perPageOptions as $opt): ?>
                        <option value="<?= $opt ?>" <?= ($perPage ?? 10) == $opt ? 'selected' : '' ?>>
                            <?= $opt ?> data
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="/master/machine" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- TABLE CARD -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 170px;">Alamat Mesin</th>
                        <th>Tipe Mesin</th>
                        <th>Process</th>
                        <th style="width: 140px;">Line Position</th>
                        <th style="width: 190px;" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($machines)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                Data machine belum tersedia
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($machines as $m): ?>
                            <tr>
                                <td class="fw-semibold"><?= esc($m['machine_code']) ?></td>
                                <td><?= esc($m['machine_name']) ?></td>
                                <td><?= esc($m['process_name'] ?? '-') ?></td>
                                <td>Line <?= esc($m['line_position']) ?></td>
                                <td class="text-end">
                                    <!-- EDIT (modal) -->
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-warning"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalEditMachine"
                                        data-id="<?= esc($m['id']) ?>"
                                        data-machine_code="<?= esc($m['machine_code']) ?>"
                                        data-machine_name="<?= esc($m['machine_name']) ?>"
                                        data-process_id="<?= esc($m['process_id'] ?? '') ?>"
                                        data-line_position="<?= esc($m['line_position'] ?? '') ?>"
                                    >
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>

                                    <!-- DELETE -->
                                    <form action="/master/machine/<?= $m['id'] ?>/delete"
                                          method="post"
                                          class="d-inline"
                                          onsubmit="return confirm('Hapus machine?')">
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

    <div class="d-flex justify-content-end">
        <?= $pager->links('machines', 'bootstrap_pagination') ?>
    </div>
</div>

<!-- ===========================
     MODAL: ADD MACHINE
=========================== -->
<div class="modal fade modal-navbar-offset" id="modalAddMachine" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" action="/master/machine/store">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Tambah Machine</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Alamat Mesin (Code)</label>
              <input type="text" name="machine_code" class="form-control" required>
            </div>

            <div class="col-md-8">
              <label class="form-label">Tipe / Nama Mesin</label>
              <input type="text" name="machine_name" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Process</label>
              <select name="process_id" class="form-select" required>
                <option value="">-- Pilih Process --</option>
                <?php foreach ($processes ?? [] as $p): ?>
                  <option value="<?= $p['id'] ?>"><?= esc($p['process_name']) ?></option>
                <?php endforeach ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Line Position</label>
              <input type="number" name="line_position" class="form-control" min="1" required>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success">
            <i class="bi bi-save"></i> Simpan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===========================
     MODAL: EDIT MACHINE
=========================== -->
<div class="modal fade modal-navbar-offset" id="modalEditMachine" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <!-- action akan di-set via JS -->
      <form method="post" id="formEditMachine" action="">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Edit Machine</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" id="edit_id">

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Alamat Mesin (Code)</label>
              <input type="text" name="machine_code" id="edit_machine_code" class="form-control" required>
            </div>

            <div class="col-md-8">
              <label class="form-label">Tipe / Nama Mesin</label>
              <input type="text" name="machine_name" id="edit_machine_name" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Process</label>
              <select name="process_id" id="edit_process_id" class="form-select" required>
                <option value="">-- Pilih Process --</option>
                <?php foreach ($processes ?? [] as $p): ?>
                  <option value="<?= $p['id'] ?>"><?= esc($p['process_name']) ?></option>
                <?php endforeach ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Line Position</label>
              <input type="number" name="line_position" id="edit_line_position" class="form-control" min="1" required>
            </div>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-warning">
            <i class="bi bi-save"></i> Update
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // Isi modal edit dari data-* button
  const modalEdit = document.getElementById('modalEditMachine');
  modalEdit.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;

    const id = button.getAttribute('data-id');
    const machine_code = button.getAttribute('data-machine_code');
    const machine_name = button.getAttribute('data-machine_name');
    const process_id = button.getAttribute('data-process_id');
    const line_position = button.getAttribute('data-line_position');

    document.getElementById('edit_id').value = id;
    document.getElementById('edit_machine_code').value = machine_code || '';
    document.getElementById('edit_machine_name').value = machine_name || '';
    document.getElementById('edit_process_id').value = process_id || '';
    document.getElementById('edit_line_position').value = line_position || '';

    // set action ke route update
    document.getElementById('formEditMachine').action = '/master/machine/update/' + id;
  });
</script>

<?= $this->endSection() ?>
