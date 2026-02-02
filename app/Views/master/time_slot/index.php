<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-0">Master Time Slot</h4>
        <small class="text-muted">Kelola data jam/time slot produksi</small>
    </div>

    <!-- tombol create -> modal -->
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateTimeSlot">
        <i class="bi bi-plus"></i> Tambah Time Slot
    </button>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= session()->getFlashdata('success') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="get" action="/master/time-slot" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label mb-1">Cari (Kode)</label>
                <input type="text" name="q" value="<?= esc($q) ?>" class="form-control" placeholder="contoh: TS01">
            </div>

            <div class="col-md-3">
                <label class="form-label mb-1">Tampilkan</label>
                <select name="perPage" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($perPageOptions as $opt): ?>
                        <option value="<?= $opt ?>" <?= ($perPage == $opt ? 'selected' : '') ?>>
                            <?= $opt ?> data
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-5 d-flex gap-2">
                <button class="btn btn-outline-primary" type="submit">
                    <i class="bi bi-search"></i> Filter
                </button>

                <a class="btn btn-outline-secondary" href="/master/time-slot">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </a>
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
                        <th style="width: 140px;">Kode</th>
                        <th style="width: 240px;">Jam</th>
                        <th class="text-end" style="width: 200px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($timeSlots)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-4 text-muted">Data tidak ditemukan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($timeSlots as $t): ?>
                            <tr>
                                <td class="fw-semibold"><?= esc($t['time_code']) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= esc($t['time_start']) ?> - <?= esc($t['time_end']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <!-- tombol edit -> modal -->
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-warning btn-edit-timeslot"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalEditTimeSlot"
                                        data-id="<?= (int)$t['id'] ?>"
                                        data-code="<?= esc($t['time_code']) ?>"
                                        data-start="<?= esc($t['time_start']) ?>"
                                        data-end="<?= esc($t['time_end']) ?>"
                                    >
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>

                                    <a href="/master/time-slot/delete/<?= (int)$t['id'] ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Hapus time slot ini?')">
                                        <i class="bi bi-trash"></i> Hapus
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="p-3 d-flex justify-content-end">
        <?= $pager->links('timeslots', 'bootstrap_pagination') ?>
    </div>
</div>


<!-- =========================
     MODAL CREATE
========================= -->
<div class="modal fade" id="modalCreateTimeSlot" tabindex="-1" aria-labelledby="modalCreateTimeSlotLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="/master/time-slot/store" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title" id="modalCreateTimeSlotLabel">Tambah Time Slot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Kode Time Slot</label>
                    <input type="text" name="time_code" class="form-control" placeholder="contoh: TS01" required maxlength="10" autocomplete="off">
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Jam Mulai</label>
                        <input type="time" name="time_start" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jam Selesai</label>
                        <input type="time" name="time_end" class="form-control" required>
                    </div>
                </div>

                <div class="form-text mt-2">
                    Boleh melewati tengah malam (contoh: 22:00 - 06:00).
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>


<!-- =========================
     MODAL EDIT
========================= -->
<div class="modal fade" id="modalEditTimeSlot" tabindex="-1" aria-labelledby="modalEditTimeSlotLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" id="formEditTimeSlot" action="#" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditTimeSlotLabel">Edit Time Slot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="edit_id" value="">

                <div class="mb-3">
                    <label class="form-label">Kode Time Slot</label>
                    <input type="text" name="time_code" id="edit_time_code" class="form-control" required maxlength="10" autocomplete="off">
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Jam Mulai</label>
                        <input type="time" name="time_start" id="edit_time_start" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jam Selesai</label>
                        <input type="time" name="time_end" id="edit_time_end" class="form-control" required>
                    </div>
                </div>

                <div class="form-text mt-2">
                    Pastikan jam sesuai (boleh melewati tengah malam).
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


<!-- =========================
     SCRIPT: isi modal edit
========================= -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const editButtons = document.querySelectorAll('.btn-edit-timeslot');
    const formEdit    = document.getElementById('formEditTimeSlot');

    const inpCode  = document.getElementById('edit_time_code');
    const inpStart = document.getElementById('edit_time_start');
    const inpEnd   = document.getElementById('edit_time_end');

    editButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const id    = btn.getAttribute('data-id');
            const code  = btn.getAttribute('data-code');
            const start = btn.getAttribute('data-start');
            const end   = btn.getAttribute('data-end');

            // set action ke endpoint update
            formEdit.action = '/master/time-slot/update/' + id;

            // isi value
            inpCode.value  = code || '';
            inpStart.value = start || '';
            inpEnd.value   = end || '';
        });
    });
});
</script>

<?= $this->endSection() ?>
