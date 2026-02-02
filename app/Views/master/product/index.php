<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
  /* Sesuaikan angka 70px dengan tinggi navbar kamu */
  .modal-dialog {
    margin-top: 80px !important;
  }

   .modal.modal-navbar-offset .modal-dialog {
    margin-top: 80px !important; /* sesuaikan */
  }
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
        <form method="get" action="/master/product" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label mb-1">Cari (Part No / Name)</label>
                <input type="text"
                       name="keyword"
                       class="form-control"
                       placeholder="contoh: 12345 / Gear"
                       value="<?= esc($keyword ?? '') ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label mb-1">Customer</label>
                <select name="customer_id" class="form-select">
                    <option value="">-- Semua Customer --</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($customerId ?? '') == $c['id'] ? 'selected' : '' ?>>
                            <?= esc($c['customer_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ✅ Filter jumlah data -->
            <div class="col-md-2">
                <label class="form-label mb-1">Tampilkan</label>
                <select name="perPage" class="form-select" onchange="this.form.submit()">
                    <?php foreach (($perPageOptions ?? [10,15,25,50,100]) as $opt): ?>
                        <option value="<?= $opt ?>" <?= (($perPage ?? 15) == $opt ? 'selected' : '') ?>>
                            <?= $opt ?> data
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
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:70px;" class="text-center">No</th>
                        <th style="width:140px;">Part No</th>
                        <th>Product Name</th>
                        <th style="width:200px;">Customer</th>
                        <th style="width:120px;" class="text-end">Ascas (gr)</th>
                        <th style="width:120px;" class="text-end">Runner (gr)</th>
                        <th style="width:120px;" class="text-center">CT (sec)</th>
                        <th style="width:90px;" class="text-center">Cavity</th>
                        <th style="width:120px;" class="text-center">Eff (%)</th>
                        <th style="min-width:200px;">Notes</th>
                        <th style="width:180px;" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $page    = $pager->getCurrentPage('products') ?? 1;
                $perP    = $pager->getPerPage('products') ?? (int)($perPage ?? 15);
                $no      = 1 + ($page - 1) * $perP;
                ?>

                <?php if (!empty($products) && count($products) > 0): ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td class="fw-semibold"><?= esc($p['part_no']) ?></td>
                            <td><?= esc($p['part_name']) ?></td>
                            <td><?= esc($p['customer_name'] ?? '-') ?></td>

                            <td class="text-end"><?= number_format((float)($p['weight_ascas'] ?? 0), 0) ?></td>
                            <td class="text-end"><?= number_format((float)($p['weight_runner'] ?? 0), 0) ?></td>
                            <td class="text-center"><?= esc($p['cycle_time'] ?? '') ?></td>
                            <td class="text-center"><?= esc($p['cavity'] ?? '') ?></td>
                            <td class="text-center"><?= esc($p['efficiency_rate'] ?? '') ?></td>
                            <td><?= esc($p['notes'] ?? '-') ?></td>

                            <td class="text-end">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-warning btnEditProduct"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEditProduct"
                                    data-id="<?= esc($p['id']) ?>"
                                    data-part_no="<?= esc($p['part_no'] ?? '') ?>"
                                    data-part_name="<?= esc($p['part_name'] ?? '') ?>"
                                    data-customer_id="<?= esc($p['customer_id'] ?? '') ?>"
                                    data-weight_ascas="<?= esc($p['weight_ascas'] ?? 0) ?>"
                                    data-weight_runner="<?= esc($p['weight_runner'] ?? 0) ?>"
                                    data-cycle_time="<?= esc($p['cycle_time'] ?? '') ?>"
                                    data-cavity="<?= esc($p['cavity'] ?? '') ?>"
                                    data-efficiency_rate="<?= esc($p['efficiency_rate'] ?? '') ?>"
                                    data-notes="<?= esc($p['notes'] ?? '') ?>"
                                >
                                    <i class="bi bi-pencil"></i> Edit
                                </button>

                                <a href="/master/product/delete/<?= $p['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Hapus product ini?')">
                                    <i class="bi bi-trash"></i> Hapus
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">
                            Tidak ada data.
                        </td>
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
<div class="modal fade" id="modalAddProduct" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" action="/master/product/store">
                <?= csrf_field() ?>

                <div class="modal-header">
                    <h5 class="modal-title">Tambah Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Part No</label>
                            <input type="text" name="part_no" class="form-control" required>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Product Name</label>
                            <input type="text" name="part_name" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Customer</label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">-- Pilih Customer --</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= esc($c['customer_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Ascas (gr)</label>
                            <input type="number" step="0.01" name="weight_ascas" class="form-control" value="0">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Runner (gr)</label>
                            <input type="number" step="0.01" name="weight_runner" class="form-control" value="0">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Cycle Time (sec)</label>
                            <input type="number" step="0.01" name="cycle_time" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Cavity</label>
                            <input type="number" step="1" name="cavity" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Efficiency Rate (%)</label>
                            <input type="number" step="0.01" name="efficiency_rate" class="form-control">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" rows="3" class="form-control" placeholder="Catatan..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-save"></i> Simpan
                    </button>
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Tutup</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================= MODAL: EDIT PRODUCT ========================= -->
<div class="modal fade" id="modalEditProduct" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" id="formEditProduct" action="">
                <?= csrf_field() ?>

                <div class="modal-header">
                    <h5 class="modal-title">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Part No</label>
                            <input type="text" id="edit_part_no" name="part_no" class="form-control" required>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Product Name</label>
                            <input type="text" id="edit_part_name" name="part_name" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Customer</label>
                            <select id="edit_customer_id" name="customer_id" class="form-select" required>
                                <option value="">-- Pilih Customer --</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= esc($c['customer_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Ascas (gr)</label>
                            <input type="number" step="0.01" id="edit_weight_ascas" name="weight_ascas" class="form-control">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Runner (gr)</label>
                            <input type="number" step="0.01" id="edit_weight_runner" name="weight_runner" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Cycle Time (sec)</label>
                            <input type="number" step="0.01" id="edit_cycle_time" name="cycle_time" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Cavity</label>
                            <input type="number" step="1" id="edit_cavity" name="cavity" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Efficiency Rate (%)</label>
                            <input type="number" step="0.01" id="edit_efficiency_rate" name="efficiency_rate" class="form-control">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea id="edit_notes" name="notes" rows="3" class="form-control"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-save"></i> Update
                    </button>
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Tutup</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const formEdit = document.getElementById('formEditProduct');

    document.querySelectorAll('.btnEditProduct').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            formEdit.setAttribute('action', '/master/product/update/' + id);

            document.getElementById('edit_part_no').value = this.getAttribute('data-part_no') || '';
            document.getElementById('edit_part_name').value = this.getAttribute('data-part_name') || '';
            document.getElementById('edit_customer_id').value = this.getAttribute('data-customer_id') || '';

            document.getElementById('edit_weight_ascas').value = this.getAttribute('data-weight_ascas') || 0;
            document.getElementById('edit_weight_runner').value = this.getAttribute('data-weight_runner') || 0;
            document.getElementById('edit_cycle_time').value = this.getAttribute('data-cycle_time') || '';
            document.getElementById('edit_cavity').value = this.getAttribute('data-cavity') || '';
            document.getElementById('edit_efficiency_rate').value = this.getAttribute('data-efficiency_rate') || '';
            document.getElementById('edit_notes').value = this.getAttribute('data-notes') || '';
        });
    });
});
</script>

<?= $this->endSection() ?>


