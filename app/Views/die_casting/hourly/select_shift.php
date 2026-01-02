<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">
    <i class="bi bi-clock-history me-2"></i>
    Hourly Production – Die Casting
</h4>

<form method="get" class="card p-3">

    <div class="row g-3">
        <div class="col-md-4">
            <label>Tanggal</label>
            <input type="date"
                   name="date"
                   value="<?= esc($date) ?>"
                   class="form-control">
        </div>

        <div class="col-md-4">
            <label>Shift</label>
            <select name="shift_id" class="form-select" required>
                <option value="">-- Pilih Shift --</option>
                <?php foreach ($shifts as $s): ?>
                    <option value="<?= $s['id'] ?>">
                        <?= esc($s['shift_name']) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>

        <div class="col-md-4 d-flex align-items-end">
            <button class="btn btn-primary w-100">
                <i class="bi bi-arrow-right-circle"></i> Lanjut
            </button>
        </div>
    </div>

</form>

<?= $this->endSection() ?>
