<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
  .modal .modal-dialog{ max-width: 920px; margin-top: 90px !important; }
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

  .form-grid .row-item{
    display: grid;
    grid-template-columns: 220px 1fr;
    gap: 14px;
    align-items: start;
    margin-bottom: 10px;
  }
  .form-grid label{ font-weight: 600; margin: 0; padding-top: 7px; }
  .table td, .table th { vertical-align: middle; }

  /* checkbox sections grid */
  .sections-grid{
    display:grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px 14px;
  }
  @media (max-width: 768px){
    .sections-grid{ grid-template-columns: 1fr; }
  }
  .sections-grid .form-check{
    border: 1px solid rgba(0,0,0,.08);
    padding: 8px 10px;
    border-radius: 10px;
    background: #fff;
  }
</style>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="mb-0">Manajemen User</h4>
    <small class="text-muted">Admin dapat menambah, mengedit, dan mengatur privilege multi-section (berdasarkan production_processes)</small>
  </div>

  <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAddUser">
    <i class="bi bi-plus"></i> Tambah User
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
    <form method="get" action="/master/user" class="row g-2 align-items-end">
      <div class="col-md-5">
        <label class="form-label mb-1">Cari (Username / Fullname)</label>
        <input type="text" name="keyword" class="form-control"
               value="<?= esc($keyword ?? '') ?>" placeholder="contoh: admin / Budi">
      </div>

      <div class="col-md-3">
        <label class="form-label mb-1">Role</label>
        <select name="role" class="form-select" onchange="this.form.submit()">
          <option value="">Semua</option>
          <?php foreach (($roleOptions ?? []) as $r): ?>
            <option value="<?= esc($r, 'attr') ?>" <?= ($role ?? '') === $r ? 'selected' : '' ?>>
              <?= esc($r) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
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
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Filter</button>
        <a href="/master/user" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
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
            <th style="width:170px;">Username</th>
            <th>Fullname</th>
            <th style="width:140px;">Role</th>
            <th>Section (Processes)</th>
            <th style="width:320px;" class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr>
              <td colspan="6" class="text-center py-4 text-muted">Data user belum tersedia</td>
            </tr>
          <?php else: ?>
            <?php
              $page = (int)($pager->getCurrentPage('users') ?? 1);
              $pp   = (int)($perPage ?? 10);
              $no   = 1 + (($page - 1) * $pp);
            ?>
            <?php foreach ($users as $u): ?>
              <tr>
                <td><?= $no++ ?></td>
                <td class="fw-semibold"><?= esc($u['username']) ?></td>
                <td><?= esc($u['fullname'] ?? '-') ?></td>
                <td>
                  <?php
                    $roleVal = (string)($u['role'] ?? '');
                    $badge = 'bg-secondary';
                    if ($roleVal === 'ADMIN') $badge = 'bg-danger';
                    elseif ($roleVal === 'MANAGER') $badge = 'bg-primary';
                    elseif ($roleVal === 'QC') $badge = 'bg-warning text-dark';
                    elseif ($roleVal === 'PPIC') $badge = 'bg-info text-dark';
                    elseif ($roleVal === 'OPERATOR') $badge = 'bg-success';
                  ?>
                  <span class="badge <?= $badge ?>"><?= esc($roleVal ?: '-') ?></span>
                </td>
                <td>
                  <?php if (($u['role'] ?? '') === 'ADMIN'): ?>
                    <span class="text-muted">All Access</span>
                  <?php else: ?>
                    <?php if (!empty($u['process_names'])): ?>
                      <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($u['process_names'] as $pname): ?>
                          <span class="badge bg-light text-dark border"><?= esc($pname) ?></span>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>

                <td class="text-end">
                  <button type="button"
                          class="btn btn-sm btn-secondary"
                          data-bs-toggle="modal"
                          data-bs-target="#modalPrivilege"
                          data-id="<?= esc($u['id'], 'attr') ?>"
                          data-username="<?= esc($u['username'] ?? '', 'attr') ?>"
                          data-role="<?= esc($u['role'] ?? '', 'attr') ?>">
                    <i class="bi bi-shield-lock"></i> Detail Privilege
                  </button>

                  <button type="button"
                          class="btn btn-sm btn-warning"
                          data-bs-toggle="modal"
                          data-bs-target="#modalEditUser"
                          data-id="<?= esc($u['id'], 'attr') ?>"
                          data-username="<?= esc($u['username'] ?? '', 'attr') ?>"
                          data-fullname="<?= esc($u['fullname'] ?? '', 'attr') ?>"
                          data-role="<?= esc($u['role'] ?? '', 'attr') ?>"
                          data-process_ids="<?= esc(implode(',', $u['process_ids'] ?? []), 'attr') ?>">
                    <i class="bi bi-pencil"></i> Edit
                  </button>

                  <form action="/master/user/<?= esc($u['id']) ?>/delete"
                        method="post"
                        class="d-inline"
                        onsubmit="return confirm('Hapus user ini?')">
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
    <?= $pager->links('users', 'bootstrap_pagination') ?>
  </div>
</div>

<!-- ========================= MODAL: ADD USER ========================= -->
<div class="modal fade" id="modalAddUser" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" action="/master/user/store" id="formAddUser">
        <?= csrf_field() ?>

        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-0">Tambah User</h5>
            <small class="text-muted">Pilih section/process dengan checkbox</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="form-grid">

            <div class="row-item">
              <label>Username <span class="text-danger">*</span></label>
              <input type="text" name="username" class="form-control" required placeholder="contoh: operator01">
            </div>

            <div class="row-item">
              <label>Fullname</label>
              <input type="text" name="fullname" class="form-control" placeholder="contoh: Budi Santoso">
            </div>

            <div class="row-item">
              <label>Password <span class="text-danger">*</span></label>
              <input type="password" name="password" class="form-control" required placeholder="min 6 karakter">
            </div>

            <div class="row-item">
              <label>Role <span class="text-danger">*</span></label>
              <select name="role" class="form-select" id="add_role" required>
                <?php foreach (($roleOptions ?? []) as $r): ?>
                  <option value="<?= esc($r, 'attr') ?>"><?= esc($r) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="row-item">
              <label>Section / Process</label>
              <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                  <button type="button" class="btn btn-sm btn-outline-primary" id="add_check_all">Check all</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="add_uncheck_all">Uncheck all</button>
                </div>

                <div class="sections-grid" id="add_sections_wrap">
                  <?php foreach (($processes ?? []) as $p): ?>
                    <div class="form-check">
                      <input class="form-check-input add-section"
                             type="checkbox"
                             name="sections[]"
                             value="<?= esc($p['id'], 'attr') ?>"
                             id="add_sec_<?= esc($p['id'], 'attr') ?>">
                      <label class="form-check-label" for="add_sec_<?= esc($p['id'], 'attr') ?>">
                        <b><?= esc($p['process_code']) ?></b> - <?= esc($p['process_name']) ?>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>

                <small class="text-muted d-block mt-1">
                  Untuk role selain <b>ADMIN</b>, wajib pilih minimal 1 process.
                </small>
              </div>
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

<!-- ========================= MODAL: EDIT USER ========================= -->
<div class="modal fade" id="modalEditUser" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" id="formEditUser" action="">
        <?= csrf_field() ?>

        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-0">Edit User</h5>
            <small class="text-muted">Password opsional (kosongkan jika tidak mengganti)</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="form-grid">

            <div class="row-item">
              <label>Username <span class="text-danger">*</span></label>
              <input type="text" name="username" id="edit_username" class="form-control" required>
            </div>

            <div class="row-item">
              <label>Fullname</label>
              <input type="text" name="fullname" id="edit_fullname" class="form-control">
            </div>

            <div class="row-item">
              <label>Password Baru</label>
              <input type="password" name="password" id="edit_password" class="form-control" placeholder="kosongkan jika tidak mengganti">
            </div>

            <div class="row-item">
              <label>Role <span class="text-danger">*</span></label>
              <select name="role" class="form-select" id="edit_role" required>
                <?php foreach (($roleOptions ?? []) as $r): ?>
                  <option value="<?= esc($r, 'attr') ?>"><?= esc($r) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="row-item">
              <label>Section / Process</label>
              <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                  <button type="button" class="btn btn-sm btn-outline-primary" id="edit_check_all">Check all</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="edit_uncheck_all">Uncheck all</button>
                </div>

                <div class="sections-grid" id="edit_sections_wrap">
                  <?php foreach (($processes ?? []) as $p): ?>
                    <div class="form-check">
                      <input class="form-check-input edit-section"
                             type="checkbox"
                             name="sections[]"
                             value="<?= esc($p['id'], 'attr') ?>"
                             id="edit_sec_<?= esc($p['id'], 'attr') ?>">
                      <label class="form-check-label" for="edit_sec_<?= esc($p['id'], 'attr') ?>">
                        <b><?= esc($p['process_code']) ?></b> - <?= esc($p['process_name']) ?>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>

                <small class="text-muted d-block mt-1">
                  Untuk role selain <b>ADMIN</b>, wajib pilih minimal 1 process.
                </small>
              </div>
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

<!-- ========================= MODAL: PRIVILEGE DETAIL ========================= -->
<div class="modal fade" id="modalPrivilege" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Detail Privilege</h5>
          <small class="text-muted" id="priv_subtitle">-</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body" id="priv_body">
        <div class="text-muted">Loading...</div>
      </div>

    </div>
  </div>
</div>

<script>
  function setCheckboxGroup(selector, checked) {
    document.querySelectorAll(selector).forEach(cb => {
      if (!cb.disabled) cb.checked = checked;
    });
  }

  function setMultiFromCSV(selector, csv) {
    const values = new Set((csv || '').split(',').map(s => s.trim()).filter(Boolean));
    document.querySelectorAll(selector).forEach(cb => {
      cb.checked = values.has(cb.value);
    });
  }

  function toggleSectionCheckboxByRole(role, wrapSelector) {
    const disabled = (role === 'ADMIN');
    document.querySelectorAll(wrapSelector + ' input[type="checkbox"]').forEach(cb => {
      cb.checked = disabled ? false : cb.checked;
      cb.disabled = disabled;
    });
  }

  // ADD
  const addRole = document.getElementById('add_role');
  if (addRole) {
    toggleSectionCheckboxByRole(addRole.value, '#add_sections_wrap');
    addRole.addEventListener('change', () => toggleSectionCheckboxByRole(addRole.value, '#add_sections_wrap'));
  }
  document.getElementById('add_check_all')?.addEventListener('click', () => setCheckboxGroup('.add-section', true));
  document.getElementById('add_uncheck_all')?.addEventListener('click', () => setCheckboxGroup('.add-section', false));

  // EDIT modal populate
  const modalEdit = document.getElementById('modalEditUser');
  modalEdit?.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;

    const id = btn.getAttribute('data-id');
    const username = btn.getAttribute('data-username') || '';
    const fullname = btn.getAttribute('data-fullname') || '';
    const role = btn.getAttribute('data-role') || 'OPERATOR';
    const processIds = btn.getAttribute('data-process_ids') || '';

    document.getElementById('edit_username').value = username;
    document.getElementById('edit_fullname').value = fullname;
    document.getElementById('edit_password').value = '';
    document.getElementById('edit_role').value = role;

    setMultiFromCSV('.edit-section', processIds);
    toggleSectionCheckboxByRole(role, '#edit_sections_wrap');

    document.getElementById('formEditUser').action = '/master/user/update/' + id;
  });

  document.getElementById('edit_role')?.addEventListener('change', function () {
    toggleSectionCheckboxByRole(this.value, '#edit_sections_wrap');
  });

  document.getElementById('edit_check_all')?.addEventListener('click', () => setCheckboxGroup('.edit-section', true));
  document.getElementById('edit_uncheck_all')?.addEventListener('click', () => setCheckboxGroup('.edit-section', false));

  // PRIVILEGE modal load partial
  const modalPrivilege = document.getElementById('modalPrivilege');
  modalPrivilege?.addEventListener('show.bs.modal', async function (event) {
    const btn = event.relatedTarget;
    const id = btn.getAttribute('data-id');
    const username = btn.getAttribute('data-username');
    const role = btn.getAttribute('data-role');

    document.getElementById('priv_subtitle').textContent = `User: ${username} | Role: ${role}`;
    const body = document.getElementById('priv_body');
    body.innerHTML = `<div class="text-muted">Loading...</div>`;

    try {
      const res = await fetch('/master/user/' + id + '/privilege', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!res.ok) throw new Error('Gagal load privilege');
      const html = await res.text();
      body.innerHTML = html;
    } catch (e) {
      body.innerHTML = `<div class="alert alert-danger mb-0">${e.message}</div>`;
    }
  });
</script>

<?= $this->endSection() ?>
