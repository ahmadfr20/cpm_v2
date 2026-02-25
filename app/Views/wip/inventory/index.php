<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<style>
  :root{
    --border:#e5e7eb;
    --text:#0f172a;

    --head:#f1f5f9;
    --head-border:#cbd5e1;

    --wipakhir:#fff7cc;
    --stock:#dcfce7;

    --row-hover:#f8fafc;
  }

  .page-head{
    display:flex;
    gap:12px;
    align-items:flex-end;
    flex-wrap:wrap;
    margin-bottom:12px;
  }
  .page-head .title{
    font-weight:900;
    font-size:18px;
    letter-spacing:.2px;
    color:var(--text);
  }
  .page-head form{
    margin-left:auto;
    display:flex;
    gap:10px;
    align-items:flex-end;
  }

  .excel-wrap{
    background:#fff;
    border:1px solid var(--border);
    border-radius:12px;
    overflow:hidden;
  }

  .excel-scroll{
    overflow:auto;
    background:#fff;
  }

  table.excel{
    border-collapse:separate;
    border-spacing:0;
    width:max-content;
    min-width:1300px;
    font-size:13px;
    color:var(--text);
  }

  table.excel thead th{
    position: sticky;
    top: 0;
    z-index: 5;
    background: var(--head);
    color: #0f172a;
    font-weight: 800;
    text-align: center;
    border-bottom: 1px solid var(--head-border);
    padding: 10px 10px;
    white-space: nowrap;
  }

  table.excel th, table.excel td{
    border-right:1px solid var(--border);
    border-bottom:1px solid var(--border);
    padding:10px 10px;
    vertical-align:middle;
    white-space:nowrap;
    background:#fff;
  }
  table.excel tr > *:first-child{ border-left:1px solid var(--border); }
  table.excel thead tr:first-child > *{ border-top:1px solid var(--border); }

  table.excel tbody tr:hover td{
    background: var(--row-hover);
  }

  .num{
    text-align:right;
    font-variant-numeric: tabular-nums;
  }

  .station{
    font-weight:800;
    letter-spacing:.2px;
  }
  .part{
    font-weight:800;
    line-height:1.2;
  }
  .muted{
    color:#64748b;
    font-weight:600;
    font-size:12px;
    margin-top:2px;
    line-height:1.2;
  }

  td.wipakhir{
    background: var(--wipakhir) !important;
    font-weight:900;
  }
  td.stock{
    background: var(--stock) !important;
    font-weight:900;
  }

  tr.sep td{
    height:10px;
    padding:0 !important;
    background:#fff !important;
    border:0 !important;
  }

  /* sticky columns (DATE + SHIFT + STATION + PART) */
  .sticky-1{ position: sticky; left: 0; z-index: 4; background:#fff; }
  .sticky-2{ position: sticky; left: 90px; z-index: 4; background:#fff; }
  .sticky-3{ position: sticky; left: 200px; z-index: 4; background:#fff; }
  .sticky-4{ position: sticky; left: 370px; z-index: 4; background:#fff; }

  thead .sticky-1, thead .sticky-2, thead .sticky-3, thead .sticky-4{
    z-index: 6;
    background: var(--head) !important;
  }

  .w-date{ min-width:90px; }
  .w-shift{ min-width:110px; }
  .w-station{ min-width:170px; }
  .w-part{ min-width:360px; }
  .w-col{ min-width:130px; }
  .w-col-sm{ min-width:110px; }

  .help{
    display:inline-flex;
    gap:6px;
    align-items:center;
    justify-content:center;
  }
  .help i{ font-size:14px; opacity:.7; }
</style>

<div class="page-head">
  <div>
    <div class="title"><?= esc($titleDate) ?> – Rekap WIP</div>
  </div>

  <?php if (!empty($isAdmin) && $isAdmin): ?>
    <form method="get">
      <div>
        <label class="form-label mb-1 small">Tanggal</label>
        <input type="date" name="date" value="<?= esc($date) ?>" class="form-control form-control-sm">
      </div>
      <div>
        <button class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Filter</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<div class="excel-wrap">
  <div class="excel-scroll">
    <table class="excel">
      <thead>
        <tr>
          <th class="w-date sticky-1">Tanggal</th>
          <th class="w-shift sticky-2">Shift</th>
          <th class="w-station sticky-3">Station</th>
          <th class="w-part sticky-4">Part</th>

          <th class="w-col">Qty WIP Awal</th>

          <th class="w-col">
            <span class="help">
              Qty Masuk (In)
              <span title="Masuk = Scheduling + Transfer (masuk dari proses sebelumnya)">
                <i class="bi bi-info-circle"></i>
              </span>
            </span>
          </th>

          <th class="w-col">Qty Selesai (Out)</th>
          <th class="w-col-sm">Qty NG</th>
          <th class="w-col">Qty WIP Akhir</th>

          <th class="w-col-sm">Stock</th>
          <th class="w-col-sm">Buffer (After Process)</th>
        </tr>
      </thead>

      <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="11" class="text-center text-muted py-4">Tidak ada data untuk tanggal ini.</td>
          </tr>
        <?php else: ?>

          <?php $lastKey = null; ?>
          <?php foreach ($rows as $r): ?>
            <?php
              // pemisah jika ganti SHIFT atau Station
              $curKey = ($r['shift'] ?? '') . '|' . ($r['station'] ?? '');
              if ($lastKey !== null && $lastKey !== $curKey) {
                echo '<tr class="sep"><td colspan="11"></td></tr>';
              }
              $lastKey = $curKey;

              $dmy = date('d/m', strtotime($r['date']));
              $tipIn = "Scheduling: ".number_format((int)($r['qty_in_schedule'] ?? 0)).
                       " | Transfer: ".number_format((int)($r['qty_in_transfer'] ?? 0));
            ?>

            <tr>
              <td class="sticky-1"><?= esc($dmy) ?></td>
              <td class="sticky-2"><?= esc($r['shift'] ?? '-') ?></td>
              <td class="station sticky-3"><?= esc($r['station']) ?></td>

              <td class="sticky-4">
                <div class="part"><?= esc($r['part_no']) ?></div>
                <?php if (!empty($r['part_name'])): ?>
                  <div class="muted"><?= esc($r['part_name']) ?></div>
                <?php endif; ?>
              </td>

              <td class="num"><?= number_format((int)$r['wip_awal']) ?></td>

              <td class="num" title="<?= esc($tipIn) ?>">
                <?= number_format((int)$r['qty_in']) ?>
              </td>

              <td class="num"><?= number_format((int)$r['qty_out']) ?></td>
              <td class="num"><?= number_format((int)$r['qty_ng']) ?></td>

              <td class="num wipakhir"><?= number_format((int)$r['wip_akhir']) ?></td>
              <td class="num stock"><?= number_format((int)$r['stock']) ?></td>
              <td class="num"><?= number_format((int)$r['transfer']) ?></td>
            </tr>
          <?php endforeach; ?>

        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
