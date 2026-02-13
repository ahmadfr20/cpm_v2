<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
  :root{
    --grid:#cfcfcf;
    --head:#ffe600;
    --rowHover:#fffbe0;
    --stripe:#fafafa;
  }

  .shift-wrap{
    overflow:auto;
    border:1px solid #ddd;
    border-radius:10px;
    background:#fff;
  }

  .shift-table{
    min-width: 1700px;
    margin:0;
    border-collapse: separate;
    border-spacing: 0;
  }

  .shift-table thead th{
    position: sticky;
    top: 0;
    z-index: 3;
    background: var(--head);
    font-weight: 800;
    text-transform: lowercase;
    border: 1px solid #999;
    white-space: nowrap;
    padding: 10px 8px;
    text-align: center;
  }

  .shift-table td{
    border: 1px solid var(--grid);
    white-space: nowrap;
    padding: 8px 8px;
    vertical-align: middle;
  }

  .shift-table tbody tr:nth-child(even) td{ background: var(--stripe); }
  .shift-table tbody tr:hover td{ background: var(--rowHover); }

  .w-sec{ width:140px; }
  .w-days{ width:110px; }
  .w-shift{ width:60px; text-align:center; font-weight:800; }
  .w-time{ width:85px; text-align:center; font-weight:700; }
  .w-slot{ width:190px; }
  .w-total{ width:120px; text-align:right; font-weight:900; }
  .w-action{ width:130px; text-align:center; }

  .cell-muted{ color:#777; font-size:12px; line-height: 1.2; }
  .btn-mini{ padding:4px 10px; }

  .row-highlight td{
    outline: 2px solid #2d7ff9;
    outline-offset:-2px;
  }

  .modal .modal-dialog{ margin-top: 80px !important; }

  /* select2 compact */
  .select2-container .select2-selection--single{ height: 32px; }
  .select2-container--default .select2-selection--single .select2-selection__rendered{
    line-height: 32px;
    padding-left: 10px;
  }
  .select2-container--default .select2-selection--single .select2-selection__arrow{ height: 32px; }
  .select2-container{ width:100% !important; }
  .select2-results__option{ font-size: 13px; }
</style>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="mb-0">Master Shift</h4>
    <small class="text-muted">
      Slot hanya 1 kolom (Time Slot). Begin / End & Total Minutes otomatis dihitung.
    </small>
  </div>

  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddShift">
    + Tambah Shift
  </button>
</div>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= esc(session()->getFlashdata('success')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= esc(session()->getFlashdata('error')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="shift-wrap">
  <table class="table shift-table">
    <thead>
      <tr>
        <th class="w-sec">section</th>
        <th class="w-days">days</th>
        <th class="w-shift">shift</th>
        <th class="w-time">begin</th>
        <th class="w-time">end</th>

        <?php for($i=1;$i<=10;$i++): ?>
          <th class="w-slot">slot <?= $i ?></th>
        <?php endfor; ?>

        <th class="w-total">total minutes</th>
        <th class="w-action">edit</th>
      </tr>
    </thead>

    <tbody>
    <?php if (empty($shifts)): ?>
      <tr>
        <td colspan="18" class="text-center text-muted py-4">Tidak ada data shift.</td>
      </tr>
    <?php else: ?>
      <?php foreach ($shifts as $s): ?>
        <?php $isNew = ((int)($newShiftId ?? 0) === (int)$s['id']); ?>
        <tr id="shift-row-<?= (int)$s['id'] ?>" class="<?= $isNew ? 'row-highlight' : '' ?>">
          <form method="post" action="/master/shift/update-slots/<?= (int)$s['id'] ?>" class="rowForm">
            <?= csrf_field() ?>

            <td class="w-sec"><?= esc($s['section']) ?></td>
            <td class="w-days"><?= esc($s['days_label']) ?></td>
            <td class="w-shift"><?= (int)$s['shift_no'] ?></td>

            <td class="w-time"><span class="beginTxt"><?= esc($s['begin'] ?: '-') ?></span></td>
            <td class="w-time"><span class="endTxt"><?= esc($s['end'] ?: '-') ?></span></td>

            <?php for($i=1;$i<=10;$i++): ?>
              <?php
                $slot = $s['slots'][$i] ?? ['time_slot_id'=>null,'start'=>'','end'=>'','minutes'=>0];
                $selectedId = $slot['time_slot_id'];
              ?>
              <td class="w-slot">
                <select class="form-select form-select-sm slotSelect"
                        name="slots[<?= $i ?>]"
                        data-slot="<?= $i ?>">
                  <option value="">-- pilih --</option>

                  <?php foreach ($timeSlots as $ts): ?>
                    <?php
                      $tsId = (int)$ts['id'];
                      $st = substr((string)$ts['time_start'], 0, 5);
                      $en = substr((string)$ts['time_end'], 0, 5);

                      $mins = 0;
                      $t1 = strtotime((string)$ts['time_start']);
                      $t2 = strtotime((string)$ts['time_end']);
                      if ($t1 !== false && $t2 !== false && $t2 > $t1) $mins = (int)(($t2-$t1)/60);

                      // ✅ label hanya jam start - end
                      $label = $st . ' - ' . $en;
                    ?>
                    <option
                      value="<?= $tsId ?>"
                      <?= ($selectedId === $tsId) ? 'selected' : '' ?>
                      data-start="<?= esc($st,'attr') ?>"
                      data-end="<?= esc($en,'attr') ?>"
                      data-minutes="<?= (int)$mins ?>"
                    >
                      <?= esc($label) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
            <?php endfor; ?>

            <td class="w-total"><span class="totalMinutes"><?= (int)$s['total_minutes'] ?></span></td>

            <td class="w-action">
              <button type="submit" class="btn btn-sm btn-warning btn-mini">
                <i class="bi bi-save"></i> Update
              </button>
              <div class="cell-muted mt-1">
                #<?= esc($s['shift_code'] ?: $s['shift_name']) ?>
              </div>
            </td>
          </form>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ===================== MODAL ADD SHIFT ===================== -->
<div class="modal fade" id="modalAddShift" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" action="/master/shift/store-index">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Tambah Shift</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Shift Code</label>
              <input type="text" name="shift_code" class="form-control" required>
            </div>

            <div class="col-md-8">
              <label class="form-label">Shift Name</label>
              <input type="text" name="shift_name" class="form-control" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Day Group</label>
              <select name="day_group" class="form-select">
                <option value="MON_THU">Senin - Kamis</option>
                <option value="FRI">Jumat</option>
                <option value="SAT">Sabtu</option>
                <option value="SUN">Minggu</option>
              </select>
            </div>

            <div class="col-md-8 d-flex align-items-end">
              <div class="text-muted">
                Setelah disimpan, shift baru muncul sebagai <b>row kosong</b> untuk diisi slot.
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success">
            <i class="bi bi-save"></i> Simpan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  function initSelect2(){
    $('.slotSelect').select2({
      width: '100%',
      placeholder: '-- pilih --',
      allowClear: true
    });
  }
  initSelect2();

  function recalcRow($form){
    let total = 0, begin = '', end = '';

    $form.find('.slotSelect').each(function(){
      const opt = this.options[this.selectedIndex];
      const st = opt?.dataset?.start || '';
      const en = opt?.dataset?.end || '';
      const mins = parseInt(opt?.dataset?.minutes || '0', 10) || 0;

      if (!begin && st) begin = st;
      if (en) end = en;
      total += mins;
    });

    $form.find('.totalMinutes').text(total);
    $form.find('.beginTxt').text(begin || '-');
    $form.find('.endTxt').text(end || '-');
  }

  $(document).on('change', '.slotSelect', function(){
    recalcRow($(this).closest('form'));
  });

  $('form.rowForm').each(function(){
    recalcRow($(this));
  });

  const newShiftId = <?= (int)($newShiftId ?? 0) ?>;
  if (newShiftId > 0) {
    const el = document.getElementById('shift-row-' + newShiftId);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
  }
})();
</script>

<?= $this->endSection() ?>
