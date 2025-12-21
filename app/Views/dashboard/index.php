<?= $this->extend('layout/layout') ?>

<?= $this->section('content') ?>

<h3>Dashboard CPM</h3>

<div class="row mt-4">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6>Target Hari Ini</h6>
                <h3>1,200</h3>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6>Actual Produksi</h6>
                <h3>980</h3>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6>Balance</h6>
                <h3>220</h3>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
