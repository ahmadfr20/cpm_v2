<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">
    <i class="bi bi-calendar2-week me-2"></i>
    Daily Schedule Machining
</h4>

<form method="post" action="/machining/schedule/store" class="card p-3">
<?= csrf_field() ?>

<div class="row mb-3">
    <div class="col-md-3">
        <label>Tanggal</label>
        <input name="date" class="form-control" value="<?= $date ?>" readonly>
    </div>

    <div class="col-md-3">
        <label>Shift</label>
        <select name="shift_id" class="form-select">
            <?php foreach ($shifts as $s): ?>
                <option value="<?= $s['id'] ?>"><?= $s['shift_name'] ?></option>
            <?php endforeach ?>
        </select>
    </div>
</div>

<hr>

<h6>Detail Jadwal</h6>

<div id="items">
    <div class="row g-2 mb-2 item">
        <div class="col-md-2">
            <select name="items[0][machine_id]" class="form-select">
                <?php foreach ($machines as $m): ?>
                    <option value="<?= $m['id'] ?>"><?= $m['machine_name'] ?></option>
                <?php endforeach ?>
            </select>
        </div>

        <div class="col-md-3">
            <select name="items[0][product_id]" class="form-select">
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>">
                        <?= $p['part_no'] ?> - <?= $p['part_name'] ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>

        <div class="col-md-1">
            <input name="items[0][cycle_time]" class="form-control" placeholder="CT">
        </div>

        <div class="col-md-1">
            <input name="items[0][cavity]" class="form-control" placeholder="Cav">
        </div>

        <div class="col-md-2">
            <input name="items[0][target_hour]" class="form-control" placeholder="Target/Jam">
        </div>

        <div class="col-md-2">
            <input name="items[0][target_shift]" class="form-control" placeholder="Target/Shift">
        </div>
    </div>
</div>

<button type="submit" class="btn btn-primary mt-3">
    <i class="bi bi-save"></i> Simpan Jadwal
</button>

</form>

<?= $this->endSection() ?>
