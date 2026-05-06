<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="mb-2 fw-bold">RAW MATERIAL</h4>
    <h5 class="mb-0 text-muted">SCRAP RECEIVING</h5>
  </div>
  <form method="get" action="<?= site_url('/raw-material/scrap') ?>" class="d-flex align-items-center gap-2">
    <label for="date" class="fw-bold mb-0">Tanggal:</label>
    <input type="date" name="date" id="date" class="form-control form-control-sm" value="<?= esc($date) ?>" onchange="this.form.submit()" style="width: 150px;">
  </form>
</div>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<div class="alert alert-info border-info d-flex align-items-center mb-4">
  <i class="bi bi-info-circle-fill fs-3 me-3 text-info"></i>
  <div>
    <strong>Informasi Perhitungan Sistem (Referensi):</strong><br>
    Nilai scrap yang diturunkan dari <strong>Die Casting</strong> di bawah ini merupakan estimasi otomatis dari target produksi terhadap kaviti dan berat runner. Sedangkan Scrap dari <strong>Machining</strong> (Chips dan NG) merupakan akumulasi selisih berat dan NG dari line Machining sesuai shift masing-masing. Silakan gunakan angka aktual pada kolom timbangan.
  </div>
</div>

<form method="post" action="<?= site_url('/raw-material/scrap/store') ?>">
<?= csrf_field() ?>
<input type="hidden" name="date" value="<?= esc($date) ?>">

<div class="card shadow-sm border-0 mb-4">
  <div class="card-body p-0 table-responsive">
    <table class="table table-bordered table-striped table-hover mb-0 align-middle text-center">
      <thead class="table-secondary">
        <tr>
          <th rowspan="2" class="align-middle" style="width:60px">No</th>
          <th rowspan="2" class="align-middle">Shift</th>
          <th colspan="2" class="text-primary border-bottom border-primary">Info Die Casting Scrap (Kg)</th>
          <th colspan="2" class="text-warning border-bottom border-warning">Info Machining Scrap (Kg)</th>
          <th rowspan="2" class="align-middle text-success" style="width:200px">Aktual Timbangan<br>(Scrap Masuk - Kg)</th>
          <th rowspan="2" class="align-middle" style="width:250px">Notes</th>
        </tr>
        <tr>
          <th class="text-primary" style="font-size:0.85rem">DC Runner</th>
          <th class="text-primary" style="font-size:0.85rem">DC NG</th>
          <th class="text-warning" style="font-size:0.85rem">Chips</th>
          <th class="text-warning" style="font-size:0.85rem">Mac NG</th>
        </tr>
      </thead>
      <tbody>

      <?php if (empty($shifts)): ?>
        <tr>
          <td colspan="8" class="text-muted py-4">Tidak ada data shift aktif.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($shifts as $i => $s): ?>
          <?php
            $sid = (int)$s['id'];
            $r = $receives[$sid] ?? [];
            $actual = $r['actual'] ?? '';
            $notes  = $r['notes'] ?? '';

            $calc = $calcData[$sid] ?? [
                'dc_runner' => 0, 'dc_ng' => 0, 'mac_chips' => 0, 'mac_ng' => 0
            ];
          ?>
          <tr>
            <td class="fw-bold"><?= $i + 1 ?></td>
            <td class="fs-6 fw-bold"><?= esc($s['shift_name'] ?? '-') ?></td>
            
            <td class="text-primary fw-bold" style="background-color:rgba(13,110,253,0.05)"><?= number_format($calc['dc_runner'], 2) ?></td>
            <td class="text-primary fw-bold" style="background-color:rgba(13,110,253,0.05)"><?= number_format($calc['dc_ng'], 2) ?></td>
            
            <td class="text-warning fw-bold text-dark" style="background-color:rgba(255,193,7,0.05)"><?= number_format($calc['mac_chips'], 2) ?></td>
            <td class="text-warning fw-bold text-dark" style="background-color:rgba(255,193,7,0.05)"><?= number_format($calc['mac_ng'], 2) ?></td>
            
            <td style="background-color:rgba(25,135,84,0.05)">
              <!-- Hidden inputs for calculated refs -->
              <input type="hidden" name="items[<?= $sid ?>][dc_runner]" value="<?= $calc['dc_runner'] ?>">
              <input type="hidden" name="items[<?= $sid ?>][dc_ng]" value="<?= $calc['dc_ng'] ?>">
              <input type="hidden" name="items[<?= $sid ?>][mac_chips]" value="<?= $calc['mac_chips'] ?>">
              <input type="hidden" name="items[<?= $sid ?>][mac_ng]" value="<?= $calc['mac_ng'] ?>">
              
              <input
                type="number" step="0.01"
                name="items[<?= $sid ?>][actual]"
                class="form-control form-control-sm text-center fw-bold text-success border-success"
                min="0"
                value="<?= $actual ?>"
                placeholder="Aktual Kg"
              >
            </td>
            <td>
              <textarea name="items[<?= $sid ?>][notes]" class="form-control form-control-sm" rows="1" placeholder="Catatan opsional..."><?= esc($notes) ?></textarea>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>

      </tbody>
    </table>
  </div>
</div>

<div class="mb-5 text-end">
  <button type="submit" class="btn btn-primary">
    <i class="bi bi-save me-2"></i> Simpan Data Penerimaan Scrap
  </button>
</div>

</form>

<h5 class="mt-5 mb-3 fw-bold border-bottom pb-2">History Penerimaan Scrap</h5>
<div class="card shadow-sm border-0 mb-4">
  <div class="card-body p-0">
    <table class="table table-bordered table-striped mb-0 align-middle text-center" style="font-size:0.9rem">
      <thead class="table-light">
        <tr>
          <th>Tgl Receive</th>
          <th>Shift</th>
          <th>Ref. DC Runner</th>
          <th>Ref. DC NG</th>
          <th>Ref. Mac Chips</th>
          <th>Ref. Mac NG</th>
          <th><span class="text-success fw-bold">Actual In (Kg)</span></th>
          <th>Time Input</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($history)): ?>
          <tr>
            <td colspan="8" class="text-muted py-4">Belum ada history penerimaan scrap.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($history as $h): ?>
            <tr>
              <td><?= esc($h['receive_date']) ?></td>
              <td class="fw-bold"><?= esc($h['shift_name'] ?? '-') ?></td>
              <td><?= number_format((float)$h['dc_runner_scrap_kg'], 2) ?></td>
              <td><?= number_format((float)$h['dc_ng_scrap_kg'], 2) ?></td>
              <td><?= number_format((float)$h['machining_chips_kg'], 2) ?></td>
              <td><?= number_format((float)$h['machining_ng_kg'], 2) ?></td>
              <td class="fw-bold text-success fs-6"><?= number_format((float)$h['actual_scrap_received_kg'], 2) ?></td>
              <td class="text-muted small"><?= date('d M H:i', strtotime($h['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const actualInput = row.querySelector('input[name*="[actual]"]');
        if (!actualInput) return;
        
        // Only auto-fill if the input is empty or 0 (never filled before)
        if (!actualInput.value || parseFloat(actualInput.value) === 0) {
            const dcRunner = parseFloat(row.querySelector('input[name*="[dc_runner]"]').value) || 0;
            const dcNg = parseFloat(row.querySelector('input[name*="[dc_ng]"]').value) || 0;
            const macChips = parseFloat(row.querySelector('input[name*="[mac_chips]"]').value) || 0;
            const macNg = parseFloat(row.querySelector('input[name*="[mac_ng]"]').value) || 0;
            
            const total = dcRunner + dcNg + macChips + macNg;
            if (total > 0) {
                actualInput.value = total.toFixed(2);
            }
        }
    });
});
</script>

<?= $this->endSection() ?>
