<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-2 fw-bold">BARITORI</h4>
<h5 class="mb-4 text-muted">DELIVERY TO VENDOR</h5>

<form method="post" action="/baritori/delivery/store">
<?= csrf_field() ?>

<!-- ================= HEADER ================= -->
<table class="table table-bordered table-sm mb-4" style="max-width:850px">
<tr>
    <td style="width:120px">Date</td>
    <td style="width:200px"><?= date('Y-m-d') ?></td>
    <td style="width:120px">Shift</td>
    <td>
        <select name="shift_id" class="form-select form-select-sm" required>
            <option value="">-- pilih shift --</option>
            <?php foreach ($shifts as $s): ?>
                <option value="<?= $s['id'] ?>">
                    <?= esc($s['shift_name']) ?>
                </option>
            <?php endforeach ?>
        </select>
    </td>
</tr>
<tr>
    <td>Time</td>
    <td><?= date('H:i:s') ?></td>
    <td>PO#</td>
    <td>
        <input type="text"
               name="po_number"
               class="form-control form-control-sm"
               required>
    </td>
</tr>
<tr>
    <td>Vendor</td>
    <td>
        <select name="vendor_id"
                id="vendorSelect"
                class="form-select form-select-sm"
                required>
            <option value="">-- pilih vendor --</option>
            <?php foreach ($vendors as $v): ?>
                <option value="<?= $v['id'] ?>">
                    <?= esc($v['supplier_code']) ?> - <?= esc($v['supplier_name']) ?>
                </option>
            <?php endforeach ?>
        </select>
    </td>
    <td>Vendor Name</td>
    <td>
        <input type="text"
               id="vendorName"
               class="form-control form-control-sm"
               readonly>
    </td>
</tr>
<tr>
    <td>DO#</td>
    <td>
        <input type="text"
               name="do_number"
               class="form-control form-control-sm"
               required>
    </td>
    <td></td>
    <td></td>
</tr>
</table>

<!-- ================= DETAIL TABLE ================= -->
<table class="table table-bordered table-sm align-middle text-center">
<thead class="table-secondary">
<tr>
    <th style="width:60px">No</th>
    <th style="width:180px">Part No</th>
    <th>Part Name</th>
    <th style="width:150px">QTY Delivery</th>
</tr>
</thead>
<tbody>
<?php foreach ($products as $i => $p): ?>
<tr>
    <td>
        <?= ($i + 1) + ($pager->getCurrentPage() - 1) * $pager->getPerPage() ?>
    </td>
    <td><?= esc($p['part_no']) ?></td>
    <td class="text-start"><?= esc($p['part_name']) ?></td>
    <td>
        <input type="number"
               name="items[<?= $i ?>][qty]"
               class="form-control form-control-sm text-center"
               min="0">
        <input type="hidden"
               name="items[<?= $i ?>][product_id]"
               value="<?= $p['id'] ?>">
    </td>
</tr>
<?php endforeach ?>
</tbody>
</table>

<!-- ================= PAGINATION ================= -->
<?= $pager->links('default', 'bootstrap_pagination') ?>

<button class="btn btn-success btn-sm mt-3">
    <i class="bi bi-truck"></i> Simpan Delivery Baritori
</button>

</form>

<script>
document.getElementById('vendorSelect').addEventListener('change', function () {
    const text = this.options[this.selectedIndex]?.text || '';
    document.getElementById('vendorName').value =
        text.split(' - ').slice(1).join(' - ');
});
</script>

<?= $this->endSection() ?>
