<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">MACHINING – LEAK TEST DAILY PRODUCTION PER HOUR</h4>

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

<form method="post" action="/machining/leak-test/hourly/store" id="formSaveHourly">
<?= csrf_field() ?>

<?php
// shift terakhir MC (array sudah urut ASC)
$lastShift = end($shifts);
reset($shifts);
?>

<?php foreach ($shifts as $shift): ?>

<h5 class="mt-4 mb-2"><?= esc($shift['shift_name']) ?></h5>

<div class="table-scroll">
<table class="production-table">

<?php
  $slotCount = count($shift['slots']);
?>

<!-- ✅ KUNCI RAPINYA: colgroup -->
<colgroup>
  <!-- sticky cols -->
  <col class="col-line">
  <col class="col-machine">
  <col class="col-part">
  <col class="col-target-shift">

  <!-- per slot: Target, OK, NG, NG Cat -->
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
        data-start="<?= $slot['time_start'] ?>"
        data-end="<?= $slot['time_end'] ?>">
      <?= substr($slot['time_start'],0,5) ?> - <?= substr($slot['time_end'],0,5) ?>
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

    <td class="slot-target"><?= $targetSlot ?></td>

    <td>
      <input type="number"
             class="cell-input slot-input ok"
             data-start="<?= $slot['time_start'] ?>"
             data-end="<?= $slot['time_end'] ?>"
             value="<?= $exist['qty_ok'] ?? 0 ?>"
             name="items[<?= $key ?>][ok]">
    </td>

    <td>
      <input type="number"
             class="cell-input slot-input ng"
             data-start="<?= $slot['time_start'] ?>"
             data-end="<?= $slot['time_end'] ?>"
             value="<?= $exist['qty_ng'] ?? 0 ?>"
             name="items[<?= $key ?>][ng]">
    </td>

    <!-- ✅ NG Category dropdown -->
    <td>
      <select class="cell-select slot-input ngcat"
              data-start="<?= $slot['time_start'] ?>"
              data-end="<?= $slot['time_end'] ?>"
              name="items[<?= $key ?>][ng_category]">
        <option value="">- pilih -</option>
        <?php
          // daftar kategori bisa kamu ubah sesuai kebutuhan
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

    <!-- hidden -->
    <input type="hidden" name="items[<?= $key ?>][date]" value="<?= esc($date) ?>">
    <input type="hidden" name="items[<?= $key ?>][shift_id]" value="<?= $shift['id'] ?>">
    <input type="hidden" name="items[<?= $key ?>][machine_id]" value="<?= $item['machine_id'] ?>">
    <input type="hidden" name="items[<?= $key ?>][product_id]" value="<?= $item['product_id'] ?>">
    <input type="hidden" name="items[<?= $key ?>][time_slot_id]" value="<?= $slot['id'] ?>">

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

<!-- ✅ tombol sejajar -->
<div class="d-flex gap-2 mt-3">
    <button class="btn btn-success">
        <i class="bi bi-save"></i> Simpan Leak Test
    </button>

    <form method="post"
          action="/machining/leak-test/hourly/finish-shift"
          id="formFinishShift"
          onsubmit="return confirm('Finish Shift Leak Test? Ini akan transfer WIP ke proses berikutnya.');">
        <?= csrf_field() ?>
        <input type="hidden" name="date" value="<?= esc($date) ?>">
        <input type="hidden" name="shift_id" value="<?= esc($lastShift['id'] ?? '') ?>">

        <!-- default disabled, akan di-enable otomatis oleh JS -->
        <button class="btn btn-warning" id="btnFinishShift" disabled>
            <i class="bi bi-check2-circle"></i>
            Finish Shift (<?= esc($lastShift['shift_name'] ?? 'Shift 3') ?>)
        </button>

        <div class="small text-muted mt-1" id="finishHint">
            Finish Shift aktif setelah slot terakhir shift berakhir.
        </div>
    </form>
</div>

</form>

<!-- ================= CSS (rapi + sticky benar) ================= -->
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
  table-layout: fixed;             /* ✅ kunci */
  border-collapse: separate;       /* ✅ kunci */
  border-spacing: 0;               /* ✅ kunci */
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

/* ✅ width harus match dengan sticky left offset */
.col-line{ width: 90px; }
.col-machine{ width: 120px; }
.col-part{ width: 320px; }
.col-target-shift{ width: 110px; }

.col-slot-target{ width: 70px; }
.col-slot-ok{ width: 85px; }
.col-slot-ng{ width: 85px; }
.col-slot-ngcat{ width: 180px; }

/* ✅ sticky columns */
.sticky-left{
  position: sticky; left: 0;
  background: #fff;
  z-index: 60;
}
.sticky-left-2{
  position: sticky; left: 90px;
  background: #fff;
  z-index: 60;
}
.sticky-left-3{
  position: sticky; left: 210px; /* 90 + 120 */
  background: #fff;
  z-index: 60;
}
.sticky-left-4{
  position: sticky; left: 530px; /* 90+120+320 */
  background: #fff;
  z-index: 60;
}

/* header sticky columns must be higher */
.production-table thead .sticky-left,
.production-table thead .sticky-left-2,
.production-table thead .sticky-left-3,
.production-table thead .sticky-left-4{
  background:#e5e7eb;
  z-index: 80;
}

/* input/select full width of cell */
.cell-input, .cell-select{
  width: 100%;
  height: 30px;
  padding: 2px 6px;
  font-size: 13px;
  box-sizing: border-box;
}

/* number spinner bikin lebar berubah di beberapa browser -> matikan */
.cell-input[type=number]::-webkit-outer-spin-button,
.cell-input[type=number]::-webkit-inner-spin-button{
  -webkit-appearance: none;
  margin: 0;
}
.cell-input[type=number]{
  -moz-appearance: textfield;
}

.slot-target{
  background:#f9fafb;
  font-weight:700;
  text-align:center;
}

.slot-active{ background:#dcfce7 !important; }
</style>

<!-- ================= JS (enable finish shift + total) ================= -->
<script>
function parseSlotEnd(dateStr, start, end){
  // dateStr: YYYY-MM-DD
  // handle slot melewati midnight
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
    // slot melewati midnight
    if (now >= s) e.setDate(e.getDate() + 1);
    else s.setDate(s.getDate() - 1);
  }
  return now >= s && now <= e;
}

function updateActiveSlots(dateStr){
  document.querySelectorAll('.slot-input').forEach(i=>{
    const a = isSlotActive(dateStr, i.dataset.start, i.dataset.end);
    i.readOnly = !a;
    i.closest('td').classList.toggle('slot-active', a);
  });
}

function calcTotals(){
  document.querySelectorAll('.production-table').forEach(t=>{
    let ok=0,ng=0,target=0;
    t.querySelectorAll('.ok').forEach(i=>ok += (+i.value||0));
    t.querySelectorAll('.ng').forEach(i=>ng += (+i.value||0));
    t.querySelectorAll('.target-shift').forEach(td=>target += (+td.innerText||0));

    const summary = t.closest('.table-scroll').nextElementSibling;
    if(!summary) return;
    summary.querySelector('.total-ok').innerText = ok;
    summary.querySelector('.total-ng').innerText = ng;
    summary.querySelector('.eff').innerText = target ? ((ok/target)*100).toFixed(1)+'%' : '0%';
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
      // setelah 4 sticky kolom, tiap slot = 4 kolom
      for(let i=4;i<c.length;i+=4){
        const idx = (i-4)/4;
        tg[idx] += (+c[i].innerText||0);
        ok[idx] += (+(c[i+1].querySelector('.ok')?.value||0));
        ng[idx] += (+(c[i+2].querySelector('.ng')?.value||0));
      }
    });

    t.querySelectorAll('.total-slot-target').forEach((e,i)=>e.innerText=tg[i]);
    t.querySelectorAll('.total-slot-ok').forEach((e,i)=>e.innerText=ok[i]);
    t.querySelectorAll('.total-slot-ng').forEach((e,i)=>e.innerText=ng[i]);
  });
}

function recalcAll(){ calcTotals(); calcSlotTotals(); }

/**
 * ✅ Enable Finish Shift ketika slot terakhir shift terakhir sudah berakhir
 * Kita ambil slot terakhir dari header shift terakhir (MC terakhir) yang ada di halaman.
 */
function updateFinishShiftButton() {
  const btn = document.getElementById('btnFinishShift');
  const hint = document.getElementById('finishHint');
  if (!btn) return;

  // cari table terakhir (shift terakhir)
  const tables = document.querySelectorAll('.production-table');
  if (!tables.length) return;
  const lastTable = tables[tables.length - 1];

  // ambil header slot terakhir dari shift terakhir
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

const dateStr = "<?= esc($date) ?>";
updateActiveSlots(dateStr);
recalcAll();
updateFinishShiftButton();

setInterval(() => {
  updateActiveSlots(dateStr);
  updateFinishShiftButton();
}, 30000);

document.addEventListener('input', recalcAll);
</script>

<?= $this->endSection() ?>
