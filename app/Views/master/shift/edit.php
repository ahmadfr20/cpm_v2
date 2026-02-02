<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4 class="mb-0">Edit Shift</h4>
        <small class="text-muted">Ubah data shift dan pilih time slot yang berlaku</small>
    </div>
    <a href="/master/shift" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="/master/shift/update/<?= $shift['id'] ?>">
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Shift Code</label>
                    <input name="shift_code" class="form-control"
                           value="<?= esc($shift['shift_code']) ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Shift Name</label>
                    <input name="shift_name" class="form-control"
                           value="<?= esc($shift['shift_name']) ?>" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-select">
                        <option value="1" <?= ($shift['is_active'] ? 'selected' : '') ?>>Active</option>
                        <option value="0" <?= (!$shift['is_active'] ? 'selected' : '') ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-2">
                <div>
                    <h6 class="mb-0">Time Slot</h6>
                    <small class="text-muted">Centang time slot yang dipakai untuk shift ini</small>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" id="btnCheckAll" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-check2-square"></i> Pilih Semua
                    </button>
                    <button type="button" id="btnUncheckAll" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-square"></i> Kosongkan
                    </button>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-6 col-lg-4">
                    <input type="text" id="tsSearch" class="form-control"
                           placeholder="Cari time slot (contoh: TS01 / 07:30)...">
                </div>
                <div class="col-md-6 col-lg-8 d-flex align-items-center">
                    <small class="text-muted" id="tsCountInfo"></small>
                </div>
            </div>

            <div class="border rounded p-3 bg-light">
                <div class="row g-2" id="tsContainer">
                    <?php foreach ($timeSlots as $ts): ?>
                        <?php
                            $label = $ts['time_code'].' ('.$ts['time_start'].' - '.$ts['time_end'].')';
                            $isChecked = in_array($ts['id'], $selected);
                        ?>
                        <div class="col-12 col-md-6 col-lg-4 ts-item"
                             data-text="<?= esc(strtolower($label)) ?>">
                            <label class="d-flex align-items-start gap-2 p-2 bg-white border rounded h-100">
                                <input class="form-check-input mt-1 ts-checkbox"
                                       type="checkbox"
                                       name="time_slots[]"
                                       value="<?= (int)$ts['id'] ?>"
                                       <?= $isChecked ? 'checked' : '' ?>>
                                <div>
                                    <div class="fw-semibold"><?= esc($ts['time_code']) ?></div>
                                    <div class="small text-muted">
                                        <?= esc($ts['time_start']) ?> - <?= esc($ts['time_end']) ?>
                                    </div>
                                </div>
                            </label>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>

            <!-- sticky action bar -->
            <div class="position-sticky bottom-0 bg-white pt-3 mt-4 border-top">
                <div class="d-flex justify-content-end gap-2">
                    <a href="/master/shift" class="btn btn-outline-secondary">
                        Batal
                    </a>
                    <button class="btn btn-primary">
                        <i class="bi bi-save"></i> Update
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const searchInput = document.getElementById('tsSearch');
    const items = Array.from(document.querySelectorAll('.ts-item'));
    const checkboxes = Array.from(document.querySelectorAll('.ts-checkbox'));
    const info = document.getElementById('tsCountInfo');

    function updateInfo() {
        const visible = items.filter(el => el.style.display !== 'none').length;
        const checked = checkboxes.filter(cb => cb.checked).length;
        info.textContent = `Ditampilkan: ${visible} / ${items.length} time slot • Terpilih: ${checked}`;
    }

    function filterList() {
        const q = (searchInput.value || '').trim().toLowerCase();
        items.forEach(el => {
            const text = el.getAttribute('data-text') || '';
            el.style.display = text.includes(q) ? '' : 'none';
        });
        updateInfo();
    }

    document.getElementById('btnCheckAll').addEventListener('click', () => {
        // hanya yang terlihat
        items.forEach(el => {
            if (el.style.display === 'none') return;
            const cb = el.querySelector('.ts-checkbox');
            if (cb) cb.checked = true;
        });
        updateInfo();
    });

    document.getElementById('btnUncheckAll').addEventListener('click', () => {
        items.forEach(el => {
            if (el.style.display === 'none') return;
            const cb = el.querySelector('.ts-checkbox');
            if (cb) cb.checked = false;
        });
        updateInfo();
    });

    searchInput.addEventListener('input', filterList);
    checkboxes.forEach(cb => cb.addEventListener('change', updateInfo));

    // init
    filterList();
})();
</script>

<?= $this->endSection() ?>
