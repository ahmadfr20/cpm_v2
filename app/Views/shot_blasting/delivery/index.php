<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-2 fw-bold">SAND BLASTING</h4>
<h5 class="mb-4 text-muted">DELIVERY TO VENDOR</h5>

<?php if (!empty($errorMsg)): ?>
  <div class="alert alert-danger"><?= esc($errorMsg) ?></div>
<?php endif; ?>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<form method="post" action="<?= site_url('/shot-blasting/delivery/store') ?>">
<?= csrf_field() ?>

<!-- ================= HEADER ================= -->
<table class="table table-bordered table-sm mb-4" style="max-width:850px">
  <tr>
    <td style="width:120px">Date</td>
    <td style="width:200px"><?= esc($date ?? date('Y-m-d')) ?></td>
    <td style="width:120px">Shift</td>
    <td>
      <select name="shift_id" class="form-select form-select-sm" required>
        <option value="">-- pilih shift --</option>
        <?php foreach (($shifts ?? []) as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= esc($s['shift_name']) ?></option>
        <?php endforeach ?>
      </select>
    </td>
  </tr>

  <tr>
    <td>Time</td>
    <td><?= date('H:i:s') ?></td>
    <td>Vendor</td>
    <td>
      <select name="vendor_id" id="vendorSelect" class="form-select form-select-sm" required>
        <option value="">-- pilih vendor --</option>
        <?php foreach (($vendors ?? []) as $v): ?>
          <?php
            $vid   = (int)($v['id'] ?? 0);
            $vcode = (string)($v['vendor_code'] ?? '');
            $vname = (string)($v['vendor_name'] ?? '');
            $vapp  = (string)($v['vendor_code_app'] ?? '');
            $label = trim($vcode . ($vapp ? " ($vapp)" : "") . " - " . $vname);
          ?>
          <option
            value="<?= $vid ?>"
            data-name="<?= esc($vname) ?>"
            data-code="<?= esc($vcode) ?>"
          >
            <?= esc($label) ?>
          </option>
        <?php endforeach ?>
      </select>
    </td>
  </tr>

  <tr>
    <td>Vendor Name</td>
    <td>
      <input type="text" id="vendorName" class="form-control form-control-sm" readonly>
    </td>
    <td>DO#</td>
    <td>
      <!-- ✅ otomatis dari vendor_code -->
      <input type="text" name="do_number" id="doNumber" class="form-control form-control-sm" readonly required>
    </td>
  </tr>
</table>

<!-- ================= DETAIL TABLE ================= -->
<table class="table table-bordered table-sm align-middle text-center">
  <thead class="table-secondary">
    <tr>
      <th style="width:60px">No</th>
      <th style="width:180px">Part No</th>
      <th>Part Name</th>
      <th style="width:160px">Prev Process</th>
      <th style="width:140px">Available (Prev)</th>
      <th style="width:170px">QTY Delivery</th>
    </tr>
  </thead>
  <tbody>
  <?php
    $availableMap   = $availableMap ?? [];
    $prevProcMap    = $prevProcMap ?? [];
    $processNameMap = $processNameMap ?? [];
  ?>

  <?php if (empty($products)): ?>
    <tr>
      <td colspan="6" class="text-muted">
        Tidak ada product yang bisa dikirim (stock prev process kosong).
      </td>
    </tr>
  <?php else: ?>
    <?php foreach ($products as $i => $p): ?>
      <?php
        $pid = (int)($p['id'] ?? 0);
        $av  = (int)($availableMap[$pid] ?? 0);
        $prevId   = (int)($prevProcMap[$pid] ?? 0);
        $prevName = $prevId > 0 ? ($processNameMap[$prevId] ?? ("Process #".$prevId)) : '-';

        $page = $pager ? (int)$pager->getCurrentPage() : 1;
        $pp   = $pager ? (int)$pager->getPerPage() : 10;
        $no   = ($i + 1) + ($page - 1) * $pp;
      ?>
      <tr>
        <td><?= $no ?></td>
        <td><?= esc($p['part_no'] ?? '') ?></td>
        <td class="text-start"><?= esc($p['part_name'] ?? '') ?></td>
        <td><?= esc($prevName) ?></td>
        <td class="fw-bold"><?= number_format($av) ?></td>
        <td>
          <input
            type="number"
            name="items[<?= $i ?>][qty]"
            class="form-control form-control-sm text-center qty-input"
            min="0"
            max="<?= $av ?>"
            data-available="<?= $av ?>"
            placeholder="0 - <?= $av ?>"
          >
          <input type="hidden" name="items[<?= $i ?>][product_id]" value="<?= $pid ?>">
        </td>
      </tr>
    <?php endforeach ?>
  <?php endif; ?>
  </tbody>
</table>

<?= $pager ? $pager->links('default', 'bootstrap_pagination') : '' ?>

<button class="btn btn-success btn-sm mt-3">
  <i class="bi bi-truck"></i> Simpan Delivery Sand Blasting
</button>

</form>

<script>
function syncVendorFields() {
  const sel = document.getElementById('vendorSelect');
  const opt = sel?.options[sel.selectedIndex];
  const name = opt?.getAttribute('data-name') || '';
  const code = opt?.getAttribute('data-code') || '';

  const nameEl = document.getElementById('vendorName');
  const doEl   = document.getElementById('doNumber');

  if (nameEl) nameEl.value = name;
  if (doEl)   doEl.value   = code; // ✅ DO# = vendor_code
}

document.getElementById('vendorSelect')?.addEventListener('change', syncVendorFields);
document.addEventListener('DOMContentLoaded', syncVendorFields);

// validasi qty <= available
document.querySelectorAll('.qty-input').forEach(inp => {
  inp.addEventListener('input', () => {
    const av = parseInt(inp.dataset.available || '0', 10);
    let v = parseInt(inp.value || '0', 10);
    if (isNaN(v)) v = 0;
    if (v > av) inp.value = av;
    if (v < 0) inp.value = 0;
  });
});
</script>

<?= $this->endSection() ?>
