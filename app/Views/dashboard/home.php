<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>


<?php if (session()->getFlashdata('error')) : ?>
  <div class="alert alert-danger" role="alert">
    <?= esc(session()->getFlashdata('error')) ?>
  </div>
<?php endif; ?>

<?php if ($logged_in): ?>
    <h3>Welcome, <?= esc($fullname) ?> 👋</h3>
    <p class="text-muted">Silakan pilih menu dari sidebar</p>
<?php else: ?>
    <h3>Selamat Datang di CPM Shop Floor 👋</h3>
    <p class="text-muted">Anda dapat melihat Dashboard, WIP List, Inventory FG, dan Delivery Control Board tanpa login. <a href="/login">Login</a> untuk mengakses fitur lengkap.</p>
<?php endif; ?>


<div class="row mt-4 g-3">
    <div class="col-md-3">
        <a href="/wip/inventory" class="card shadow-sm text-decoration-none h-100">
            <div class="card-body text-center py-4">
                <i class="bi bi-layers fs-2 text-primary mb-2 d-block"></i>
                <h6 class="fw-bold mb-0">WIP List</h6>
                <small class="text-muted">Work In Process Inventory</small>
            </div>
        </a>
    </div>

    <div class="col-md-3">
        <a href="/inventory-fg" class="card shadow-sm text-decoration-none h-100">
            <div class="card-body text-center py-4">
                <i class="bi bi-box-seam fs-2 text-success mb-2 d-block"></i>
                <h6 class="fw-bold mb-0">Inventory FG</h6>
                <small class="text-muted">Finished Good Inventory</small>
            </div>
        </a>
    </div>

    <div class="col-md-3">
        <a href="/finished-good/delivery-control-board" class="card shadow-sm text-decoration-none h-100">
            <div class="card-body text-center py-4">
                <i class="bi bi-clipboard2-data fs-2 text-warning mb-2 d-block"></i>
                <h6 class="fw-bold mb-0">Delivery Control Board</h6>
                <small class="text-muted">Monitor Target & Aktual Delivery</small>
            </div>
        </a>
    </div>

    <?php if ($logged_in): ?>
    <div class="col-md-3">
        <a href="/dashboard/asakai" class="card shadow-sm text-decoration-none h-100">
            <div class="card-body text-center py-4">
                <i class="bi bi-bar-chart-line fs-2 text-info mb-2 d-block"></i>
                <h6 class="fw-bold mb-0">ASAKAI</h6>
                <small class="text-muted">Daily KPI Meeting</small>
            </div>
        </a>
    </div>

    <div class="col-md-3">
        <a href="/dashboard/daily-performance" class="card shadow-sm text-decoration-none h-100">
            <div class="card-body text-center py-4">
                <i class="bi bi-graph-up-arrow fs-2 text-primary mb-2 d-block"></i>
                <h6 class="fw-bold mb-0">Daily Performance</h6>
                <small class="text-muted">Machine Performance KPI</small>
            </div>
        </a>
    </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
