<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>


<?php if (session()->getFlashdata('error')) : ?>
  <div class="alert alert-danger" role="alert">
    <?= esc(session()->getFlashdata('error')) ?>
  </div>
<?php endif; ?>

<h3>Welcome, <?= esc($fullname) ?> 👋</h3>
<p class="text-muted">Silakan pilih menu dashboard</p>


<div class="row mt-4">
    <div class="col-md-4">
        <a href="/dashboard/asakai" class="card shadow-sm text-decoration-none">
            <div class="card-body text-center">
                <h5>ASAKAI</h5>
                <small>Daily KPI Meeting</small>
            </div>
        </a>
    </div>

    <div class="col-md-4">
        <a href="/dashboard/wip" class="card shadow-sm text-decoration-none">
            <div class="card-body text-center">
                <h5>WIP</h5>
                <small>Work In Process</small>
            </div>
        </a>
    </div>

    <div class="col-md-4">
        <a href="/dashboard/inventory" class="card shadow-sm text-decoration-none">
            <div class="card-body text-center">
                <h5>Inventory</h5>
                <small>Raw & Finish Good</small>
            </div>
        </a>
    </div>
</div>

<?= $this->endSection() ?>
