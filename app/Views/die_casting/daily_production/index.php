<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">DIE CASTING – DAILY PRODUCTION PER HOUR</h4>

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

<form method="post" action="/die-casting/daily-production/store">
<?= csrf_field() ?>

<?php foreach ($shifts as $shift): ?>

<h5 class="mt-4 mb-2"><?= esc($shift['shift_name']) ?></h5>

<div class="table-scroll">
<table class="production-table table table-bordered table-sm">

<thead>
<tr>
    <th rowspan="2" class="sticky-left col-part">Mesin</th>
    <th rowspan="2" class="sticky-left col-machine">Part</th>
    <th rowspan="2" class="sticky-left-2 col-target-shift">Target<br>Shift</th>

    <?php foreach ($shift['slots'] as $slot): ?>
        <th colspan="4"
            class="slot-header"
            data-start="<?= $slot['time_start'] ?>"
            data-end="<?= $slot['time_end'] ?>">
            <?= substr($slot['time_start'],0,5) ?> - <?= substr($slot['time_end'],0,5) ?>
        </th>
    <?php endforeach ?>
</tr>

<tr>
<?php foreach ($shift['slots'] as $slot): ?>
    <th class="col-slot-target">Target</th>
    <th class="col-slot-fg">FG</th>
    <th class="col-slot-ng">NG</th>
    <th class="col-slot-remark">NG Category</th>
<?php endforeach ?>
</tr>
</thead>

<tbody class="shift-body">
<?php foreach ($shift['items'] as $item): ?>
<tr>

<td class="sticky-left fw-bold text-center">
    <?= esc($item['machine_code']) ?>
</td>

<td class="sticky-left text-start fw-bold">
    <?= esc($item['part_name']) ?>
</td>

<td class="sticky-left-2 fw-bold text-center target-shift">
    <?= esc($item['qty_p']) ?>
</td>

<?php foreach ($shift['slots'] as $slot):

$targetSlot = round(
    ($item['qty_p'] / $shift['total_minute']) * $slot['minute']
);

$exist = $shift['hourly_map']
    [$item['machine_id']]
    [$item['product_id']]
    [$slot['id']] ?? null;
?>

<td class="slot-target fw-bold bg-light text-center">
    <?= $targetSlot ?>
</td>

<td>
<input type="number"
       class="form-control form-control-sm slot-input fg"
       data-start="<?= $slot['time_start'] ?>"
       data-end="<?= $slot['time_end'] ?>"
       value="<?= $exist['qty_fg'] ?? 0 ?>"
       name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][fg]">
</td>

<td>
<input type="number"
       class="form-control form-control-sm slot-input ng"
       data-start="<?= $slot['time_start'] ?>"
       data-end="<?= $slot['time_end'] ?>"
       value="<?= $exist['qty_ng'] ?? 0 ?>"
       name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][ng]">
</td>

<td>
<select class="form-select form-select-sm slot-input"
        data-start="<?= $slot['time_start'] ?>"
        data-end="<?= $slot['time_end'] ?>"
        name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][ng_category_id]">
    <option value="">-- NG --</option>
    <?php foreach ($ngCategories as $ng): ?>
        <option value="<?= $ng['id'] ?>"
            <?= ($exist['ng_category_id'] ?? '') == $ng['id'] ? 'selected' : '' ?>>
            <?= esc($ng['ng_code'].' - '.$ng['ng_name']) ?>
        </option>
    <?php endforeach ?>
</select>
</td>

<input type="hidden" name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][shift_id]" value="<?= $shift['id'] ?>">
<input type="hidden" name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][machine_id]" value="<?= $item['machine_id'] ?>">
<input type="hidden" name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][product_id]" value="<?= $item['product_id'] ?>">
<input type="hidden" name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][time_slot_id]" value="<?= $slot['id'] ?>">
<input type="hidden" name="items[<?= $shift['id'].'_'.$item['machine_id'].'_'.$item['product_id'].'_'.$slot['id'] ?>][date]" value="<?= $date ?>">

<?php endforeach ?>
</tr>
<?php endforeach ?>
</tbody>

<tfoot>
<tr class="total-slot-row fw-bold">
    <td colspan="2" class="text-end">TOTAL / JAM</td>
    <?php foreach ($shift['slots'] as $slot): ?>
        <td class="total-slot-target text-center">0</td>
        <td class="total-slot-fg text-center">0</td>
        <td class="total-slot-ng text-center">0</td>
        <td class="total-slot-eff text-center"> </td>
    <?php endforeach ?>
</tr>
</tfoot>

</table>
</div>

<div class="shift-summary mt-2 mb-4 p-2 border rounded bg-light">
    <strong>SUMMARY <?= esc($shift['shift_name']) ?> :</strong>
    <span class="ms-3">FG: <span class="total-fg">0</span></span>
    <span class="ms-3">NG: <span class="total-ng">0</span></span>
    <span class="ms-3">Efficiency: <span class="eff">0%</span></span>
</div>

<?php endforeach ?>

<button class="btn btn-success mt-3">
    <i class="bi bi-save"></i> Simpan
</button>

</form>

<!-- ================= CSS & JS ================= -->
<style>
.table-scroll{overflow-x:auto}
.production-table{min-width:2600px}
.production-table th,td{font-size:13px;padding:4px;white-space:nowrap;text-align:center}
.col-part{width:260px}.col-target-shift{width:110px}
.sticky-left{position:sticky;left:0;background:#fff;z-index:5}
.sticky-left-2{position:sticky;left:260px;background:#fff;z-index:5}
.slot-active{background:#dcfce7!important}
.slot-header-active{background:#fde68a!important}

.col-machine{width:110px}
.col-part{width:260px}
.col-target-shift{width:110px}

.sticky-left{
    position:sticky;
    left:0;
    background:#fff;
    z-index:6
}
.sticky-left-2{
    position:sticky;
    left:110px;
    background:#fff;
    z-index:6
}
.sticky-left-3{
    position:sticky;
    left:370px;
    background:#fff;
    z-index:6
}

</style>

    <script>
            function isSlotActive(start,end){
            const now=new Date()
            const today=now.toISOString().slice(0,10)
            let s=new Date(`${today}T${start}`)
            let e=new Date(`${today}T${end}`)
            if(e<=s){
            if(now>=s)e.setDate(e.getDate()+1)
            else s.setDate(s.getDate()-1)
            }
            return now>=s&&now<=e
            }

            /* ===============================
            * HIGHLIGHT SLOT AKTIF (TANPA DISABLE)
            * =============================== */
            function updateActiveSlots(){
            document.querySelectorAll('.slot-input').forEach(i=>{
            const a=isSlotActive(i.dataset.start,i.dataset.end)
            i.readOnly = !a
            i.closest('td').classList.toggle('slot-active',a)
            })
            document.querySelectorAll('.slot-header').forEach(h=>{
            h.classList.toggle(
                'slot-header-active',
                isSlotActive(h.dataset.start,h.dataset.end)
            )
            })
            }

            /* ===============================
            * TOTAL PER SHIFT
            * =============================== */
            function calcTotals(){
            document.querySelectorAll('.production-table').forEach(table=>{

            let fg = 0, ng = 0, target = 0

            table.querySelectorAll('.fg').forEach(i=> fg += +i.value || 0)
            table.querySelectorAll('.ng').forEach(i=> ng += +i.value || 0)
            table.querySelectorAll('.target-shift').forEach(td=> target += +td.innerText || 0)

            /* ============================
            * CARI SUMMARY SHIFT TERDEKAT
            * ============================ */
            const summary = table.closest('.table-scroll')
                                .nextElementSibling

            if(!summary || !summary.classList.contains('shift-summary')) return

            summary.querySelector('.total-fg').innerText = fg
            summary.querySelector('.total-ng').innerText = ng
            summary.querySelector('.eff').innerText =
                target ? ((fg/target)*100).toFixed(1)+'%' : '0%'
            })
            }

            /* ===============================
            * TOTAL PER SLOT (INI YANG HILANG)
            * =============================== */
            function calcSlotTotals(){
            document.querySelectorAll('.production-table').forEach(t=>{
            const rows = t.querySelectorAll('tbody tr')
            const slotCount = t.querySelectorAll('.total-slot-target').length

            let tg = Array(slotCount).fill(0)
            let fg = Array(slotCount).fill(0)
            let ng = Array(slotCount).fill(0)

            rows.forEach(r=>{
                const cells = r.querySelectorAll('td')
                for(let i=2;i<cells.length;i+=4){
                const idx = (i-2)/4
                tg[idx] += +cells[i].innerText || 0
                fg[idx] += +(cells[i+1].querySelector('.fg')?.value || 0)
                ng[idx] += +(cells[i+2].querySelector('.ng')?.value || 0)
                }
            })

            t.querySelectorAll('.total-slot-target').forEach((e,i)=>e.innerText=tg[i])
            t.querySelectorAll('.total-slot-fg').forEach((e,i)=>e.innerText=fg[i])
            t.querySelectorAll('.total-slot-ng').forEach((e,i)=>e.innerText=ng[i])
            })
            }

            /* ===============================
            * REKALKULASI GLOBAL
            * =============================== */
            function recalcAll(){
            calcTotals()
            calcSlotTotals()
            }

            updateActiveSlots()
            recalcAll()
            setInterval(updateActiveSlots,30000)
            document.addEventListener('input',recalcAll)

            document.querySelectorAll('.slot-input').forEach(input => {
            input.addEventListener('change', () => {

                const td = input.closest('td')
                const tr = input.closest('tr')

                const data = {
                    date: '<?= $date ?>',
                    shift_id: tr.querySelector('[name$="[shift_id]"]').value,
                    machine_id: tr.querySelector('[name$="[machine_id]"]').value,
                    product_id: tr.querySelector('[name$="[product_id]"]').value,
                    time_slot_id: tr.querySelector('[name$="[time_slot_id]"]').value,
                    fg: tr.querySelector('.fg')?.value || 0,
                    ng: tr.querySelector('.ng')?.value || 0,
                    ng_category_id: tr.querySelector('select')?.value || ''
                }

                fetch('/die-casting/daily-production/save-slot', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '<?= csrf_hash() ?>'
                    },
                    body: new URLSearchParams(data)
                })
                .then(r => r.json())
                .then(res => {
                    if(res.status){
                        td.classList.add('bg-success-subtle')
                        setTimeout(()=>td.classList.remove('bg-success-subtle'),800)
                    }
                })
            })
        })
    </script>


<?= $this->endSection() ?>
