<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
  :root{
    --w-no: 56px;
    --w-prod: 320px;
    --w-metric: 80px;     /* In / Out */
    --w-stock: 90px;      /* Stock */
  }

  .wip-wrap{
    overflow:auto;
    border:1px solid #dee2e6;
    border-radius:10px;
    background:#fff;
  }

  .wip-table{
    width: max-content;          /* biar bisa melebar mengikuti kolom */
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;         /* penting untuk kerapian */
  }

  .wip-table th, .wip-table td{
    padding: 6px 8px;
    border-right: 1px solid #dee2e6;
    border-bottom: 1px solid #dee2e6;
    vertical-align: middle;
    white-space: nowrap;
  }

  .wip-table thead th{
    position: sticky;
    top: 0;
    z-index: 30;
    border-bottom: 2px solid #adb5bd;
  }

  /* header styling */
  .thead-top th{
    background: #ffffff; /* kuning */
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .2px;
  }
  .thead-sub th{
    background: #f1f3f5; /* abu */
    font-weight: 700;
  }

  /* sticky columns */
  .sticky-col-1{
    position: sticky;
    left: 0;
    z-index: 40;
    background: #fff;
  }
  .sticky-col-2{
    position: sticky;
    left: var(--w-no);
    z-index: 40;
    background: #fff;
  }

  /* sticky header cells should stay above body sticky */
  thead .sticky-col-1,
  thead .sticky-col-2{
    z-index: 60;
  }

  /* add subtle shadow so sticky area looks separated */
  .sticky-shadow{
    box-shadow: 2px 0 0 rgba(0,0,0,.06);
  }

  /* stock column styling */
  .col-stock{
    background: #fff3a6;
    font-weight: 800;
  }

  /* product not in flow */
  .cell-disabled{
    background:#f1f3f5 !important;
    color:#adb5bd;
  }

  /* widths (follow colgroup too) */
  .col-no{ width: var(--w-no); }
  .col-product{ width: var(--w-prod); }
  .col-metric{ width: var(--w-metric); }
  .col-stock-w{ width: var(--w-stock); }

  /* alignment */
  .text-left{ text-align:left; }
  .text-center{ text-align:center; }

  /* nicer product cell */
  .prod-title{ font-weight: 800; line-height: 1.05; }
  .prod-sub{ font-size: 12px; color:#6c757d; line-height: 1.1; }

  /* outer borders */
  .wip-table tr > *:first-child{ border-left: 1px solid #dee2e6; }
  .wip-table thead tr:first-child th{ border-top: 1px solid #dee2e6; }
</style>

<h4 class="mb-3">
  <i class="bi bi-box-seam me-2"></i>
  WIP Process
</h4>

<form method="get" class="row g-2 mb-3 align-items-end" style="max-width:520px">
  <div class="col-md-7">
    <label class="form-label fw-bold">Tanggal</label>
    <input type="date" name="date" value="<?= esc($date) ?>" class="form-control">
  </div>
  <div class="col-md-5">
    <button class="btn btn-primary w-100">
      <i class="bi bi-funnel"></i> Filter
    </button>
  </div>
</form>

<?php if (empty($rows)): ?>
  <div class="alert alert-warning">Tidak ada data WIP untuk tanggal ini.</div>
<?php else: ?>

<div class="wip-wrap">
  <table class="wip-table mb-0">
    <!-- COLGROUP: bikin lebar kolom benar-benar konsisten -->
    <colgroup>
      <col style="width: var(--w-no)">
      <col style="width: var(--w-prod)">
      <?php foreach (($processes ?? []) as $p): ?>
        <col style="width: var(--w-metric)">
        <col style="width: var(--w-metric)">
        <col style="width: var(--w-stock)">
      <?php endforeach; ?>
    </colgroup>

    <thead>
      <tr class="thead-top">
        <th class="sticky-col-1 col-no text-center sticky-shadow">No</th>
        <th class="sticky-col-2 col-product text-left sticky-shadow">Product</th>

        <?php foreach (($processes ?? []) as $p): ?>
          <th class="text-center" colspan="3"><?= esc($p['label']) ?></th>
        <?php endforeach; ?>
      </tr>

      <tr class="thead-sub">
        <th class="sticky-col-1 col-no sticky-shadow"></th>
        <th class="sticky-col-2 col-product sticky-shadow"></th>

        <?php foreach (($processes ?? []) as $p): ?>
          <th class="text-center col-metric">In</th>
          <th class="text-center col-metric">Out</th>
          <th class="text-center col-stock col-stock-w">Stock</th>
        <?php endforeach; ?>
      </tr>
    </thead>

    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="sticky-col-1 col-no text-center sticky-shadow"><?= (int)$r['no'] ?></td>

          <td class="sticky-col-2 col-product text-left sticky-shadow">
            <div class="prod-title"><?= esc($r['part_no'] ?? '-') ?></div>
            <div class="prod-sub"><?= esc($r['part_name'] ?? '-') ?></div>
          </td>

          <?php foreach (($processes ?? []) as $p): ?>
            <?php
              $label = $p['label'];
              $cell  = $r['cells'][$label] ?? ['in'=>0,'out'=>0,'stock'=>0,'enabled'=>false];

              $enabled = !empty($cell['enabled']);
              $in    = (int)($cell['in'] ?? 0);
              $out   = (int)($cell['out'] ?? 0);
              $stock = (int)($cell['stock'] ?? 0);

              $cls = $enabled ? '' : 'cell-disabled';
            ?>

            <td class="text-center col-metric <?= $cls ?>">
              <?= $enabled ? number_format($in) : '0' ?>
            </td>

            <td class="text-center col-metric <?= $cls ?>">
              <?= $enabled ? number_format($out) : '0' ?>
            </td>

            <td class="text-center col-stock col-stock-w <?= $enabled ? '' : 'cell-disabled' ?>">
              <?= $enabled ? number_format($stock) : '0' ?>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php endif; ?>

<?= $this->endSection() ?>
