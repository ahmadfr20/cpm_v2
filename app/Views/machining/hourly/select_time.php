<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">
    <i class="bi bi-clock-history me-2"></i>
    Pilih Jam Produksi – Machining
</h4>

<form method="get" class="card p-3">

    <input type="hidden" name="date" value="<?= esc($date) ?>">
    <input type="hidden" name="shift_id" value="<?= esc($shiftId) ?>">
    <input type="hidden" name="section" value="Machining">

    <div class="row g-3">
        <div class="col-md-6">
            <label>Time Slot</label>
            <select name="time_slot_id" class="form-select" required>
                <option value="">-- Pilih Jam --</option>
                <?php foreach ($timeSlots as $t): ?>
                    <option value="<?= $t['id'] ?>">
                        <?= $t['time_start'] ?> - <?= $t['time_end'] ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>

        <div class="col-md-6 d-flex align-items-end">
            <button class="btn btn-primary w-100">
                <i class="bi bi-pencil-square"></i> Input Hourly
            </button>
        </div>
    </div>

</form>

<?= $this->endSection() ?>
