<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">
     <i class="bi bi-tools me-2 text-warning"></i> JADWAL DANDORI – DIE CASTING
  </h4>
</div>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success shadow-sm"><i class="bi bi-check-circle-fill me-2"></i><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<form method="get" class="mb-3">
  <input type="date" name="date" value="<?= esc($date) ?>" class="form-control fw-bold text-primary" style="max-width:240px" onchange="this.form.submit()">
</form>

<form method="post" action="<?= site_url('/die-casting/dandori/store') ?>">
<?= csrf_field() ?>
<input type="hidden" name="date" value="<?= esc($date) ?>">

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
  .select2-container .select2-selection--single { height: 34px; }
  .select2-container--default .select2-selection--single .select2-selection__rendered{ line-height: 34px; font-size: 13px; }
  .select2-container--default .select2-selection--single .select2-selection__arrow{ height: 34px; }
</style>

<?php foreach ($shifts as $shift): ?>
  <div class="card mb-4 shadow-sm border-0">
    <div class="card-header bg-white pt-3 border-bottom-0">
      <h5 class="mb-0 text-primary fw-bold">
        <i class="bi bi-clock-history"></i> <?= esc($shift['shift_name']) ?>
      </h5>
    </div>
    
    <div class="table-responsive">
      <table class="table table-bordered table-sm text-center align-middle mb-0" id="table-shift-<?= $shift['id'] ?>">
        <thead class="table-light">
          <tr>
            <th style="width:180px;">Mesin</th>
            <th style="min-width:300px;">Part Produk</th>
            <th style="width:180px;">Waktu Dandori</th>
            <th>Detail Aktivitas</th>
            <th style="width:60px;">Hapus</th>
          </tr>
        </thead>
        <tbody id="tbody-shift-<?= $shift['id'] ?>">
        
        <?php $rows = $map[$shift['id']] ?? []; ?>
        
        <tr class="empty-row" style="<?= empty($rows) ? '' : 'display:none;' ?>">
            <td colspan="5" class="text-muted py-4">Belum ada jadwal Dandori pada shift ini. Klik tombol di bawah untuk menambah manual.</td>
        </tr>

        <?php foreach ($rows as $d): $uuid = uniqid('row_'); ?>
          <tr class="dandori-row">
            <td>
              <input type="hidden" name="items[<?= $uuid ?>][shift_id]" value="<?= $shift['id'] ?>">
              <select class="form-select form-select-sm machine-select fw-bold" name="items[<?= $uuid ?>][machine_id]" required>
                <option value="">- Pilih Mesin -</option>
                <?php foreach ($machines as $m): ?>
                  <option value="<?= $m['id'] ?>" <?= ($m['id'] == $d['machine_id']) ? 'selected' : '' ?>>
                    <?= esc($m['machine_code']) ?>
                  </option>
                <?php endforeach ?>
              </select>
            </td>
            
            <td class="text-start">
              <select class="form-select form-select-sm product-select" name="items[<?= $uuid ?>][product_id]" required>
                <option value="">- Cari Part -</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= ($p['id'] == $d['product_id']) ? 'selected' : '' ?>>
                        <?= esc($p['part_no'] . ' - ' . $p['part_name']) ?>
                    </option>
                <?php endforeach; ?>
              </select>
            </td>

            <td>
              <select class="form-select form-select-sm bg-warning-subtle" name="items[<?= $uuid ?>][time_slot_id]">
                  <option value="">-- Set Waktu --</option>
                  <?php foreach ($shiftSlots[$shift['id']] as $slot): ?>
                      <option value="<?= $slot['time_slot_id'] ?>" <?= ($d['time_slot_id'] == $slot['time_slot_id']) ? 'selected' : '' ?>>
                          <?= $slot['label'] ?>
                      </option>
                  <?php endforeach; ?>
              </select>
            </td>

            <td>
              <input type="text" class="form-control form-control-sm" name="items[<?= $uuid ?>][activity]" 
                     value="<?= esc($d['activity']) ?>" placeholder="Setup / Ganti Mold / Trial" required>
            </td>

            <td>
              <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row" title="Hapus Dandori">
                <i class="bi bi-trash"></i>
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    
    <div class="card-footer bg-white text-start">
       <button type="button" class="btn btn-sm btn-outline-primary fw-bold btn-add-dandori" data-shift="<?= $shift['id'] ?>">
          <i class="bi bi-plus-circle"></i> Tambah Manual Dandori
       </button>
    </div>
  </div>
<?php endforeach ?>

<div class="mt-4 mb-5 position-sticky bottom-0 bg-white p-3 border-top shadow-sm z-3 d-flex justify-content-end">
  <button class="btn btn-success px-5 rounded-pill fw-bold shadow-sm" type="submit">
      <i class="bi bi-save me-1"></i> Simpan / Update Jadwal Dandori
  </button>
</div>

</form>

<template id="row-template">
    <tr class="dandori-row">
        <td>
          <input type="hidden" class="input-shift" name="items[{uuid}][shift_id]" value="">
          <select class="form-select form-select-sm machine-select fw-bold" name="items[{uuid}][machine_id]" required>
            <option value="">- Pilih Mesin -</option>
            <?php foreach ($machines as $m): ?>
              <option value="<?= $m['id'] ?>"><?= esc($m['machine_code']) ?></option>
            <?php endforeach ?>
          </select>
        </td>
        <td class="text-start">
          <select class="form-select form-select-sm product-select-new" name="items[{uuid}][product_id]" required>
            <option value="">- Cari Part -</option>
            <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>"><?= esc($p['part_no'] . ' - ' . $p['part_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td class="time-slot-container">
           </td>
        <td>
          <input type="text" class="form-control form-control-sm" name="items[{uuid}][activity]" 
                 value="Setup/Dandori Preparation" required>
        </td>
        <td>
          <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">
            <i class="bi bi-trash"></i>
          </button>
        </td>
    </tr>
</template>

<script>
// Peta data Time Slot (Agar template baris baru punya dropdown jam yang sesuai dengan shift)
const shiftSlotsMap = <?= json_encode($shiftSlots) ?>;

function initSelect2() {
    $('.product-select').select2({ width: '100%', placeholder: '- Cari Part -', allowClear: true });
}

$(document).ready(function() {
    initSelect2();

    // Fungsi Hapus Baris
    $(document).on('click', '.btn-remove-row', function() {
        const tbody = $(this).closest('tbody');
        $(this).closest('tr').remove();
        
        // Memunculkan text pesan kosong jika di shift tersebut tidak tersisa baris
        if(tbody.find('tr.dandori-row').length === 0) {
            tbody.find('.empty-row').show();
        }
    });

    // Fungsi Tambah Baris Manual
    $('.btn-add-dandori').on('click', function() {
        const shiftId = $(this).data('shift');
        const tbody = $(`#tbody-shift-${shiftId}`);
        
        // Sembunyikan pesan kosong
        tbody.find('.empty-row').hide();

        const template = document.getElementById('row-template').innerHTML;
        const uuid = 'row_' + Math.random().toString(36).substr(2, 9);
        
        let newRowHtml = template.replace(/{uuid}/g, uuid);
        let $newRow = $(newRowHtml);

        $newRow.find('.input-shift').val(shiftId);

        // Render dropwdown Time Slot berdasarkan Shift yang diklik
        let slots = shiftSlotsMap[shiftId] || [];
        let selectHtml = `<select class="form-select form-select-sm bg-warning-subtle" name="items[${uuid}][time_slot_id]">
                            <option value="">-- Set Waktu --</option>`;
        slots.forEach(s => {
            selectHtml += `<option value="${s.time_slot_id}">${s.label}</option>`;
        });
        selectHtml += `</select>`;
        
        $newRow.find('.time-slot-container').html(selectHtml);

        tbody.append($newRow);
        
        // Aktifkan select2 pencarian produk di baris baru
        $newRow.find('.product-select-new').removeClass('product-select-new').addClass('product-select').select2({
            width: '100%',
            placeholder: '- Cari Part -',
            allowClear: true
        });
    });
});
</script>

<?= $this->endSection() ?>