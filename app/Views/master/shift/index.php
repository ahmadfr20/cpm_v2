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
    min-width: 1200px;
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

  .w-sec{ width:120px; }
  .w-days{ width:90px; }
  .w-shift{ width:60px; text-align:center; font-weight:800; }
  .w-time{ width:70px; text-align:center; font-weight:700; }
  .w-slots{ min-width:350px; }
  .w-total{ width:100px; text-align:right; font-weight:900; }
  .w-action{ width:120px; text-align:center; }

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
  
  /* Styling nomor slot */
  .slot-number {
    font-size: 11px;
    min-width: 22px;
    text-align: center;
  }

  @media print {
    body { background: #fff !important; }
    #sidebar, header, nav, footer, .modal, .btn, .w-action, .btn-remove-slot, .btn-add-slot, form[method="get"] { display: none !important; }
    .shift-wrap { border: none !important; overflow: visible !important; }
    .shift-table { min-width: 100% !important; margin: 0; }
    .select2-container--default .select2-selection--single { border: none !important; background: transparent !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { display: none !important; }
    .badge { border: 1px solid #000; color: #000 !important; background: transparent !important; }
  }
</style>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<?php 
$optsHtml = '<option value="">-- pilih --</option>';
foreach ($timeSlots as $ts) {
    $tsId = (int)$ts['id'];
    $st = substr((string)$ts['time_start'], 0, 5);
    $en = substr((string)$ts['time_end'], 0, 5);
    
    $mins = 0;
    if ($st && $en) {
        $sArr = explode(':', $st);
        $eArr = explode(':', $en);
        $mStart = ((int)$sArr[0] * 60) + (int)$sArr[1];
        $mEnd   = ((int)$eArr[0] * 60) + (int)$eArr[1];
        if ($mEnd <= $mStart) $mEnd += 1440;
        $mins = $mEnd - $mStart;
    }
    $label = $st . ' - ' . $en;
    $optsHtml .= sprintf(
        '<option value="%d" data-start="%s" data-end="%s" data-minutes="%d">%s</option>',
        $tsId, esc($st, 'attr'), esc($en, 'attr'), $mins, esc($label)
    );
}
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="mb-0">Master Shift</h4>
    <small class="text-muted d-print-none">
      Slot bebas ditambahkan/dihapus sesuai kebutuhan shift.
    </small>
  </div>

  <div class="d-flex gap-2">
    <button class="btn btn-outline-success fw-bold" onclick="exportExcel()">
      <i class="bi bi-file-earmark-excel"></i> Export Excel
    </button>
    <button class="btn btn-outline-danger fw-bold" onclick="window.print()">
      <i class="bi bi-printer"></i> Print / PDF
    </button>
    <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddShift">
      + Tambah Shift
    </button>
  </div>
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
        <th class="w-slots">time slots (berurutan)</th>
        <th class="w-total">total minutes</th>
        <th class="w-action">edit</th>
      </tr>
    </thead>

    <tbody>
    <?php if (empty($shifts)): ?>
      <tr>
        <td colspan="8" class="text-center text-muted py-4">Tidak ada data shift.</td>
      </tr>
    <?php else: ?>
      <?php foreach ($shifts as $s): ?>
        <?php $isNew = ((int)($newShiftId ?? 0) === (int)$s['id']); ?>
        <tr id="shift-row-<?= (int)$s['id'] ?>" class="<?= $isNew ? 'row-highlight' : '' ?>">
          
          <form method="post" action="/master/shift/update-slots/<?= (int)$s['id'] ?>" id="form-<?= (int)$s['id'] ?>"></form>
          <?= csrf_field() ?>
          <input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>" form="form-<?= (int)$s['id'] ?>">

          <td class="w-sec"><?= esc($s['section']) ?></td>
          <td class="w-days"><?= esc($s['days_label']) ?></td>
          <td class="w-shift"><?= (int)$s['shift_no'] ?></td>

          <td class="w-time"><span class="beginTxt"><?= esc($s['begin']) ?></span></td>
          <td class="w-time"><span class="endTxt"><?= esc($s['end']) ?></span></td>

          <td class="w-slots">
            <div class="d-flex flex-wrap align-items-center gap-2 slot-container">
              <?php foreach($s['slots'] as $idx => $slot): ?>
                <div class="slot-item d-flex align-items-center border rounded bg-white p-1">
                  <span class="badge bg-secondary me-1 slot-number"><?= $idx + 1 ?></span>
                  
                  <select class="form-select form-select-sm slotSelect" name="slots[]" style="width:140px;" form="form-<?= (int)$s['id'] ?>">
                    <?= str_replace('value="'.$slot['time_slot_id'].'"', 'value="'.$slot['time_slot_id'].'" selected', $optsHtml) ?>
                  </select>
                  <button type="button" class="btn btn-sm text-danger btn-remove-slot ms-1 px-1 py-0 border-0" title="Hapus Slot"><i class="bi bi-x-circle-fill"></i></button>
                </div>
              <?php endforeach; ?>
              
              <button type="button" class="btn btn-sm btn-outline-primary btn-add-slot">+ Slot</button>
            </div>
          </td>

          <td class="w-total"><span class="totalMinutes"><?= (int)$s['total_minutes'] ?></span></td>

          <td class="w-action">
            <button type="submit" form="form-<?= (int)$s['id'] ?>" class="btn btn-sm btn-warning btn-mini">
              <i class="bi bi-save"></i> Update
            </button>
            <div class="cell-muted mt-1">
              #<?= esc($s['shift_code'] ?: $s['shift_name']) ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>

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
                Setelah disimpan, shift baru muncul sebagai <b>row kosong</b>. Silakan gunakan tombol <b>+ Slot</b> untuk merakit slot waktunya.
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

<template id="slot-template">
  <div class="slot-item d-flex align-items-center border rounded bg-white p-1">
    <span class="badge bg-secondary me-1 slot-number">#</span>
    <select class="form-select form-select-sm slotSelect" name="slots[]" style="width:140px;">
      <?= $optsHtml ?>
    </select>
    <button type="button" class="btn btn-sm text-danger btn-remove-slot ms-1 px-1 py-0 border-0" title="Hapus Slot"><i class="bi bi-x-circle-fill"></i></button>
  </div>
</template>

<script>
(function(){
  // Initialize select2
  function initSelect2(container) {
    container.find('.slotSelect:not(.select2-hidden-accessible)').select2({
      width: '140px',
      placeholder: '-- pilih --',
      allowClear: true
    });
  }

  // Initial render
  initSelect2($('body'));

  // Logic presisi: Menghitung baris dan update nomor urut
  function recalcRow($tr){
    let total = 0;
    let validSlots = [];

    // 1. Kumpulkan data slot dan re-numbering
    $tr.find('.slot-item').each(function(index){
      // Update nomor badge berurutan (1, 2, 3...)
      $(this).find('.slot-number').text(index + 1);

      const select = $(this).find('.slotSelect');
      if (select.val()) {
        const opt = select[0].options[select[0].selectedIndex];
        validSlots.push({
          st: opt?.dataset?.start || '',
          en: opt?.dataset?.end || '',
          mins: parseInt(opt?.dataset?.minutes || '0', 10) || 0
        });
      }
    });

    // 2. Kalkulasi begin, end, dan total menit
    let begin = '-';
    let end = '-';
    if (validSlots.length > 0) {
      begin = validSlots[0].st;
      end = validSlots[validSlots.length - 1].en;
      validSlots.forEach(s => { total += s.mins; });
    }

    $tr.find('.totalMinutes').text(total);
    $tr.find('.beginTxt').text(begin);
    $tr.find('.endTxt').text(end);
  }

  // Trigger rekalkulasi saat nilai dropdown berubah
  $(document).on('change', '.slotSelect', function(){
    recalcRow($(this).closest('tr'));
  });

  // Action: Tambah Slot Dinamis
  $(document).on('click', '.btn-add-slot', function() {
    const tr = $(this).closest('tr');
    const formId = tr.find('form').attr('id'); // Ikat dengan form row tersebut
    
    // Ambil template
    const template = document.getElementById('slot-template').content.cloneNode(true);
    const select = template.querySelector('select');
    select.setAttribute('form', formId); // Bind form HTML5
    
    // Masukkan ke depan tombol Add
    $(this).before(template);
    
    // Init select2 pada elemen yang baru di-add
    initSelect2(tr);
    // Kalkulasi ulang (termasuk update nomor urut)
    recalcRow(tr);
  });

  // Action: Hapus Slot Dinamis
  $(document).on('click', '.btn-remove-slot', function() {
    const tr = $(this).closest('tr');
    $(this).closest('.slot-item').remove();
    // Kalkulasi ulang (nomor urut akan otomatis bergeser merapat)
    recalcRow(tr);
  });

  // Auto scroll saat shift baru ditambahkan
  const newShiftId = <?= (int)($newShiftId ?? 0) ?>;
  if (newShiftId > 0) {
    const el = document.getElementById('shift-row-' + newShiftId);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
  }
})();

function exportExcel() {
    const data = [];
    data.push(['Section', 'Days', 'Shift', 'Begin', 'End', 'Time Slots', 'Total Minutes']);
    
    document.querySelectorAll('.shift-table tbody tr').forEach(tr => {
        if(tr.querySelector('td.text-center.text-muted')) return;
        
        const sec = tr.querySelector('.w-sec')?.innerText.trim() || '';
        const days = tr.querySelector('.w-days')?.innerText.trim() || '';
        const shift = tr.querySelector('.w-shift')?.innerText.trim() || '';
        const begin = tr.querySelector('.beginTxt')?.innerText.trim() || '';
        const end = tr.querySelector('.endTxt')?.innerText.trim() || '';
        const total = tr.querySelector('.totalMinutes')?.innerText.trim() || '';
        
        let slots = [];
        tr.querySelectorAll('.slotSelect').forEach(sel => {
            if(sel.selectedIndex >= 0 && sel.value !== "") {
                const optText = sel.options[sel.selectedIndex].text;
                if(optText !== "-- pilih --") slots.push(optText);
            }
        });
        
        data.push([sec, days, shift, begin, end, slots.join(', '), total]);
    });
    
    const ws = XLSX.utils.aoa_to_sheet(data);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Master Shift");
    XLSX.writeFile(wb, "Master_Shift.xlsx");
}
</script>

<?= $this->endSection() ?>