<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">MACHINING – LEAK TEST DAILY PRODUCTION PER HOUR</h4>

<?php
  // ✅ Admin detection (sesuaikan key session jika berbeda)
  $role = session()->get('role')
        ?? session()->get('role_name')
        ?? session()->get('user_role')
        ?? session()->get('level')
        ?? '';
  $isAdmin = in_array(strtolower((string)$role), ['admin','administrator','superadmin','super admin'], true);

  // shift terakhir MC (array sudah urut ASC)
  $lastShift = end($shifts);
  reset($shifts);
?>

<div class="mb-3">
  <strong>Tanggal:</strong> <?= esc($date) ?><br>
  <strong>Operator:</strong> <?= esc($operator) ?>
</div>

<form method="get" class="mb-3">
  <label class="fw-bold me-2">Tanggal Produksi:</label>
  <input type="date"
         name="date"
         value="<?= esc($date) ?>"
         class="form-control d-inline-block"
         style="width:180px"
         onchange="this.form.submit()">
</form>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>


<!-- ===================== FORM SIMPAN HOURLY (TANPA NESTED FORM) ===================== -->
<form method="post" action="/machining/leak-test/hourly/store" id="formSaveHourly">
<?= csrf_field() ?>

<?php foreach ($shifts as $shift): ?>

<h5 class="mt-4 mb-2"><?= esc($shift['shift_name']) ?></h5>

<div class="table-scroll mb-3">
<table class="production-table">

<?php $slotCount = count($shift['slots']); ?>

<colgroup>
  <col class="col-line">
  <col class="col-machine">
  <col class="col-part">
  <col class="col-target-shift">
  <?php for($i=0;$i<$slotCount;$i++): ?>
    <col class="col-slot-target">
    <col class="col-slot-ok">
    <col class="col-slot-ng">
    <col class="col-slot-ngcat">
  <?php endfor; ?>
</colgroup>

<thead>
<tr>
  <th rowspan="2" class="sticky-left">Line</th>
  <th rowspan="2" class="sticky-left-2">Machine</th>
  <th rowspan="2" class="sticky-left-3">Part</th>
  <th rowspan="2" class="sticky-left-4">Target<br>Shift</th>

  <?php foreach ($shift['slots'] as $slot): ?>
    <th colspan="4" class="slot-header"
        data-start="<?= esc($slot['time_start']) ?>"
        data-end="<?= esc($slot['time_end']) ?>">
      <?= esc(substr($slot['time_start'],0,5)) ?> - <?= esc(substr($slot['time_end'],0,5)) ?>
    </th>
  <?php endforeach ?>
</tr>

<tr>
  <?php foreach ($shift['slots'] as $slot): ?>
    <th class="subhead">Target</th>
    <th class="subhead">OK</th>
    <th class="subhead">NG</th>
    <th class="subhead">NG Category</th>
  <?php endforeach ?>
</tr>
</thead>

<tbody class="shift-body">
<?php foreach ($shift['items'] as $item): ?>
<tr>
  <td class="sticky-left fw-bold text-center"><?= esc($item['line_position']) ?></td>
  <td class="sticky-left-2 text-center"><?= esc($item['machine_code']) ?></td>
  <td class="sticky-left-3 text-start"><?= esc($item['part_no'].' - '.$item['part_name']) ?></td>
  <td class="sticky-left-4 fw-bold text-center target-shift"><?= esc($item['target_per_shift']) ?></td>

  <?php foreach ($shift['slots'] as $slot):

    $targetSlot = round(($item['target_per_shift'] / max(1, $shift['total_minute'])) * $slot['minute']);

    $exist = $shift['hourly_map']
        [$item['machine_id']]
        [$item['product_id']]
        [$slot['id']] ?? null;

    $key = $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'];
  ?>

    <td class="slot-target"><?= (int)$targetSlot ?></td>

    <td>
      <input type="number"
             class="cell-input slot-input ok"
             data-start="<?= esc($slot['time_start']) ?>"
             data-end="<?= esc($slot['time_end']) ?>"
             value="<?= (int)($exist['qty_ok'] ?? 0) ?>"
             name="items[<?= esc($key) ?>][ok]">

      <!-- Hidden inputs tetap valid -->
      <input type="hidden" name="items[<?= esc($key) ?>][date]" value="<?= esc($date) ?>">
      <input type="hidden" name="items[<?= esc($key) ?>][shift_id]" value="<?= (int)$shift['id'] ?>">
      <input type="hidden" name="items[<?= esc($key) ?>][machine_id]" value="<?= (int)$item['machine_id'] ?>">
      <input type="hidden" name="items[<?= esc($key) ?>][product_id]" value="<?= (int)$item['product_id'] ?>">
      <input type="hidden" name="items[<?= esc($key) ?>][time_slot_id]" value="<?= (int)$slot['id'] ?>">
    </td>

    <td>
      <input type="number"
             class="cell-input slot-input ng"
             data-start="<?= esc($slot['time_start']) ?>"
             data-end="<?= esc($slot['time_end']) ?>"
             value="<?= (int)($exist['qty_ng'] ?? 0) ?>"
             name="items[<?= esc($key) ?>][ng]">
    </td>

    <td>
      <select class="cell-select slot-input ngcat"
              data-start="<?= esc($slot['time_start']) ?>"
              data-end="<?= esc($slot['time_end']) ?>"
              name="items[<?= esc($key) ?>][ng_category]">
        <option value="">- pilih -</option>
        <?php
          $cats = ['BOCOR', 'CRACK', 'SCRATCH', 'POROS', 'DIMENSI', 'LAINNYA'];
          $selected = (string)($exist['ng_category'] ?? '');
        ?>
        <?php foreach($cats as $c): ?>
          <option value="<?= esc($c) ?>" <?= $selected === $c ? 'selected' : '' ?>>
            <?= esc($c) ?>
          </option>
        <?php endforeach ?>
      </select>
    </td>

  <?php endforeach ?>
</tr>
<?php endforeach ?>
</tbody>

<tfoot>
<tr class="total-slot-row">
  <td colspan="4" class="text-end fw-bold">TOTAL / JAM</td>
  <?php foreach ($shift['slots'] as $slot): ?>
    <td class="total-slot-target fw-bold text-center">0</td>
    <td class="total-slot-ok fw-bold text-center">0</td>
    <td class="total-slot-ng fw-bold text-center">0</td>
    <td class="text-center">-</td>
  <?php endforeach ?>
</tr>
</tfoot>

</table>
</div>

<?php endforeach ?>

<div class="d-flex gap-2 mt-3">
  <button type="submit" class="btn btn-success">
    <i class="bi bi-save"></i> Simpan Leak Test
  </button>
</div>

</form>


<!-- ===================== FORM FINISH SHIFT (FORM TERPISAH, TANPA NESTED) ===================== -->
<form method="post"
      action="/machining/leak-test/hourly/finish-shift"
      id="formFinishShift"
      class="mt-2"
      onsubmit="return confirm('Finish Shift Leak Test? Ini akan transfer WIP ke proses berikutnya.');">
  <?= csrf_field() ?>
  <input type="hidden" name="date" value="<?= esc($date) ?>">
  <input type="hidden" name="shift_id" value="<?= esc($lastShift['id'] ?? '') ?>">

  <!-- ✅ Admin: tombol selalu aktif -->
  <button type="submit" class="btn btn-warning" id="btnFinishShift" <?= $isAdmin ? '' : 'disabled' ?>>
    <i class="bi bi-check2-circle"></i>
    Finish Shift (<?= esc($lastShift['shift_name'] ?? 'Shift 3') ?>)
  </button>

  <div class="small mt-1" id="finishHint">
    <?= $isAdmin
      ? 'ADMIN: Finish Shift bisa dilakukan kapan saja (override waktu).'
      : 'Finish Shift aktif setelah slot terakhir shift berakhir.' ?>
  </div>
</form>


<!-- ================= CSS ================= -->
<style>
.table-scroll{
  overflow-x:auto;
  border:1px solid #e5e7eb;
  border-radius:10px;
  background:#fff;
}

.production-table{
  width: max-content;
  min-width: 1200px;
  table-layout: fixed;
  border-collapse: separate;
  border-spacing: 0;
  font-size: 13px;
}

.production-table th,
.production-table td{
  border: 1px solid #e5e7eb;
  padding: 6px;
  vertical-align: middle;
  white-space: nowrap;
}

.production-table thead th{
  position: sticky;
  top: 0;
  z-index: 50;
  background: #f3f4f6;
}

.production-table thead .subhead{
  background:#f9fafb;
  font-weight: 600;
}

.col-line{ width: 90px; }
.col-machine{ width: 120px; }
.col-part{ width: 320px; }
.col-target-shift{ width: 110px; }

.col-slot-target{ width: 70px; }
.col-slot-ok{ width: 85px; }
.col-slot-ng{ width: 85px; }
.col-slot-ngcat{ width: 180px; }

.sticky-left{ position: sticky; left: 0; background: #fff; z-index: 60; }
.sticky-left-2{ position: sticky; left: 90px; background: #fff; z-index: 60; }
.sticky-left-3{ position: sticky; left: 210px; background: #fff; z-index: 60; }
.sticky-left-4{ position: sticky; left: 530px; background: #fff; z-index: 60; }

.production-table thead .sticky-left,
.production-table thead .sticky-left-2,
.production-table thead .sticky-left-3,
.production-table thead .sticky-left-4{
  background:#e5e7eb;
  z-index: 80;
}

.cell-input, .cell-select{
  width: 100%;
  height: 30px;
  padding: 2px 6px;
  font-size: 13px;
  box-sizing: border-box;
}

.cell-input[type=number]::-webkit-outer-spin-button,
.cell-input[type=number]::-webkit-inner-spin-button{
  -webkit-appearance: none;
  margin: 0;
}
.cell-input[type=number]{ -moz-appearance: textfield; }

.slot-target{
  background:#f9fafb;
  font-weight:700;
  text-align:center;
}
.slot-active{ background:#dcfce7 !important; }
</style>


<!-- ================= JS ================= -->
<script>
function parseSlotEnd(dateStr, start, end){
  const s = new Date(`${dateStr}T${start}`);
  let e = new Date(`${dateStr}T${end}`);
  if (e <= s) e.setDate(e.getDate() + 1);
  return e;
}

function isSlotActive(dateStr, start, end){
  const now = new Date();
  const s = new Date(`${dateStr}T${start}`);
  let e = new Date(`${dateStr}T${end}`);
  if (e <= s) {
    if (now >= s) e.setDate(e.getDate() + 1);
  }
  return now >= s && now <= e;
}

function updateActiveSlots(dateStr){
  document.querySelectorAll('.slot-input').forEach(el=>{
    const active = isSlotActive(dateStr, el.dataset.start, el.dataset.end);

    if (el.tagName === 'INPUT') el.readOnly = !active;
    if (el.tagName === 'SELECT') el.disabled = !active;

    const td = el.closest('td');
    if (td) td.classList.toggle('slot-active', active);
  });
}

function calcSlotTotals(){
  document.querySelectorAll('.production-table').forEach(t=>{
    const rows = t.querySelectorAll('tbody tr');
    const slots = t.querySelectorAll('.total-slot-target').length;

    let tg = Array(slots).fill(0),
        ok = Array(slots).fill(0),
        ng = Array(slots).fill(0);

    rows.forEach(r=>{
      const c = r.querySelectorAll('td');
      for(let i=4;i<c.length;i+=4){
        const idx = (i-4)/4;
        tg[idx] += (+c[i].innerText || 0);
        ok[idx] += (+(c[i+1].querySelector('.ok')?.value || 0));
        ng[idx] += (+(c[i+2].querySelector('.ng')?.value || 0));
      }
    });

    t.querySelectorAll('.total-slot-target').forEach((e,i)=>e.innerText=tg[i]);
    t.querySelectorAll('.total-slot-ok').forEach((e,i)=>e.innerText=ok[i]);
    t.querySelectorAll('.total-slot-ng').forEach((e,i)=>e.innerText=ng[i]);
  });
}

function updateFinishShiftButton() {
  const btn = document.getElementById('btnFinishShift');
  const hint = document.getElementById('finishHint');
  if (!btn) return;

  const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
  if (isAdmin) {
    btn.disabled = false;
    if (hint) {
      hint.innerText = 'ADMIN: Finish Shift bisa dilakukan kapan saja (override waktu).';
      hint.classList.remove('text-muted');
      hint.classList.add('text-success');
    }
    return;
  }

  const tables = document.querySelectorAll('.production-table');
  if (!tables.length) return;
  const lastTable = tables[tables.length - 1];

  const headers = lastTable.querySelectorAll('thead .slot-header');
  if (!headers.length) return;

  const lastHeader = headers[headers.length - 1];
  const start = lastHeader.dataset.start;
  const end = lastHeader.dataset.end;

  const dateStr = "<?= esc($date) ?>";
  const endTime = parseSlotEnd(dateStr, start, end);
  const now = new Date();

  const canFinish = now >= endTime;
  btn.disabled = !canFinish;

  if (hint) {
    if (canFinish) {
      hint.innerText = 'Finish Shift sudah aktif (slot terakhir sudah berakhir).';
      hint.classList.remove('text-muted');
      hint.classList.add('text-success');
    } else {
      hint.innerText = `Finish Shift aktif setelah slot terakhir berakhir (${end.slice(0,5)}).`;
      hint.classList.remove('text-success');
      hint.classList.add('text-muted');
    }
  }
}

const dateStr = "<?= esc($date) ?>";
updateActiveSlots(dateStr);
calcSlotTotals();
updateFinishShiftButton();

setInterval(() => {
  updateActiveSlots(dateStr);
  updateFinishShiftButton();
}, 30000);

document.addEventListener('input', calcSlotTotals);
</script>

<?= $this->endSection() ?>
