<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
  :root{
    --w-time: 140px;
    --w-part: 280px;
    --w-sch: 170px;
    --w-sub: 95px;

    --border: #e5e7eb;
    --head: #f8fafc;
    --subhead: #f1f5f9;
    --stock: #FEF3C7;
    --active: #dcfce7;
  }

  .page-head{ display:flex; gap:12px; align-items:flex-end; margin-bottom:14px; flex-wrap:wrap; }
  .page-head .box{ border:1px solid var(--border); border-radius:10px; background:#fff; padding:10px 12px; min-width:180px; }
  .page-head .box.highlight{ background: var(--stock); }
  .page-head .label{ font-size:12px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:.3px; }
  .page-head .value{ font-size:16px; font-weight:900; margin-top:2px; }

  .sections-row{ display:flex; gap:14px; overflow:auto; padding-bottom:10px; }
  .section-col{ min-width: 1050px; flex: 0 0 auto; } /* sedikit dilebarkan karena tambah kolom */

  .section-card{ border:1px solid var(--border); border-radius:12px; background:#fff; overflow:hidden; }
  .section-head{
    padding:10px 12px; border-bottom:1px solid var(--border); background:#fff;
    display:flex; align-items:center; justify-content:space-between; gap:10px;
  }
  .section-head .title{ font-weight:900; text-transform:uppercase; letter-spacing:.3px; }
  .section-head .meta{ font-size:12px; font-weight:800; color:#64748b; }

  .shift-card{ border-top:1px solid var(--border); }
  .shift-title{
    padding:10px 12px; background:#fff; border-bottom:1px solid var(--border);
    font-weight:900; text-transform:uppercase; letter-spacing:.3px;
  }

  .table-scroll{ overflow:auto; }

  table.wip{ width:max-content; border-collapse:separate; border-spacing:0; table-layout:fixed; font-size:13px; }
  table.wip th, table.wip td{
    border-right:1px solid var(--border);
    border-bottom:1px solid var(--border);
    padding:6px 8px;
    white-space:nowrap;
    vertical-align:middle;
    text-align:center;
    background:#fff;
  }

  table.wip thead tr.row-1 th{
    position:sticky; top:0; z-index:30;
    background:var(--head);
    font-weight:900;
    height:38px;
  }
  table.wip thead tr.row-2 th{
    position:sticky; top:38px; z-index:29;
    background:var(--subhead);
    font-weight:900;
    font-size:12px;
    height:34px;
  }

  .sticky-1{ position:sticky; left:0; z-index:40; background:#fff; }
  .sticky-2{ position:sticky; left:var(--w-time); z-index:40; background:#fff; }
  .sticky-3{ position:sticky; left:calc(var(--w-time) + var(--w-part)); z-index:40; background:#fff; }
  thead .sticky-1, thead .sticky-2, thead .sticky-3{ z-index:60 !important; background:var(--head) !important; }

  .col-time{ width:var(--w-time); }
  .col-part{ width:var(--w-part); text-align:left !important; }
  .col-sch{ width:var(--w-sch); text-align:left !important; }
  .col-sub{ width:var(--w-sub); }

  .prod-title{ font-weight:900; line-height:1.05; }
  .prod-sub{ font-size:12px; color:#64748b; line-height:1.1; }

  .sch-box{ font-weight:900; line-height:1.35; }
  .sch-line{ display:flex; justify-content:space-between; gap:12px; }
  .sch-line span:first-child{ color:#64748b; font-weight:800; }

  .row-active td{ background: var(--active); }

  .stock-cell{
    background: var(--stock) !important;
    font-weight:900;
    vertical-align: middle !important;
    text-align: center !important;
  }
  .stock-box{
    height:100%;
    display:flex;
    align-items:center;
    justify-content:center;
  }
  .stock-line{
    display:flex;
    gap:10px;
    align-items:center;
    justify-content:center;
  }
  .stock-line span:first-child{
    color:#64748b;
    font-weight:800;
    text-transform:lowercase;
  }

  /* transfer cell (warna netral) */
  .transfer-cell{
    font-weight:900;
    vertical-align: middle !important;
    text-align: center !important;
    background:#fff !important;
  }

  .spacer-row td{ background:#f8fafc; height:10px; padding:0; }
</style>

<h4 class="mb-2">
  <i class="bi bi-box-seam me-2"></i> WIP – PER TIME SLOT
</h4>

<div class="page-head">
  <div class="box highlight">
    <div class="label">date</div>
    <div class="value"><?= esc($date) ?></div>
  </div>
  <div class="box highlight" style="min-width:140px">
    <div class="label">time</div>
    <div class="value"><?= esc($nowTime) ?></div>
  </div>

  <form method="get" class="ms-auto d-flex gap-2 align-items-end">
    <div>
      <div class="label mb-1">filter date</div>
      <input type="date" name="date" value="<?= esc($date) ?>" class="form-control">
    </div>
    <div>
      <button class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
    </div>
  </form>
</div>

<?php if (empty($sections)): ?>
  <div class="alert alert-warning">Tidak ada data.</div>
<?php else: ?>

  <div class="sections-row">
    <?php foreach ($sections as $sec): ?>
      <?php
        $procLabel = $sec['process_label'] ?? 'SECTION';
        $shiftBlocks = $sec['shifts'] ?? [];
      ?>

      <div class="section-col">
        <div class="section-card">
          <div class="section-head">
            <div class="title"><?= esc($procLabel) ?></div>
            <div class="meta">Current = WIP per slot • Transfer = qty_out (Finish Shift 3)</div>
          </div>

          <?php foreach ($shiftBlocks as $sh): ?>
            <?php
              $shiftName = $sh['shift_name'] ?? 'SHIFT';
              $products  = $sh['products'] ?? [];
              $slots     = $sh['slots'] ?? [];
            ?>

            <div class="shift-card">
              <div class="shift-title"><?= esc($shiftName) ?></div>

              <div class="table-scroll">
                <table class="wip">
                  <thead>
                    <tr class="row-1">
                      <th class="col-time sticky-1" rowspan="2">Time</th>
                      <th class="col-part sticky-2" rowspan="2">Part</th>
                      <th class="col-sch  sticky-3" rowspan="2">Schedule</th>
                      <!-- ✅ colspan jadi 4 -->
                      <th class="col-sub" colspan="4"><?= esc($procLabel) ?></th>
                    </tr>
                    <tr class="row-2">
                      <th class="col-sub">Current</th>
                      <th class="col-sub">WIP</th>
                      <th class="col-sub stock-cell">Stock</th>
                      <th class="col-sub">Transfer</th>
                    </tr>
                  </thead>

                  <tbody>
                    <?php if (empty($products) || empty($slots)): ?>
                      <tr>
                        <td class="sticky-1">-</td>
                        <td class="sticky-2 col-part">-</td>
                        <td class="sticky-3 col-sch">-</td>
                        <td>0</td><td>0</td><td class="stock-cell">0</td><td>0</td>
                      </tr>
                    <?php else: ?>

                      <?php foreach ($products as $p): ?>
                        <?php
                          $rows = $p['rows'] ?? [];
                          $rowspan = count($rows);
                          if ($rowspan <= 0) continue;

                          $activeRowIdx = -1;
                          foreach ($rows as $idx => $r) {
                            $parts = explode('-', (string)($r['time_label'] ?? ''));
                            $start = trim($parts[0] ?? '');
                            $end   = trim($parts[1] ?? '');
                            if ($start && $end && $nowTime >= $start && $nowTime <= $end) { $activeRowIdx = $idx; break; }
                          }

                          $stockTotal    = (int)($p['stock_total'] ?? 0);
                          $transferTotal = (int)($p['transfer_total'] ?? 0); // ✅ dari controller
                        ?>

                        <?php foreach ($rows as $i => $r): ?>
                          <?php
                            $isActive = ($i === $activeRowIdx);
                            $cur = (int)($r['current'] ?? 0);
                            $wip = (int)($r['wip'] ?? 0);
                          ?>
                          <tr class="<?= $isActive ? 'row-active' : '' ?>">
                            <td class="col-time sticky-1"><?= esc($r['time_label'] ?? '-') ?></td>

                            <?php if ($i === 0): ?>
                              <td class="col-part sticky-2" rowspan="<?= $rowspan ?>">
                                <div class="prod-title"><?= esc($p['part_no'] ?? '-') ?></div>
                                <div class="prod-sub"><?= esc($p['part_name'] ?? '') ?></div>
                              </td>

                              <td class="col-sch sticky-3" rowspan="<?= $rowspan ?>">
                                <div class="sch-box">
                                  <div class="sch-line"><span>hour</span><span><?= number_format((int)($p['target_per_hour'] ?? 0)) ?></span></div>
                                  <div class="sch-line"><span>shift</span><span><?= number_format((int)($p['target_per_shift'] ?? 0)) ?></span></div>
                                </div>
                              </td>
                            <?php endif; ?>

                            <td><?= number_format($cur) ?></td>
                            <td><?= number_format($wip) ?></td>

                            <?php if ($i === 0): ?>
                              <td class="stock-cell" rowspan="<?= $rowspan ?>">
                                <div class="stock-box">
                                  <div class="stock-line">
                                    <span>total</span>
                                    <span><?= number_format($stockTotal) ?></span>
                                  </div>
                                </div>
                              </td>

                              <!-- ✅ TRANSFER rowspan juga -->
                              <td class="transfer-cell" rowspan="<?= $rowspan ?>">
                                <?= number_format($transferTotal) ?>
                              </td>
                            <?php endif; ?>
                          </tr>
                        <?php endforeach; ?>

                        <tr class="spacer-row"><td colspan="999"></td></tr>
                      <?php endforeach; ?>

                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?= $this->endSection() ?>
