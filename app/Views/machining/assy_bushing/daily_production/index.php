<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4 class="mb-3">MACHINING – ASSY BUSHING DAILY PRODUCTION PER HOUR</h4>
<div class="d-flex justify-content-end mb-3 gap-2 d-print-none">
    <button type="button" class="btn btn-outline-success btn-sm fw-bold" onclick="exportGenericExcel()">
        <i class="bi bi-file-earmark-excel"></i> Export Excel
    </button>
    <button type="button" class="btn btn-outline-danger btn-sm fw-bold" onclick="window.print()">
        <i class="bi bi-printer"></i> Print / PDF
    </button>
</div>

<div class="d-flex flex-wrap align-items-end gap-4 mb-4">
  <div>
    <form method="get" class="mb-0">
      <label class="fw-bold me-2">Tanggal Produksi:</label>
      <input type="date"
             name="date"
             value="<?= esc($date) ?>"
             class="form-control d-inline-block"
             style="width: 180px"
             onchange="this.form.submit()">
    </form>
  </div>
  <div>
    <!-- Global Operator session is removed per requirement -->
  </div>

  <?php if ($isAdmin): ?>
  <div class="form-check form-switch mb-1">
    <input class="form-check-input border-danger" type="checkbox" id="adminOverrideToggle" role="switch" style="cursor: pointer; transform: scale(1.2);">
    <label class="form-check-label fw-bold text-danger ms-2" for="adminOverrideToggle" style="cursor: pointer;">
      <i class="bi bi-unlock-fill"></i> Unlock Semua Slot Waktu (Admin)
    </label>
  </div>
  <?php endif; ?>
</div>

<?php if (session()->getFlashdata('success')): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <strong><i class="bi bi-check-circle"></i> Berhasil!</strong> <?= esc(session()->getFlashdata('success')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong><i class="bi bi-exclamation-triangle"></i> Gagal!</strong> <?= esc(session()->getFlashdata('error')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif ?>

<style>
.table-scroll{
  overflow:auto;
  position:relative;
  max-width:100%;
  border:1px solid #e5e7eb;
  border-radius:12px;
  background:#fff;
  padding:10px;
}
.production-table{
  width:max-content;
  border-collapse:separate !important;
  border-spacing:0 !important;
  table-layout:fixed;
}
.production-table th,
.production-table td{
  font-size:13px;
  padding:8px 8px;
  white-space:nowrap;
  text-align:center;
  vertical-align:middle;
  box-sizing:border-box;
  line-height:1.2;
  background:#fff;
  transition: background 0.3s;
}
.production-table th,
.production-table td{
  border-right:1px solid #e5e7eb;
  border-bottom:1px solid #e5e7eb;
}
.production-table tr > *:first-child{ border-left:1px solid #e5e7eb; }
.production-table thead tr:first-child > *{ border-top:1px solid #e5e7eb; }

.production-table thead tr.thead-row-1 th{
  position:sticky; top:0; z-index:30;
  background:#f8fafc; font-weight:900; height:44px;
}
.production-table thead tr.thead-row-2 th{
  position:sticky; top:44px; z-index:29;
  background:#f1f5f9; font-weight:900; height:44px; font-size:12px;
}

.col-line{ width:60px; min-width:60px; max-width:60px; }
.col-machine{ width:120px; min-width:120px; max-width:120px; }
.col-part{ width:300px; min-width:300px; max-width:300px; }
.col-target-shift{ width:120px; min-width:120px; max-width:120px; }

.col-slot-target{ width:80px; min-width:80px; }
.col-slot-fg{ width:90px; min-width:90px; }
.col-slot-ng{ width:90px; min-width:90px; }
.col-slot-remark{ width:350px; min-width:350px; } 
.col-slot-downtime{ width:180px; min-width:180px; } 

.sticky-left{ position:sticky; left:0; z-index:40; background:#fff; }
.sticky-left-2{ position:sticky; left:60px; z-index:40; background:#fff; }
.sticky-left-3{ position:sticky; left:180px; z-index:40; background:#fff; }
.sticky-left-4{ position:sticky; left:480px; z-index:40; background:#fff; }

.th-sticky-left{ z-index:60 !important; background:#f8fafc !important; }

.slot-active{ background:#dcfce7 !important; }
.slot-header-active{ background:#fde68a !important; }
.slot-rest { background-color: #cbd5e1 !important; opacity: 0.7; }
.slot-locked input, .slot-locked select { background-color: #f1f5f9; }
.slot-dandori-running { background-color: #fff3cd !important; opacity: 0.85; cursor: not-allowed; }
.slot-dandori-running input, .slot-dandori-running select { pointer-events: none; opacity: 0.6; }
.slot-after-dandori td, td.slot-after-dandori { background: #f0fdf4 !important; }

.production-table input.form-control, .production-table select.form-select{ min-width:80px; padding:6px 8px; }

/* ===== NG INLINE TABLE ===== */
.ng-inline{ display:flex; flex-direction:column; gap:8px; }
.ng-inline-head{ display:flex; justify-content:space-between; align-items:center; gap:8px; }
.ng-inline-head .meta{ font-size:12px; color:#64748b; font-weight:700; }
.ng-mini-table{ width:100%; border-collapse:separate; border-spacing:0; }
.ng-mini-table th, .ng-mini-table td{
  border:1px solid #e5e7eb; padding:6px 6px; font-size:12px; text-align:left; background:#fff;
}
.ng-mini-table th{ background:#f8fafc; font-weight:900; text-align:center; }
.ng-mini-table td.ng-no{ width:60px; text-align:center; font-weight:900; }
.ng-mini-table td.ng-qty{ width:110px; }
.ng-mini-table td.ng-act{ width:70px; text-align:center; }
.ng-empty{
  font-size:12px; color:#64748b; font-weight:700;
  text-align:center; padding:8px 0; border:1px dashed #cbd5e1; border-radius:8px;
}

.rest-toggle { transform: scale(0.9); margin-top: 0.25rem !important; }
</style>

<form method="post" action="/machining/assy-bushing/hourly/store" id="hourlyForm">
  <?= csrf_field() ?>
  <input type="hidden" name="global_date" value="<?= esc($date) ?>">

  <?php foreach ($shifts as $shift): ?>
    <?php 
      if (empty($shift['slots'])) continue; 
      $shiftId = (int)$shift['id']; 
    ?>

    <div class="d-flex flex-column gap-1 mt-4 mb-2">
      <div class="d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <h5 class="m-0 text-primary border-start border-4 border-primary ps-2"><?= esc($shift['shift_name']) ?></h5>
            <div class="d-flex align-items-center gap-2">
            </div>
            
            <div class="d-flex align-items-center gap-2 border-start ps-3 border-2 border-primary">
                <label class="fw-bold m-0" style="font-size: 0.9rem;">Leader:</label>
                <?php 
                    $selectedLeader = '';
                    // get last leader from hourly map for this shift
                    foreach ($shift['hourly_map'] ?? [] as $mMap) {
                        foreach ($mMap as $pMap) {
                            foreach ($pMap as $slotMap) {
                                if (!empty($slotMap['leader_name'])) {
                                    $selectedLeader = $slotMap['leader_name'];
                                    break 3;
                                }
                            }
                        }
                    }
                ?>
                <select name="leaders[<?= $shift['id'] ?>]" class="form-select form-select-sm" style="width: 200px;">
                    <option value="">-- Pilih Leader --</option>
                    <?php foreach ($operators as $op): ?>
                        <option value="<?= esc($op['operator_name'], 'attr') ?>" <?= ($selectedLeader == $op['operator_name']) ? 'selected' : '' ?>>
                            <?= esc($op['operator_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
      </div>
      <small class="text-muted ms-3">
        <i class="bi bi-clock-history"></i> Total Waktu Produksi (Slot Aktif): 
        <strong class="shift-active-minutes-display" data-shift-id="<?= $shift['id'] ?>" data-original-minutes="<?= $shift['total_minute'] ?>">
          <?= $shift['total_minute'] ?> Menit
        </strong>
      </small>
    </div>

    <div class="table-scroll">
      <table class="production-table table table-sm align-middle">
        <thead>
          <tr class="thead-row-1">
            <th rowspan="2" class="sticky-left col-line th-sticky-left">Line</th>
            <th rowspan="2" class="sticky-left-2 col-machine th-sticky-left">Machine</th>
            <th rowspan="2" class="sticky-left-3 col-part th-sticky-left">Part</th>
            <th rowspan="2" class="sticky-left-4 col-target-shift th-sticky-left">Target<br>Shift</th>

            <?php foreach ($shift['slots'] as $slot):
              $isBreakSlot = (int)($slot['is_break'] ?? 0);
              $slotHasDandori = false;
              foreach (($shift['dandori_map'] ?? []) as $mId => $slotArr) {
                  if (isset($slotArr[(int)$slot['id']])) { $slotHasDandori = true; break; }
              }
              $headerClass = $slotHasDandori ? 'bg-warning text-dark' : ($isBreakSlot ? 'slot-rest' : '');
            ?>
              <th colspan="7"
                  class="slot-header <?= $headerClass ?>"
                  data-start="<?= esc($slot['time_start']) ?>"
                  data-end="<?= esc($slot['time_end']) ?>"
                  data-shift-id="<?= $shift['id'] ?>"
                  data-slot-id="<?= $slot['id'] ?>"
                  data-is-break="<?= $isBreakSlot ?>">
                <div class="mb-1"><?= esc(substr((string)$slot['time_start'],0,5)) ?> - <?= esc(substr((string)$slot['time_end'],0,5)) ?></div>
                <?php if ($isBreakSlot): ?>
                  <span class="badge bg-secondary" style="font-size:0.6rem;"><i class="bi bi-cup-hot-fill"></i> ISTIRAHAT</span>
                <?php elseif ($slotHasDandori): ?>
                  <div class="mb-1"><span class="badge bg-warning text-dark border border-dark" style="font-size:0.6rem;"><i class="bi bi-tools"></i> DANDORI</span></div>
                <?php endif; ?>
              </th>
            <?php endforeach ?>
          </tr>

          <tr class="thead-row-2">
            <?php foreach ($shift['slots'] as $slot): ?>
              <th class="col-slot-target">Target</th>
              <th class="col-slot-fg">FG</th>
              <th class="col-slot-ng">NG</th>
              <th class="col-slot-remark">NG Category</th>
              <th class="col-slot-downtime">Downtime</th>
              <th class="col-slot-downtime">NG Blank Material</th>
              <th style="width:160px; min-width:160px;">Catatan</th>
            <?php endforeach ?>
          </tr>
        </thead>

        <tbody class="shift-body" data-shift-id="<?= $shift['id'] ?>">
        <?php foreach ($shift['items'] as $item): 
          $weightMc = (float)($shift['weight_mc_map'][(int)$item['product_id']] ?? 0);
          
          $machineActiveSlots = isset($item['active_slot_ids']) && $item['active_slot_ids'] !== '' ? array_map('intval', explode(',', $item['active_slot_ids'])) : null;
          $rawCustomTimes = $item['slot_custom_times'] ?? null;
          $slotCustomMinMap = [];
          if ($rawCustomTimes) {
              foreach (explode(',', $rawCustomTimes) as $entry) {
                  $parts = explode(':', $entry, 2);
                  if (count($parts) === 2) $slotCustomMinMap[(int)$parts[0]] = (int)$parts[1];
              }
          }
          $rowEndSlotId = (int)($item['end_time_slot_id'] ?? 0);
          $rowTotalMinute = 0;

          if ($machineActiveSlots !== null) {
              foreach ($shift['slots'] as $s) {
                  if (in_array((int)$s['id'], $machineActiveSlots)) {
                      if (isset($slotCustomMinMap[(int)$s['id']])) {
                          $rowTotalMinute += $slotCustomMinMap[(int)$s['id']];
                      } else {
                          $rowTotalMinute += (int)$s['minute'];
                      }
                  }
              }
          } else {
              $tempPassed = false;
              foreach ($shift['slots'] as $s) {
                  if (!$tempPassed) {
                      $rowTotalMinute += (int)$s['minute'];
                      if ($rowEndSlotId > 0 && (int)$s['id'] === $rowEndSlotId) {
                          $tempPassed = true;
                      }
                  }
              }
          }
          if ($rowTotalMinute <= 0) $rowTotalMinute = $shift['total_minute'];
        ?>
          <tr class="item-row" data-row-total-minutes="<?= $rowTotalMinute ?>" data-original-target="<?= (int)$item['target_per_shift'] ?>" data-product-id="<?= (int)$item['product_id'] ?>" data-machine-id="<?= (int)$item['machine_id'] ?>" data-machine-code="<?= esc($item['machine_code'] ?? 'Mesin') ?>" data-part-name="<?= esc($item['part_name'] ?? '') ?>" data-weight-mc="<?= number_format($weightMc, 3, '.', '') ?>">
            <td class="sticky-left fw-bold text-center"><?= esc($item['line_position'] ?? '') ?></td>
            <td class="sticky-left-2 fw-bold text-center">
                <div class="mb-1"><?= esc($item['machine_code']) ?></div>
                <div class="text-secondary mb-1" style="font-size:0.65rem;">Total: <?= $rowTotalMinute ?>m</div>
                <?php
                    $machineOpMap = [];
                    foreach ($shift['hourly_map'][$item['machine_id']] ?? [] as $pMap) {
                        foreach ($pMap as $slotId => $slotData) {
                            if (!empty($slotData['operator_name'])) {
                                $machineOpMap[$slotId] = $slotData['operator_name'];
                            }
                        }
                    }
                    $uniqueOps = array_unique(array_values($machineOpMap));
                ?>
                <!-- Hidden inputs operator per slot -->
                <?php foreach ($machineOpMap as $opSlotId => $opName): ?>
                <input type="hidden"
                       class="op-slot-input-<?= $shift['id'] ?>-<?= $item['machine_id'] ?>"
                       name="operators[<?= $shift['id'] ?>][<?= $item['machine_id'] ?>][<?= $opSlotId ?>]"
                       value="<?= esc($opName, 'attr') ?>"
                       data-slot-id="<?= $opSlotId ?>">
                <?php endforeach; ?>
                <button type="button"
                        class="btn btn-outline-info btn-sm btn-atur-operator"
                        style="font-size:0.7rem; white-space:nowrap;"
                        data-shift-id="<?= $shift['id'] ?>"
                        data-machine-id="<?= $item['machine_id'] ?>"
                        data-machine-code="<?= esc($item['machine_code'], 'attr') ?>"
                        onclick="openOperatorModal(<?= $shift['id'] ?>, <?= $item['machine_id'] ?>, '<?= esc($item['machine_code']) ?>')"
                >
                  <i class="bi bi-person-fill-gear"></i>
                  <?= !empty($uniqueOps) ? implode(', ', $uniqueOps) : 'Atur Operator' ?>
                </button>
            </td>
            <td class="sticky-left-3 text-start fw-bold">
                <?= esc(($item['part_prod'] ?? '')) ?>
                <?php if (!empty($item['part_prod']) && !empty($item['part_name'])): ?>&nbsp;-&nbsp;<?php endif; ?>
                <?= esc(($item['part_name'] ?? '')) ?>
            </td>
            <td class="sticky-left-4 fw-bold text-center text-primary fs-6">
                <span class="target-shift-display"><?= (int)$item['target_per_shift'] ?></span>
            </td>

            <?php 
              $hasPassedEndSlot = false;
              foreach ($shift['slots'] as $slot):
              $slotId    = (int)$slot['id'];
              $machineId = (int)$item['machine_id'];
              $productId = (int)$item['product_id'];

              // Cek apakah slot ini NON-AKTIF (hanya jika mode spesifik diaktifkan)
              $slotIsInactive = ($machineActiveSlots !== null) && !in_array($slotId, $machineActiveSlots);
              
              $exist    = $shift['hourly_map'][$machineId][$productId][$slotId] ?? null;
              $ngDetail = $shift['ng_detail_map'][$machineId][$productId][$slotId] ?? [];
              $key      = $date.'_'.$shift['id'].'_'.$machineId.'_'.$productId.'_'.$slotId;
              $inputNamePrefix = "items[{$key}]";

              $dandoriOnThisSlot = $shift['dandori_map'][$machineId][$slotId] ?? null;
              $isAfterDandoriSlot = false;
              foreach (($shift['dandori_map'][$machineId] ?? []) as $dSlotId => $dInfo) {
                  if ($slotId > $dSlotId && $dInfo['product_id'] === $productId) {
                      $isAfterDandoriSlot = true;
                  }
              }
              
              $totalMachineDandori = 0;
              foreach (($shift['dandori_map'][$machineId] ?? []) as $dSlotId => $dInfo) {
                  $totalMachineDandori += (int)($dInfo['dandori_minute'] ?? 0);
              }
              
              $slotDandoriMinute = $dandoriOnThisSlot['dandori_minute'] ?? 0;
              
              $slotClass = $isAfterDandoriSlot ? 'bg-success bg-opacity-10' : ''; 

              // Slot non-aktif atau pasca-end
              if ($slotIsInactive) {
                  $targetSlot = 0;
                  $slotClass .= ' bg-secondary bg-opacity-10 opacity-50';
              } elseif ($hasPassedEndSlot) {
                  $targetSlot = 0;
                  $slotClass .= ' bg-secondary bg-opacity-10 opacity-75';
              } else {
                  $effectiveSlotMinute = isset($slotCustomMinMap[$slotId]) ? $slotCustomMinMap[$slotId] : (int)$slot['minute'];
                  $targetSlot = $rowTotalMinute > 0
                    ? (int) round(((int)$item['target_per_shift'] / (float)$rowTotalMinute) * (float)$effectiveSlotMinute)
                    : 0;
              }
            ?>
            
            <?php if ($slotIsInactive): ?>
               <td class="slot-target-cell fw-bold text-center <?= $slotClass ?>" title="Slot tidak aktif (per mesin)">-</td>
               <td class="<?= $slotClass ?>"></td>
               <td class="<?= $slotClass ?>"></td>
               <td class="<?= $slotClass ?>"></td>
               <td class="<?= $slotClass ?>"></td>
               <td class="<?= $slotClass ?>"></td>
               <td class="<?= $slotClass ?>"></td>
            <?php elseif ($hasPassedEndSlot): ?>
               <td class="slot-target-cell fw-bold text-center <?= $slotClass ?>">-</td>
               <td class="<?= $slotClass ?>"></td>
               <td class="<?= $slotClass ?>"></td>
               <td class="<?= $slotClass ?>"></td>
               <td class="<?= $slotClass ?>"></td>
               <td class="<?= $slotClass ?>"></td>
               <td class="<?= $slotClass ?>"></td>
            <?php else: ?>
            <?php
               $displayTarget = isset($exist['qty_target']) ? (int)$exist['qty_target'] : (int)$targetSlot;
               $isManual = isset($exist['qty_target']) ? 1 : 0;
            ?>
            <?php if ($dandoriOnThisSlot !== null): ?>
               <td class="slot-target-cell fw-bold bg-warning bg-opacity-25 text-center <?= $slotClass ?>" 
                   data-slot-id="<?= $slot['id'] ?>" 
                   data-dandori-minutes="<?= $slotDandoriMinute ?>"
                   data-slot-minutes="<?= (int)$effectiveSlotMinute ?>"
                   style="border-left: 2px solid #ffc107;">
                  <div class="badge bg-warning text-dark mb-1 d-block w-100" style="font-size:0.65rem;">
                      Dandori <?= $slotDandoriMinute ?>m
                  </div>
                  <input type="number" class="form-control form-control-sm slot-input val-target text-center fw-bold text-primary p-0 bg-transparent border-0"
                         name="<?= $inputNamePrefix ?>[qty_target]" data-manual="<?= $isManual ?>"
                         value="<?= $displayTarget ?>" min="0">
                  <button type="button" class="btn btn-sm btn-danger py-0 px-1 mt-1 btn-akhiri-dandori d-none" style="font-size: 0.6rem; width: 100%;"
                          onclick="akhiriDandori('<?= esc($date) ?>', <?= $shift['id'] ?>, <?= $machineId ?>, <?= $slot['id'] ?>)">
                      Akhiri
                  </button>
               </td>
            <?php else: ?>
               <td class="slot-target-cell fw-bold bg-light text-center <?= $slotClass ?>" 
                   data-slot-id="<?= $slot['id'] ?>"
                   data-slot-minutes="<?= (int)$effectiveSlotMinute ?>"
                   data-dandori-minutes="0">
                  <input type="number" class="form-control form-control-sm slot-input val-target text-center fw-bold text-primary p-0 bg-transparent border-0"
                         name="<?= $inputNamePrefix ?>[qty_target]" data-manual="<?= $isManual ?>"
                         value="<?= $displayTarget ?>" min="0">
               </td>
            <?php endif; ?>

              <td class="<?= $slotClass ?>">
                <input type="hidden" name="<?= $inputNamePrefix ?>[date]"              value="<?= esc($date) ?>">
                <input type="hidden" name="<?= $inputNamePrefix ?>[shift_id]"          value="<?= esc($shift['id']) ?>">
                <input type="hidden" name="<?= $inputNamePrefix ?>[time_slot_id]"      value="<?= esc($slot['id']) ?>">
                <input type="hidden" name="<?= $inputNamePrefix ?>[machine_id]"        value="<?= esc($machineId) ?>">
                <input type="hidden" name="<?= $inputNamePrefix ?>[product_id]"        value="<?= esc($productId) ?>">
                <input type="hidden" name="<?= $inputNamePrefix ?>[downtime]"          value="0">
                <input type="number"
                       class="form-control form-control-sm slot-input fg val-fg"
                       data-date="<?= esc($date) ?>"
                       data-start="<?= esc($slot['time_start']) ?>"
                       data-end="<?= esc($slot['time_end']) ?>"
                       data-shift-id="<?= $shift['id'] ?>"
                       data-slot-id="<?= $slot['id'] ?>"
                       data-dandori-minutes="<?= $dandoriOnThisSlot !== null ? $slotDandoriMinute : 0 ?>"
                       value="<?= (int)($exist['qty_fg'] ?? 0) ?>"
                       name="<?= $inputNamePrefix ?>[fg]">
              </td>

              <td class="<?= $slotClass ?>">
                <input type="number"
                       class="form-control form-control-sm slot-input ng val-ng"
                       readonly
                       id="ngTotalInput_<?= esc($key) ?>"
                       value="<?= (int)($exist['qty_ng'] ?? 0) ?>"
                       name="<?= $inputNamePrefix ?>[ng]">
              </td>

              <td class="text-start outer-td <?= $slotClass ?>">
                <div class="ng-inline" data-key="<?= esc($key) ?>">
                  <div class="ng-inline-head">
                    <div class="meta">
                      Total NG: <span class="fw-bold text-danger" id="ngTotalBadge_<?= esc($key) ?>">0</span>
                    </div>
                    <button type="button"
                            class="btn btn-sm btn-outline-danger fw-bold ng-add-btn"
                            onclick="addNgRowInline('<?= esc($key) ?>', <?= $shift['id'] ?>)"
                            data-start="<?= esc($slot['time_start']) ?>"
                            data-end="<?= esc($slot['time_end']) ?>"
                            data-shift-id="<?= $shift['id'] ?>"
                            data-slot-id="<?= $slot['id'] ?>"
                            data-dandori-minutes="<?= $dandoriOnThisSlot !== null ? $slotDandoriMinute : 0 ?>">
                      + NG
                    </button>
                  </div>

                  <div class="table-responsive">
                    <table class="ng-mini-table">
                      <thead>
                        <tr>
                          <th style="width:60px">NG</th>
                          <th>Category</th>
                          <th style="width:110px">Qty</th>
                          <th style="width:70px"></th>
                        </tr>
                      </thead>
                      <tbody id="ngBody_<?= esc($key) ?>"></tbody>
                    </table>
                  </div>
                </div>

                <div class="ng-hidden d-none" id="ngHidden_<?= esc($key) ?>">
                  <?php foreach ($ngDetail as $idx => $d): ?>
                    <input type="hidden" name="<?= $inputNamePrefix ?>[ng_details][<?= $idx ?>][ng_category_id]" value="<?= (int)$d['ng_category_id'] ?>">
                    <input type="hidden" name="<?= $inputNamePrefix ?>[ng_details][<?= $idx ?>][qty]" value="<?= (int)$d['qty'] ?>">
                  <?php endforeach; ?>
                </div>
                
                <input type="hidden" name="<?= $inputNamePrefix ?>[shift_id]" value="<?= (int)$shiftId ?>">
                <input type="hidden" name="<?= $inputNamePrefix ?>[machine_id]" value="<?= (int)$item['machine_id'] ?>">
                <input type="hidden" name="<?= $inputNamePrefix ?>[product_id]" value="<?= (int)$item['product_id'] ?>">
                <input type="hidden" name="<?= $inputNamePrefix ?>[time_slot_id]" value="<?= (int)$slot['id'] ?>">
                <input type="hidden" name="<?= $inputNamePrefix ?>[date]" value="<?= esc($date) ?>">
              </td>

              <td class="text-start <?= $slotClass ?>">
                <?php 
                  $dtDetail = $shift['dt_detail_map'][$machineId][$productId][$slotId] ?? [];
                  $hasDandori = ($dandoriOnThisSlot !== null);
                  if ($hasDandori) {
                      // Check if Dandori is already in dtDetail
                      $found = false;
                      foreach ($dtDetail as $dt) {
                          if ($dt['downtime_category_id'] == -1 || $dt['downtime_category_id'] == 0) { $found = true; break; }
                      }
                      if (!$found) {
                          array_unshift($dtDetail, [
                              'downtime_category_id' => -1,
                              'downtime_minute' => $slotDandoriMinute,
                              'downtime_name' => 'Dandori'
                          ]);
                      }
                  }
                ?>
                <div class="dt-inline" data-key="<?= esc($key) ?>" data-shift-id="<?= $shift['id'] ?>">
                  <div class="dt-inline-head">
                    <div class="meta">
                      Penalty: <span class="fw-bold text-danger" id="dtPenaltyBadge_<?= esc($key) ?>">0%</span>
                    </div>
                    <button type="button"
                            class="btn btn-sm btn-outline-warning fw-bold dt-add-btn"
                            onclick="addDtRowInline('<?= esc($key) ?>', <?= $shift['id'] ?>)"
                            data-start="<?= esc($slot['time_start']) ?>"
                            data-end="<?= esc($slot['time_end']) ?>"
                            data-shift-id="<?= $shift['id'] ?>"
                            data-slot-id="<?= $slot['id'] ?>">
                      + DT
                    </button>
                  </div>
                  <div class="table-responsive">
                    <table class="ng-mini-table">
                      <thead>
                        <tr>
                          <th>Kategori</th>
                          <th style="width:70px">Menit</th>
                          <th style="width:50px"></th>
                        </tr>
                      </thead>
                      <tbody id="dtBody_<?= esc($key) ?>"></tbody>
                    </table>
                  </div>
                </div>
                <div class="dt-hidden d-none" id="dtHidden_<?= esc($key) ?>">
                  <?php foreach ($dtDetail as $idx => $d): ?>
                    <input type="hidden" name="<?= $inputNamePrefix ?>[dt_details][<?= $idx ?>][downtime_category_id]" value="<?= (int)$d['downtime_category_id'] ?>">
                    <input type="hidden" name="<?= $inputNamePrefix ?>[dt_details][<?= $idx ?>][downtime_minute]" value="<?= (int)$d['downtime_minute'] ?>">
                  <?php endforeach; ?>
                </div>
                <input type="hidden" name="<?= $inputNamePrefix ?>[downtime_penalty]" id="dtPenaltyInput_<?= esc($key) ?>" value="<?= (int)($exist['downtime_minute'] ?? $exist['downtime'] ?? 0) ?>" class="dt-penalty-val">
              </td>

              <td class="<?= $slotClass ?>">
                <input type="number"
                       class="form-control form-control-sm slot-input ng-blank val-ng-blank"
                       value="<?= (int)($exist['qty_ng_blank'] ?? 0) ?>"
                       name="<?= $inputNamePrefix ?>[ng_blank]"
                       min="0">
              </td>

              <td class="<?= $slotClass ?>">
                <input type="text"
                       class="form-control form-control-sm"
                       value="<?= esc($exist['remark'] ?? '') ?>"
                       name="<?= $inputNamePrefix ?>[remark]"
                       placeholder="Catatan..."
                       style="font-size: 11px;">
              </td>
            <?php endif; ?>
            <?php 
              // Set pass flag for next iterations 
              if ($rowEndSlotId > 0 && $slotId === $rowEndSlotId) {
                  $hasPassedEndSlot = true;
              }
            endforeach; ?>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>

    <div class="mt-2 mb-5 p-3 border rounded bg-light summary-box" data-shift-id="<?= $shift['id'] ?>">
      <strong>SUMMARY <?= esc($shift['shift_name']) ?> :</strong>
      <span class="ms-4">TOTAL TARGET: <span class="total-target fw-bold text-dark fs-5">0</span></span>
      <span class="ms-4">TOTAL FG: <span class="total-fg fw-bold text-success fs-5">0</span></span>
      <span class="ms-4">TOTAL NG: <span class="total-ng fw-bold text-danger fs-5">0</span></span>

      <!-- Per-Machine Efficiency Container -->
      <div class="machine-summary-container mt-3 d-flex flex-wrap gap-2"></div>
      <div class="mt-2 pt-2 border-top d-flex flex-wrap gap-3 align-items-center">
        <span class="text-muted small"><i class="bi bi-box-seam"></i> Berat FG: <span class="total-weight-fg fw-bold text-success">0.000</span> <span class="text-muted">KG</span></span>
        <span class="text-muted small"><i class="bi bi-exclamation-diamond"></i> Berat NG: <span class="total-weight-ng fw-bold text-danger">0.000</span> <span class="text-muted">KG</span></span>
      </div>
      <div class="mt-2 pt-2 border-top d-flex flex-wrap gap-3 align-items-center">
        <span><span class="fw-bold text-muted small">Eff. OK:</span> <span class="eff-ok fw-bold text-success">0%</span></span>
        <span class="text-muted">+</span>
        <span><span class="fw-bold text-muted small">Eff. NG:</span> <span class="eff-ng fw-bold text-warning">0%</span></span>
        <span class="text-muted">+</span>
        <span><span class="fw-bold text-muted small">Eff. Downtime:</span> <span class="eff-dt fw-bold text-danger">0%</span></span>
        <span class="text-muted">=</span>
        <span class="border-start ps-3">EFISIENSI TOTAL: <span class="efficiency-rate fw-bold text-primary fs-5">0%</span></span>
      </div>
    </div>

  <?php endforeach ?>

  <div class="position-sticky bottom-0 bg-white p-3 border-top shadow-sm d-flex gap-2 justify-content-end mt-3 z-3">
      <button class="btn btn-success fw-bold shadow-sm px-5" id="btnSave" type="submit">
        <i class="bi bi-save me-1"></i> Simpan Data Produksi
      </button>
  </div>

<script>
document.getElementById('hourlyForm').addEventListener('submit', function(e) {
    const form = this;
    const items = {};
    const toDisable = [];
    form.querySelectorAll('[name^="items["]').forEach(function(el) {
        if (el.disabled) return;
        const name = el.name;
        const val = (el.type === 'checkbox') ? (el.checked ? el.value : '') : el.value;
        if (el.type === 'checkbox' && !el.checked) return;
        const keys = []; const regex = /\[([^\]]*)\]/g; let m;
        while ((m = regex.exec(name)) !== null) keys.push(m[1]);
        let obj = items;
        for (let i = 0; i < keys.length - 1; i++) { if (!obj[keys[i]]) obj[keys[i]] = {}; obj = obj[keys[i]]; }
        obj[keys[keys.length - 1]] = val;
        toDisable.push(el);
    });
    let jsonInput = form.querySelector('input[name="items_json"]');
    if (!jsonInput) { jsonInput = document.createElement('input'); jsonInput.type = 'hidden'; jsonInput.name = 'items_json'; form.appendChild(jsonInput); }
    jsonInput.value = JSON.stringify(items);
    toDisable.forEach(function(el) { el.disabled = true; });
});
</script>

</form>

<script>
const NG_CATEGORIES = <?= json_encode(array_map(fn($x)=>[
  'id'=>(int)($x['id'] ?? 0),
  'ng_code'=>(int)($x['ng_code'] ?? 0),
  'ng_name'=>(string)($x['ng_name'] ?? '')
], is_array($ngCategories ?? null) ? $ngCategories : [])) ?>;

const DT_CATEGORIES = <?= json_encode(array_map(fn($x)=>[
  'id'=>(int)($x['id'] ?? 0),
  'downtime_name'=>(string)($x['downtime_name'] ?? '')
], is_array($downtimes ?? null) ? $downtimes : [])) ?>;

function syncOperator(selectEl, shiftId, machineId) {
    const val = selectEl.value;
    const selects = document.querySelectorAll(`.op-sync-${shiftId}-${machineId}`);
    selects.forEach(sel => sel.value = val);
}

function escapeHtml(str) {
  return String(str ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function buildNgSelectOptions(selectedId){
  let html = `<option value="0">-- pilih --</option>`;
  NG_CATEGORIES.forEach(c=>{
    const sel = (parseInt(selectedId||0,10) === c.id) ? 'selected' : '';
    html += `<option value="${c.id}" ${sel}>${c.ng_code} - ${escapeHtml(c.ng_name)}</option>`;
  });
  return html;
}

function readNgHidden(key){
  const hidden = document.getElementById('ngHidden_'+key);
  const rows = [];
  if(!hidden) return rows;

  const inputs = hidden.querySelectorAll('input');
  const map = {};
  inputs.forEach(inp=>{
    const m = inp.name.match(/\[ng_details\]\[(\d+)\]\[(ng_category_id|qty)\]/);
    if(!m) return;
    const idx = m[1];
    const field = m[2];
    map[idx] = map[idx] || {};
    map[idx][field] = inp.value;
  });

  Object.keys(map)
    .sort((a,b)=>parseInt(a,10)-parseInt(b,10))
    .forEach(k=>{
      rows.push({
        ng_category_id: parseInt(map[k].ng_category_id || '0',10),
        qty: parseInt(map[k].qty || '0',10),
      });
    });

  return rows;
}

function writeNgHiddenFromRows(key, rows){
  const hidden = document.getElementById('ngHidden_'+key);
  if(!hidden) return;
  hidden.innerHTML = '';

  rows.forEach((r,i)=>{
    const ngId = parseInt(r.ng_category_id || 0, 10);
    const qty  = parseInt(r.qty || 0, 10);

    const a = document.createElement('input');
    a.type='hidden';
    a.name=`items[${key}][ng_details][${i}][ng_category_id]`;
    a.value=String(isNaN(ngId)?0:ngId);
    hidden.appendChild(a);

    const b = document.createElement('input');
    b.type='hidden';
    b.name=`items[${key}][ng_details][${i}][qty]`;
    b.value=String(isNaN(qty)?0:qty);
    hidden.appendChild(b);
  });
}

function calcTotalNg(rows){
  return rows.reduce((s,r)=>{
    const ngId = parseInt(r.ng_category_id||0,10);
    const qty  = parseInt(r.qty||0,10);
    if(ngId>0 && qty>0) return s + qty;
    return s;
  },0);
}

function updateNgTotalUI(key, total, shiftId){
  const badge = document.getElementById('ngTotalBadge_'+key);
  if(badge) badge.textContent = String(total);

  const ngInp = document.getElementById('ngTotalInput_'+key);
  if(ngInp) {
      ngInp.value = String(total);
      recalcShiftSummary(shiftId);
  }
}

function renderNgTable(key, shiftId){
  const tbody = document.getElementById('ngBody_'+key);
  if(!tbody) return;

  const rows = readNgHidden(key);
  tbody.innerHTML = '';

  if(rows.length === 0){
    tbody.innerHTML = `<tr><td colspan="4"><div class="ng-empty">Belum ada NG</div></td></tr>`;
    updateNgTotalUI(key, 0, shiftId);
  } else {
    rows.forEach((r, idx)=>{
      const cat = NG_CATEGORIES.find(x=>x.id === (parseInt(r.ng_category_id||0,10))) || null;
      const code = cat ? cat.ng_code : '-';

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="ng-no">${escapeHtml(code)}</td>
        <td>
          <select class="form-select form-select-sm ngSel" data-key="${escapeHtml(key)}" data-shift-id="${shiftId}" data-idx="${idx}">
            ${buildNgSelectOptions(r.ng_category_id)}
          </select>
        </td>
        <td class="ng-qty">
          <input type="number" class="form-control form-control-sm ngQty" min="0" data-key="${escapeHtml(key)}" data-shift-id="${shiftId}" data-idx="${idx}" value="${parseInt(r.qty||0,10)}">
        </td>
        <td class="ng-act">
          <button type="button" class="btn btn-sm btn-danger py-0 px-2 ng-del-btn" onclick="deleteNgRowInline('${escapeHtml(key)}', ${idx}, ${shiftId})">
            <i class="bi bi-x"></i>
          </button>
        </td>
      `;
      tbody.appendChild(tr);
    });
    updateNgTotalUI(key, calcTotalNg(rows), shiftId);
  }

  const inlineDiv = document.querySelector(`.ng-inline[data-key="${key}"]`);
  if (inlineDiv) {
     const td = inlineDiv.closest('td.outer-td');
     if (td && td.classList.contains('slot-locked')) {
         inlineDiv.querySelectorAll('input.ngQty').forEach(el => el.readOnly = true);
         inlineDiv.querySelectorAll('select.ngSel, button.ng-del-btn').forEach(el => el.disabled = true);
     }
  }
}

function addNgRowInline(key, shiftId){
  const rows = readNgHidden(key);
  rows.push({ ng_category_id: 0, qty: 0 }); 
  writeNgHiddenFromRows(key, rows);
  renderNgTable(key, shiftId);
}

function deleteNgRowInline(key, idx, shiftId){
  const rows = readNgHidden(key);
  rows.splice(idx, 1);
  writeNgHiddenFromRows(key, rows);
  renderNgTable(key, shiftId);
}

document.addEventListener('change', function(e){
  const sel = e.target.closest('.ngSel');
  if(!sel) return;

  const key = sel.dataset.key;
  const shiftId = sel.dataset.shiftId;
  const idx = parseInt(sel.dataset.idx||'0',10);
  const rows = readNgHidden(key);
  if(!rows[idx]) return;

  rows[idx].ng_category_id = parseInt(sel.value||'0',10);
  writeNgHiddenFromRows(key, rows);

  const cat = NG_CATEGORIES.find(x=>x.id === rows[idx].ng_category_id) || null;
  const tr = sel.closest('tr');
  const codeTd = tr ? tr.querySelector('.ng-no') : null;
  if(codeTd) codeTd.textContent = cat ? cat.ng_code : '-';

  updateNgTotalUI(key, calcTotalNg(rows), shiftId);
});

document.addEventListener('input', function(e){
  const inp = e.target.closest('.ngQty');
  if(!inp) return;

  const key = inp.dataset.key;
  const shiftId = inp.dataset.shiftId;
  const idx = parseInt(inp.dataset.idx||'0',10);
  let v = parseInt(inp.value||'0',10);
  if(isNaN(v) || v < 0) v = 0;
  inp.value = String(v);

  const rows = readNgHidden(key);
  if(!rows[idx]) return;

  rows[idx].qty = v;
  writeNgHiddenFromRows(key, rows);

  updateNgTotalUI(key, calcTotalNg(rows), shiftId);
});

/* ========= DOWNTIME INLINE LOGIC ========= */
function buildDtSelectOptions(selectedId){
  let html = `<option value="0">-- pilih --</option>`;
  if (selectedId === -1 || selectedId === 0) {
      html += `<option value="-1" ${selectedId===-1?'selected':''}>Dandori</option>`;
  }
  DT_CATEGORIES.forEach(c=>{
    const sel = (parseInt(selectedId||0,10) === c.id) ? 'selected' : '';
    html += `<option value="${c.id}" ${sel}>${escapeHtml(c.downtime_name)}</option>`;
  });
  return html;
}

function readDtHidden(key){
  const hidden = document.getElementById('dtHidden_'+key);
  const rows = [];
  if(!hidden) return rows;

  const inputs = hidden.querySelectorAll('input');
  const map = {};
  inputs.forEach(inp=>{
    const m = inp.name.match(/\[dt_details\]\[(\d+)\]\[(downtime_category_id|downtime_minute)\]/);
    if(!m) return;
    const idx = m[1];
    const field = m[2];
    map[idx] = map[idx] || {};
    map[idx][field] = inp.value;
  });

  Object.keys(map)
    .sort((a,b)=>parseInt(a,10)-parseInt(b,10))
    .forEach(k=>{
      rows.push({
        downtime_category_id: parseInt(map[k].downtime_category_id || '0',10),
        downtime_minute: parseInt(map[k].downtime_minute || '0',10),
      });
    });

  return rows;
}

function writeDtHiddenFromRows(key, rows){
  const hidden = document.getElementById('dtHidden_'+key);
  if(!hidden) return;
  hidden.innerHTML = '';

  rows.forEach((r,i)=>{
    const dtId = parseInt(r.downtime_category_id || 0, 10);
    const mins = parseInt(r.downtime_minute || 0, 10);

    const a = document.createElement('input');
    a.type='hidden';
    a.name=`items[${key}][dt_details][${i}][downtime_category_id]`;
    a.value=String(dtId);
    hidden.appendChild(a);

    const b = document.createElement('input');
    b.type='hidden';
    b.name=`items[${key}][dt_details][${i}][downtime_minute]`;
    b.value=String(isNaN(mins)?0:mins);
    hidden.appendChild(b);
  });
}

function calcTotalDtPenalty(rows, shiftId){
  const shiftMinutesEl = document.querySelector(`.shift-active-minutes-display[data-shift-id="${shiftId}"]`);
  const shiftTotalMins = shiftMinutesEl ? parseInt(shiftMinutesEl.dataset.originalMinutes || 0, 10) : 0;
  
  if (shiftTotalMins <= 0) return 0;
  
  let totalMins = 0;
  rows.forEach(r => {
      const dtId = parseInt(r.downtime_category_id||0,10);
      const m = parseInt(r.downtime_minute||0,10);
      if((dtId > 0 || dtId === -1) && m > 0) totalMins += m;
  });
  
  return Math.round((totalMins / shiftTotalMins) * 100);
}

function updateDtTotalUI(key, penalty, shiftId){
  const badge = document.getElementById('dtPenaltyBadge_'+key);
  if(badge) badge.textContent = penalty + '%';

  const dtInp = document.getElementById('dtPenaltyInput_'+key);
  if(dtInp) {
      dtInp.value = String(penalty);
      recalcShiftSummary(shiftId);
  }
}

function renderDtTable(key, shiftId){
  const tbody = document.getElementById('dtBody_'+key);
  if(!tbody) return;

  const rows = readDtHidden(key);
  tbody.innerHTML = '';

  if(rows.length === 0){
    tbody.innerHTML = `<tr><td colspan="3"><div class="ng-empty">Belum ada DT</div></td></tr>`;
    // Jangan reset ke 0 jika dtPenaltyInput sudah punya nilai dari PHP/DB
    const existingPenalty = document.getElementById('dtPenaltyInput_'+key);
    const currentVal = existingPenalty ? parseFloat(existingPenalty.value || 0) : 0;
    if (currentVal > 0) {
        const badge = document.getElementById('dtPenaltyBadge_'+key);
        if (badge) badge.textContent = currentVal + '%';
        recalcShiftSummary(shiftId);
    } else {
        updateDtTotalUI(key, 0, shiftId);
    }
  } else {
    rows.forEach((r, idx)=>{
      const tr = document.createElement('tr');
      const isDandori = parseInt(r.downtime_category_id||0,10) === -1;
      
      let catSelect = `<select class="form-select form-select-sm dtSel" data-key="${escapeHtml(key)}" data-shift-id="${shiftId}" data-idx="${idx}" ${isDandori?'disabled':''}>
            ${buildDtSelectOptions(r.downtime_category_id)}
          </select>`;
          
      tr.innerHTML = `
        <td>${catSelect}</td>
        <td class="ng-qty">
          <input type="number" class="form-control form-control-sm dtQty" min="0" data-key="${escapeHtml(key)}" data-shift-id="${shiftId}" data-idx="${idx}" value="${parseInt(r.downtime_minute||0,10)}" ${isDandori?'readonly':''}>
        </td>
        <td class="ng-act">
          <button type="button" class="btn btn-sm btn-danger py-0 px-2 dt-del-btn" onclick="deleteDtRowInline('${escapeHtml(key)}', ${idx}, ${shiftId})" ${isDandori?'disabled':''}>
            <i class="bi bi-x"></i>
          </button>
        </td>
      `;
      tbody.appendChild(tr);
    });
    updateDtTotalUI(key, calcTotalDtPenalty(rows, shiftId), shiftId);
  }
  
  const inlineDiv = document.querySelector(`.dt-inline[data-key="${key}"]`);
  if (inlineDiv) {
     const td = inlineDiv.closest('td');
     if (td && td.classList.contains('slot-locked')) {
         inlineDiv.querySelectorAll('input.dtQty').forEach(el => el.readOnly = true);
         inlineDiv.querySelectorAll('select.dtSel, button.dt-del-btn, button.dt-add-btn').forEach(el => el.disabled = true);
     }
  }
}

function addDtRowInline(key, shiftId){
  const rows = readDtHidden(key);
  rows.push({ downtime_category_id: 0, downtime_minute: 0 }); 
  writeDtHiddenFromRows(key, rows);
  renderDtTable(key, shiftId);
}

function deleteDtRowInline(key, idx, shiftId){
  const rows = readDtHidden(key);
  rows.splice(idx, 1);
  writeDtHiddenFromRows(key, rows);
  renderDtTable(key, shiftId);
}

document.addEventListener('change', function(e){
  const sel = e.target.closest('.dtSel');
  if(!sel) return;

  const key = sel.dataset.key;
  const shiftId = sel.dataset.shiftId;
  const idx = parseInt(sel.dataset.idx||'0',10);
  const rows = readDtHidden(key);
  if(!rows[idx]) return;

  rows[idx].downtime_category_id = parseInt(sel.value||'0',10);
  writeDtHiddenFromRows(key, rows);

  updateDtTotalUI(key, calcTotalDtPenalty(rows, shiftId), shiftId);
});

document.addEventListener('input', function(e){
  const inp = e.target.closest('.dtQty');
  if(!inp) return;

  const key = inp.dataset.key;
  const shiftId = inp.dataset.shiftId;
  const idx = parseInt(inp.dataset.idx||'0',10);
  let v = parseInt(inp.value||'0',10);
  if(isNaN(v) || v < 0) v = 0;
  inp.value = String(v);

  const rows = readDtHidden(key);
  if(!rows[idx]) return;

  rows[idx].downtime_minute = v;
  writeDtHiddenFromRows(key, rows);

  updateDtTotalUI(key, calcTotalDtPenalty(rows, shiftId), shiftId);
});


/* ========= TIME SLOT LOCK, REST TIME & DYNAMIC TARGET RECALCULATION ========= */
function recalculateTargets(shiftId) {
    const shiftMinutesEl = document.querySelector(`.shift-active-minutes-display[data-shift-id="${shiftId}"]`);
    if (!shiftMinutesEl) return;

    // Total menit aktif shift sudah dikurangi break oleh backend
    const originalMinutes = parseInt(shiftMinutesEl.dataset.originalMinutes || 0, 10);
    if (originalMinutes <= 0) return;

    shiftMinutesEl.innerText = `${originalMinutes} Menit`;

    const rows = document.querySelectorAll(`tbody[data-shift-id="${shiftId}"] .item-row`);
    rows.forEach(row => {
        const originalTarget = parseInt(row.dataset.originalTarget || 0, 10);
        const rowTotalMinute = parseInt(row.dataset.rowTotalMinutes || originalMinutes, 10);
        
        let sumTarget = 0;
        // Per-slot target using slot minutes directly
        document.querySelectorAll(`th.slot-header[data-shift-id="${shiftId}"]`).forEach(th => {
            const slotId = th.dataset.slotId;
            const slotTargetCell = row.querySelector(`.slot-target-cell[data-slot-id="${slotId}"]`);
            if (!slotTargetCell) return;

            // Abaikan slot non-aktif (yang opacity-50 atau td kosong yg di-generate)
            if (slotTargetCell.innerText.trim() === '-' && slotTargetCell.title === "Slot tidak aktif (per mesin)") return;

            const targetInput = slotTargetCell.querySelector('.val-target');
            if (targetInput) {
                const slotMinsAttr = parseInt(slotTargetCell.dataset.slotMinutes || 0, 10);
                const slotMin = Math.max(0, slotMinsAttr);
                
                const isManual = parseInt(targetInput.dataset.manual || 0, 10);
                if (!isManual) {
                    if (slotMin <= 0 || rowTotalMinute <= 0) {
                        targetInput.value = '0';
                    } else {
                        const newSlotTarget = Math.round(originalTarget * (slotMin / rowTotalMinute));
                        targetInput.value = newSlotTarget;
                    }
                }
                sumTarget += parseInt(targetInput.value || 0, 10);
            }
        });
        
        const shiftTargetDisplay = row.querySelector('.target-shift-display');
        // if (shiftTargetDisplay) shiftTargetDisplay.innerText = sumTarget; // Target shift tetap terkunci
    });

    recalcShiftSummary(shiftId);
}

/* ========= AUTO CALCULATE SHIFT SUMMARY & EFFICIENCY ========= */
function recalcShiftSummary(shiftId) {
    const tbody = document.querySelector(`tbody[data-shift-id="${shiftId}"]`);
    if(!tbody) return;

    let totalTarget = 0;
    let totalFg = 0;
    let totalNg = 0;
    let totalWeightFg = 0;
    let totalWeightNg = 0;

    const machineSummaries = {}; // { machineId: { code: string, target: num, fg: num, ng: num, dt: num } }

    tbody.querySelectorAll('.item-row').forEach(row => {
        const weight = parseFloat(row.dataset.weightMc || 0) || 0;
        const machineIdOriginal = row.dataset.machineId;
        const productId = row.dataset.productId;
        const machineCode = row.dataset.machineCode || 'Mesin';
        const partName = row.dataset.partName || '';
        const machineId = machineIdOriginal + '_' + productId;
        
        if (!machineSummaries[machineId]) {
            machineSummaries[machineId] = { code: machineCode + (partName ? ' - ' + partName : ''), target: 0, fg: 0, ng: 0, dt: 0, dtMins: 0, ngDetails: {}, dtDetails: {} };
        }

        let rowTarget = parseInt(row.querySelector('.target-shift-display')?.innerText.trim() || 0, 10);
        totalTarget += rowTarget;
        machineSummaries[machineId].target += rowTarget;
        
        let rowFg = 0;
        row.querySelectorAll('.val-fg').forEach(inp => {
            const v = parseInt(inp.value || 0, 10);
            if (!isNaN(v)) rowFg += v;
        });
        totalFg += rowFg;
        totalWeightFg += (rowFg * weight);
        machineSummaries[machineId].fg += rowFg;

        let rowNg = 0;
        row.querySelectorAll('.val-ng').forEach(inp => {
            const v = parseInt(inp.value || 0, 10);
            if (!isNaN(v)) rowNg += v;
        });
        totalNg += rowNg;
        totalWeightNg += (rowNg * weight);
        machineSummaries[machineId].ng += rowNg;

        // Collect NG category names per machine
        row.querySelectorAll('.ng-inline[data-key]').forEach(ngBox => {
            const key = ngBox.getAttribute('data-key');
            const hiddenDiv = document.getElementById('ngHidden_'+key);
            if (!hiddenDiv) return;
            const inputs = hiddenDiv.querySelectorAll('input');
            const map = {};
            inputs.forEach(inp => {
                const m = inp.name.match(/\[ng_details\]\[(\d+)\]\[(ng_category_id|qty)\]/);
                if (!m) return;
                map[m[1]] = map[m[1]] || {};
                map[m[1]][m[2]] = inp.value;
            });
            Object.values(map).forEach(entry => {
                const catId = parseInt(entry.ng_category_id || 0, 10);
                const qty = parseInt(entry.qty || 0, 10);
                if (catId > 0 && qty > 0) {
                    const cat = NG_CATEGORIES.find(c => c.id === catId);
                    const name = cat ? cat.ng_name : 'NG-'+catId;
                    machineSummaries[machineId].ngDetails[name] = (machineSummaries[machineId].ngDetails[name] || 0) + qty;
                }
            });
        });

        // Downtime for this row/machine
        let rowDt = 0;
        row.querySelectorAll('.dt-penalty-val').forEach(inp => {
            rowDt += parseFloat(inp.value || 0);
        });
        machineSummaries[machineId].dt += rowDt;

        let rowDtMins = 0;
        row.querySelectorAll('.dtQty').forEach(inp => {
            rowDtMins += parseFloat(inp.value || 0);
        });
        machineSummaries[machineId].dtMins += rowDtMins;

        // Collect DT category names per machine
        row.querySelectorAll('.dt-inline[data-key]').forEach(dtBox => {
            const key = dtBox.getAttribute('data-key');
            const hiddenDiv = document.getElementById('dtHidden_'+key);
            if (!hiddenDiv) return;
            const inputs = hiddenDiv.querySelectorAll('input');
            const map = {};
            inputs.forEach(inp => {
                const m = inp.name.match(/\[dt_details\]\[(\d+)\]\[(downtime_category_id|downtime_minute)\]/);
                if (!m) return;
                map[m[1]] = map[m[1]] || {};
                map[m[1]][m[2]] = inp.value;
            });
            Object.values(map).forEach(entry => {
                const catId = parseInt(entry.downtime_category_id || 0, 10);
                const mins = parseInt(entry.downtime_minute || 0, 10);
                if (mins > 0) {
                    let name = 'Dandori';
                    if (catId > 0) {
                        const cat = DT_CATEGORIES.find(c => c.id === catId);
                        name = cat ? cat.downtime_name : 'DT-'+catId;
                    }
                    machineSummaries[machineId].dtDetails[name] = (machineSummaries[machineId].dtDetails[name] || 0) + mins;
                }
            });
        });
    });

    const shiftMinutesEl = document.querySelector(`.shift-active-minutes-display[data-shift-id="${shiftId}"]`);
    const totalShiftMinutes = shiftMinutesEl ? parseInt(shiftMinutesEl.dataset.originalMinutes || 0, 10) : 0;

    let totalDowntimeVal = 0;
    Object.values(machineSummaries).forEach(m => totalDowntimeVal += m.dt);
    let effDt = totalDowntimeVal;

    const box = document.querySelector(`.summary-box[data-shift-id="${shiftId}"]`);
    if(box) {
        box.querySelector('.total-target').innerText = totalTarget.toLocaleString('id-ID');
        box.querySelector('.total-fg').innerText = totalFg.toLocaleString('id-ID');
        box.querySelector('.total-ng').innerText = totalNg.toLocaleString('id-ID');

        // Update Weight (Format to KG - nilai sudah dalam KG)
        const weightFgSpan = box.querySelector('.total-weight-fg');
        const weightNgSpan = box.querySelector('.total-weight-ng');
        
        const finalWeightFg = totalWeightFg;
        const finalWeightNg = totalWeightNg;

        if (weightFgSpan) weightFgSpan.innerText = finalWeightFg.toLocaleString('id-ID', {minimumFractionDigits: 3, maximumFractionDigits: 3});
        if (weightNgSpan) weightNgSpan.innerText = finalWeightNg.toLocaleString('id-ID', {minimumFractionDigits: 3, maximumFractionDigits: 3});

        // Efisiensi OK = Total FG / Total Target * 100%
        let effOk = totalTarget > 0 ? (totalFg / totalTarget) * 100 : 0;

        // Efisiensi NG = Total NG / Total Target * 100%
        let effNg = totalTarget > 0 ? (totalNg / totalTarget) * 100 : 0;

        // Total Efisiensi = Efisiensi OK + Efisiensi NG + Efisiensi Downtime
        let efficiency = effOk + effNg + effDt;


        const effOkSpan = box.querySelector('.eff-ok');
        const effNgSpan = box.querySelector('.eff-ng');
        const effDtSpan = box.querySelector('.eff-dt');
        const effSpan = box.querySelector('.efficiency-rate');

        if (effOkSpan) effOkSpan.innerText = effOk.toFixed(2) + '%';
        if (effNgSpan) effNgSpan.innerText = effNg.toFixed(2) + '%';
        if (effDtSpan) effDtSpan.innerText = effDt.toFixed(2) + '%';
        if(effSpan) {
            effSpan.innerText = efficiency.toFixed(2) + '%';
            effSpan.className = 'efficiency-rate fw-bold fs-5 ' + (efficiency >= 90 ? 'text-success' : (efficiency >= 75 ? 'text-warning' : 'text-danger'));
        }

        const machineContainer = box.querySelector('.machine-summary-container');
        if (machineContainer) {
            let tableHtml = `<div class="table-responsive w-100 mt-2">
              <table class="table table-bordered table-sm text-center align-middle bg-white mb-0" style="font-size: 13px;">
                <thead class="table-light">
                  <tr>
                    <th rowspan="2" class="align-middle">Mesin & Part</th>
                    <th rowspan="2" class="align-middle">Target</th>
                    <th colspan="2">OK (FG)</th>
                    <th colspan="3">NG</th>
                    <th colspan="3">Downtime</th>
                    <th rowspan="2" class="align-middle">Total Efisiensi</th>
                  </tr>
                  <tr>
                    <th>Qty</th><th>%</th>
                    <th>Qty</th><th>%</th><th style="max-width:200px">Kategori NG</th>
                    <th>Menit</th><th>%</th><th style="max-width:200px">Kategori DT</th>
                  </tr>
                </thead>
                <tbody>`;

            Object.values(machineSummaries).forEach(m => {
                let mEffOk = m.target > 0 ? (m.fg / m.target) * 100 : 0;
                let mEffNg = m.target > 0 ? (m.ng / m.target) * 100 : 0;
                let mEffTotal = mEffOk + mEffNg + m.dt;
                
                let effClass = mEffTotal >= 90 ? 'text-success' : (mEffTotal >= 75 ? 'text-warning text-dark' : 'text-danger');
                
                // Build NG detail string
                let ngDetailHtml = '';
                const ngEntries = Object.entries(m.ngDetails || {});
                if (ngEntries.length > 0) {
                    ngDetailHtml = ngEntries.map(([name, qty]) => `<span class="badge bg-danger bg-opacity-10 text-danger me-1 mb-1" style="font-size:11px; white-space:normal; text-align:left;">${escapeHtml(name)}: ${qty}</span>`).join('');
                } else {
                    ngDetailHtml = '-';
                }

                // Build DT detail string
                let dtDetailHtml = '';
                const dtEntries = Object.entries(m.dtDetails || {});
                if (dtEntries.length > 0) {
                    dtDetailHtml = dtEntries.map(([name, mins]) => `<span class="badge bg-warning bg-opacity-10 text-dark me-1 mb-1" style="font-size:11px; white-space:normal; text-align:left;">${escapeHtml(name)}: ${mins} mnt</span>`).join('');
                } else {
                    dtDetailHtml = '-';
                }

                tableHtml += `
                  <tr>
                    <td class="fw-bold text-start"><i class="bi bi-display"></i> ${escapeHtml(m.code)}</td>
                    <td class="fw-bold">${m.target.toLocaleString('id-ID')}</td>
                    <td class="fw-bold text-success">${m.fg.toLocaleString('id-ID')}</td>
                    <td class="text-success">${mEffOk.toFixed(2)}%</td>
                    <td class="fw-bold text-danger">${m.ng.toLocaleString('id-ID')}</td>
                    <td class="text-danger">${mEffNg.toFixed(2)}%</td>
                    <td class="text-start" style="min-width: 150px;">${ngDetailHtml}</td>
                    <td class="fw-bold text-secondary">${m.dtMins || 0}</td>
                    <td class="text-secondary">${m.dt.toFixed(2)}%</td>
                    <td class="text-start" style="min-width: 150px;">${dtDetailHtml}</td>
                    <td class="fw-bold ${effClass} fs-6">${mEffTotal.toFixed(2)}%</td>
                  </tr>`;
            });

            tableHtml += `</tbody></table></div>`;
            machineContainer.innerHTML = tableHtml;
        }
    }
}

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('val-fg') || e.target.classList.contains('val-target')) {
        const shiftId = e.target.closest('.shift-body').dataset.shiftId;
        
        if (e.target.classList.contains('val-target')) {
            e.target.dataset.manual = "1";
            
            // NOTE: target-shift-display is intentionally NOT updated here.
            // The shift target reflects the daily schedule plan and should remain fixed.
            
            // Add hidden input so backend can recalculate daily schedule target_per_shift easily
            let existingRowTotalTarget = row.querySelector('.row-total-target-input');
            if (!existingRowTotalTarget) {
                const dummyId = escapeHtml(shiftId) + '_' + escapeHtml(row.dataset.machineId) + '_' + escapeHtml(row.dataset.productId);
                row.insertAdjacentHTML('beforeend', `<input type="hidden" name="row_targets[${dummyId}]" class="row-total-target-input" value="${parseInt(row.querySelector('.target-shift-display')?.innerText || '0', 10)}">`);
            }
        }
        recalcShiftSummary(shiftId);
    }
});

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('dt-sel')) {
        const shiftId = e.target.dataset.shiftId;
        recalcShiftSummary(shiftId);
    }
});

function parseTimeOnDate(dateISO, hhmmss){
  const t = String(hhmmss || '').slice(0,5);
  return new Date(`${dateISO}T${t}:00`);
}

function isSlotActive(prodDateISO, start, end){
  const now = new Date();
  let s = parseTimeOnDate(prodDateISO, start);
  let e = parseTimeOnDate(prodDateISO, end);

  const startHour = parseInt(String(start).split(':')[0], 10);
  const endHour = parseInt(String(end).split(':')[0], 10);

  if (startHour >= 0 && startHour < 7) s.setDate(s.getDate() + 1);
  if (endHour >= 0 && endHour < 7) e.setDate(e.getDate() + 1);
  if (e <= s) e.setDate(e.getDate() + 1);

  // Tambahan waktu 1 jam agar slot masih bisa diisi setelah waktunya habis
  const e_extended = new Date(e.getTime() + (60 * 60 * 1000));

  return now >= s && now <= e_extended;
}

/* ========= CHECK IF DANDORI STILL RUNNING ========= */
function isDandoriStillRunning(prodDateISO, slotStart, dandoriMinutes) {
    if (!dandoriMinutes || dandoriMinutes <= 0) return false;
    const now = new Date();
    let slotStartDt = parseTimeOnDate(prodDateISO, slotStart);
    const startHour = parseInt(String(slotStart).split(':')[0], 10);
    if (startHour >= 0 && startHour < 7) slotStartDt.setDate(slotStartDt.getDate() + 1);
    const dandoriEndDt = new Date(slotStartDt.getTime() + dandoriMinutes * 60 * 1000);
    return now >= slotStartDt && now < dandoriEndDt;
}

function applySlotLock(){
  const prodDateISO = "<?= esc($date) ?>";
  const overrideToggle = document.getElementById('adminOverrideToggle');
  const isAdminOverride = overrideToggle ? overrideToggle.checked : false;

  document.querySelectorAll('th.slot-header').forEach(th => {
     const shiftId = th.dataset.shiftId;
     const slotId = th.dataset.slotId;
     const slotStart = th.dataset.start;
     const isCurrentTime = isSlotActive(prodDateISO, th.dataset.start, th.dataset.end);
     
     // is_break dari backend (data-is-break attribute)
     const isBreak = th.dataset.isBreak === '1';
     
     const canEditSlot = (isAdminOverride || (!isBreak && isCurrentTime));

     th.classList.toggle('slot-header-active', isCurrentTime && !isBreak);
     th.classList.toggle('slot-rest', isBreak && !isAdminOverride);
     th.classList.toggle('bg-secondary', isBreak && !isAdminOverride);
     th.classList.toggle('bg-opacity-25', isBreak && !isAdminOverride);

     document.querySelectorAll(`.fg[data-shift-id="${shiftId}"][data-slot-id="${slotId}"]`).forEach(inp => {
         const dandoriMinutes = parseInt(inp.dataset.dandoriMinutes || 0, 10);
         const dandoriRunning = !isAdminOverride && isDandoriStillRunning(prodDateISO, slotStart, dandoriMinutes);
         const canEdit = canEditSlot && !dandoriRunning;
         
         inp.readOnly = !canEdit;
         
         const tdFg = inp.closest('td');
         if(tdFg) {
             const tdTarget = tdFg.previousElementSibling;
             const tdNg = tdFg.nextElementSibling;
             const tdNgInline = tdNg ? tdNg.nextElementSibling : null;
             const tdDowntime = tdNgInline ? tdNgInline.nextElementSibling : null;
             
             [tdTarget, tdFg, tdNg, tdNgInline, tdDowntime].forEach(td => {
                 if(td) {
                     td.classList.toggle('slot-active', isCurrentTime && !isBreak && !dandoriRunning && !isAdminOverride);
                     td.classList.toggle('slot-locked', !canEdit);
                     td.classList.toggle('slot-rest', isBreak && !isAdminOverride);
                     td.classList.toggle('slot-dandori-running', dandoriRunning && !isAdminOverride);
                 }
             });
             
             // Show/update dandori countdown in target cell
             if (tdTarget) {
                 const btnAkhiri = tdTarget.querySelector('.btn-akhiri-dandori');
                 if (dandoriRunning && !isAdminOverride) {
                     if (btnAkhiri) btnAkhiri.classList.remove('d-none');

                     let countdownEl = tdTarget.querySelector('.dandori-countdown');
                     if (!countdownEl) {
                         countdownEl = document.createElement('div');
                         countdownEl.className = 'dandori-countdown text-danger fw-bold mb-1';
                         countdownEl.style.fontSize = '0.6rem';
                         if (btnAkhiri) tdTarget.insertBefore(countdownEl, btnAkhiri);
                         else tdTarget.appendChild(countdownEl);
                     }
                     const slotStartDt = parseTimeOnDate(prodDateISO, slotStart);
                     const startHour = parseInt(String(slotStart).split(':')[0], 10);
                     if (startHour >= 0 && startHour < 7) slotStartDt.setDate(slotStartDt.getDate() + 1);
                     const dandoriEndDt = new Date(slotStartDt.getTime() + dandoriMinutes * 60 * 1000);
                     const remainSec = Math.max(0, Math.floor((dandoriEndDt - new Date()) / 1000));
                     const mm = Math.floor(remainSec / 60).toString().padStart(2,'0');
                     const ss = (remainSec % 60).toString().padStart(2,'0');
                     countdownEl.textContent = `⏱ ${mm}:${ss}`;
                 } else {
                     if (btnAkhiri) btnAkhiri.classList.add('d-none');
                     const countdownEl = tdTarget.querySelector('.dandori-countdown');
                     if (countdownEl) countdownEl.remove();
                 }
             }
         }
     });

     document.querySelectorAll(`.ng-add-btn[data-shift-id="${shiftId}"][data-slot-id="${slotId}"]`).forEach(btn => {
         const dandoriMinutes = parseInt(btn.dataset.dandoriMinutes || 0, 10);
         const dandoriRunning = !isAdminOverride && isDandoriStillRunning(prodDateISO, slotStart, dandoriMinutes);
         const canEdit = canEditSlot && !dandoriRunning;
         
         btn.disabled = !canEdit;
         
         const key = btn.closest('.ng-inline').dataset.key;
         const ngBody = document.getElementById(`ngBody_${key}`);
         if(ngBody) {
             ngBody.querySelectorAll('input.ngQty').forEach(el => el.readOnly = !canEdit);
             ngBody.querySelectorAll('select.ngSel, button.ng-del-btn').forEach(el => el.disabled = !canEdit);
         }
     });

     document.querySelectorAll(`.dt-add-btn[data-shift-id="${shiftId}"][data-slot-id="${slotId}"]`).forEach(btn => {
         const dandoriMinutes = parseInt(btn.dataset.dandoriMinutes || 0, 10);
         const dandoriRunning = !isAdminOverride && isDandoriStillRunning(prodDateISO, slotStart, dandoriMinutes);
         const canEdit = canEditSlot && !dandoriRunning;
         
         btn.disabled = !canEdit;
         
         const key = btn.closest('.dt-inline').dataset.key;
         const dtBody = document.getElementById(`dtBody_${key}`);
         if(dtBody) {
             dtBody.querySelectorAll('input.dtQty').forEach(el => el.readOnly = !canEdit);
             dtBody.querySelectorAll('select.dtSel, button.dt-del-btn').forEach(el => el.disabled = !canEdit);
         }
     });
  });
}

const adminOverrideEl = document.getElementById('adminOverrideToggle');
if (adminOverrideEl) {
  adminOverrideEl.addEventListener('change', function() {
    applySlotLock();
    document.querySelectorAll('.shift-body').forEach(tbody => {
      recalculateTargets(tbody.dataset.shiftId);
    });
  });
}

applySlotLock();
document.querySelectorAll('.shift-body').forEach(tbody => {
    recalculateTargets(tbody.dataset.shiftId);
});
// Refresh setiap 60 detik untuk mendeteksi expired dandori otomatis
setInterval(applySlotLock, 60000);

document.querySelectorAll('.ng-inline[data-key]').forEach(box=>{
  const key = box.getAttribute('data-key');
  const btn = box.querySelector('.ng-add-btn');
  const shiftId = btn ? btn.dataset.shiftId : null;
  renderNgTable(key, shiftId);
});

document.querySelectorAll('.dt-inline[data-key]').forEach(box=>{
  const key = box.getAttribute('data-key');
  // Gunakan data-shift-id dari div .dt-inline langsung (lebih reliable dari button)
  const shiftId = box.dataset.shiftId || (box.querySelector('.dt-add-btn')?.dataset.shiftId) || box.closest('.shift-body')?.dataset.shiftId;
  renderDtTable(key, shiftId);
});

// Fix: Recalculate summary AFTER all DT tables have been rendered
// This ensures downtime percentages are correct on page refresh
document.querySelectorAll('.shift-body').forEach(tbody => {
    recalcShiftSummary(tbody.dataset.shiftId);
});

async function akhiriDandori(date, shiftId, machineId, timeSlotId) {
    if (!confirm('Akhiri dandori sekarang? Target akan dihitung ulang berdasarkan sisa waktu menit.')) return;
    
    try {
        const formData = new FormData();
        formData.append('date', date);
        formData.append('shift_id', shiftId);
        formData.append('machine_id', machineId);
        formData.append('time_slot_id', timeSlotId);
        
        const res = await fetch('<?= site_url('machining/hourly/endDandori') ?>', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const data = await res.json();
        if (data.ok) {
            window.location.reload();
        } else {
            alert('Gagal mengakhiri dandori: ' + (data.msg || 'Terjadi kesalahan sistem.'));
        }
    } catch(e) {
        alert('Terjadi kesalahan jaringan.');
    }
}

/* ============================
 * MODAL OPERATOR PER SLOT
 * ============================ */
// Data slot per shift (PHP → JS)
const shiftSlotsForModal = <?= json_encode(array_map(function($shift) {
    return array_map(function($slot) {
        return [
            'id'         => (int)$slot['id'],
            'label'      => substr($slot['time_start'],0,5) . ' - ' . substr($slot['time_end'],0,5),
            'is_break'   => (int)($slot['is_break'] ?? 0),
        ];
    }, $shift['slots']);
}, array_combine(
    array_column($shifts, 'id'),
    $shifts
))) ?>;

const operatorNames = <?= json_encode(array_map(fn($op) => $op['operator_name'], $operators)) ?>;

let _modalShiftId = null, _modalMachineId = null;

function openOperatorModal(shiftId, machineId, machineCode) {
    _modalShiftId   = shiftId;
    _modalMachineId = machineId;

    document.getElementById('opModalTitle').textContent = 'Atur Operator – Mesin ' + machineCode;

    const slots = shiftSlotsForModal[shiftId] || [];
    const tbody = document.getElementById('opModalBody');
    tbody.innerHTML = '';

    slots.forEach(slot => {
        if (slot.is_break) return;

        const existingInput = document.querySelector(
            `input.op-slot-input-${shiftId}-${machineId}[data-slot-id="${slot.id}"]`
        );
        const currentVal = existingInput ? existingInput.value : '';

        let optHtml = `<option value="">-- Tidak Diassign --</option>`;
        operatorNames.forEach(name => {
            const sel = name === currentVal ? 'selected' : '';
            optHtml += `<option value="${name}" ${sel}>${name}</option>`;
        });

        tbody.insertAdjacentHTML('beforeend', `
          <tr>
            <td class="text-muted small" style="white-space:nowrap">${slot.label}</td>
            <td>
              <select class="form-select form-select-sm op-modal-sel" data-slot-id="${slot.id}">
                ${optHtml}
              </select>
            </td>
          </tr>
        `);
    });

    const modal = new bootstrap.Modal(document.getElementById('operatorModal'));
    modal.show();
}
</script>

<!-- Modal Operator Per Slot -->
<div class="modal fade" id="operatorModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="opModalTitle">Atur Operator</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <table class="table table-sm table-bordered">
          <thead class="table-light"><tr><th>Slot Waktu</th><th>Operator</th></tr></thead>
          <tbody id="opModalBody"></tbody>
        </table>
        <small class="text-muted">Pilih "Tidak Diassign" untuk slot yang tidak ada operator.</small>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button class="btn btn-primary" id="opModalSave"><i class="bi bi-save"></i> Simpan</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const saveBtn = document.getElementById('opModalSave');
    if(saveBtn) {
        saveBtn.addEventListener('click', function() {
            const shiftId   = _modalShiftId;
            const machineId = _modalMachineId;

            document.querySelectorAll(`.op-slot-input-${shiftId}-${machineId}`).forEach(el => el.remove());

            const container = document.querySelector(`button.btn-atur-operator[data-shift-id="${shiftId}"][data-machine-id="${machineId}"]`).parentElement;
            const selects   = document.querySelectorAll('#opModalBody .op-modal-sel');
            const assigned  = [];

            selects.forEach(sel => {
                const slotId = sel.dataset.slotId;
                const opName = sel.value;
                if (opName) {
                    assigned.push(opName);
                    const inp = document.createElement('input');
                    inp.type  = 'hidden';
                    inp.name  = `operators[${shiftId}][${machineId}][${slotId}]`;
                    inp.value = opName;
                    inp.className = `op-slot-input-${shiftId}-${machineId}`;
                    inp.dataset.slotId = slotId;
                    container.appendChild(inp);
                }
            });

            const btn = container.querySelector('.btn-atur-operator');
            const unique = [...new Set(assigned)];
            btn.innerHTML = `<i class="bi bi-person-fill-gear"></i> ${unique.length ? unique.join(', ') : 'Atur Operator'}`;

            bootstrap.Modal.getInstance(document.getElementById('operatorModal')).hide();
        });
    }
});
</script>

<?= $this->endSection() ?>
