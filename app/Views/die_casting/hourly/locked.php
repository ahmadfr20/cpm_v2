<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<div class="container mt-4">

    <div class="alert alert-danger text-center p-4">
        <h4 class="mb-3">
            <i class="bi bi-lock-fill me-2"></i>
            HOURLY PRODUCTION TERKUNCI
        </h4>

        <p class="mb-2">
            <?= esc($message ?? 'Input hourly production tidak tersedia saat ini.') ?>
        </p>

        <hr>

        <p class="mb-0 text-muted">
            ⏰ Input hanya dapat dilakukan pada jam time slot shift yang aktif.
        </p>
    </div>

    <div class="text-center mt-3">
        <a href="/dashboard" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
        </a>
    </div>

</div>

<?= $this->endSection() ?>
