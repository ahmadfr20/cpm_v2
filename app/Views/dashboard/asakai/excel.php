<?php
// File ini akan dibaca oleh browser sebagai Excel
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 12px; }
        th, td { border: 1px solid #000000; padding: 5px; text-align: center; vertical-align: middle; }
        th { background-color: #d9e1f2; font-weight: bold; }
        .section-name { text-align: center; font-weight: bold; background-color: #fcfcfc;}
        .part-name { text-align: left; }
        .total-col { background-color: #fff2cc; font-weight: bold; }
        .bg-target { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2>ASAKAI - Daily Production Summary</h2>
    <p><strong>Tanggal :</strong> <?= esc($date) ?></p>
    <p><strong>Section :</strong> <?= $selectedSec === '' ? 'Semua Section' : esc($selectedSec) ?></p>
    
    <br>

    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width: 150px;">Section / Process</th>
                <th rowspan="2" style="width: 250px;">Product (Part No & Name)</th>
                <?php foreach ($shifts as $shift): ?>
                    <th colspan="3"><?= esc($shift['shift_name']) ?></th>
                <?php endforeach; ?>
                <th colspan="3" class="total-col">TOTAL HARIAN</th>
            </tr>
            <tr>
                <?php foreach ($shifts as $shift): ?>
                    <th class="bg-target">Target</th>
                    <th>Actual</th>
                    <th>Eff (%)</th>
                <?php endforeach; ?>
                <th class="total-col">Target</th>
                <th class="total-col">Actual</th>
                <th class="total-col">Total Eff (%)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($summaryData)): ?>
                <tr>
                    <td colspan="<?= (count($shifts) * 3) + 5 ?>">Tidak ada data produksi yang ditemukan.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($summaryData as $sectionName => $products): ?>
                    <?php 
                        $rowspan = count($products) > 0 ? count($products) : 1; 
                        $firstRow = true;
                    ?>
                    
                    <?php if(empty($products)): ?>
                        <tr>
                            <td class="section-name"><?= esc($sectionName) ?></td>
                            <td colspan="<?= (count($shifts) * 3) + 4 ?>">Tidak ada product di section ini hari ini</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $prod): ?>
                            <tr>
                                <?php if ($firstRow): ?>
                                    <td class="section-name" rowspan="<?= $rowspan ?>"><?= esc($sectionName) ?></td>
                                    <?php $firstRow = false; ?>
                                <?php endif; ?>

                                <td class="part-name">
                                    <strong><?= esc($prod['part_no']) ?></strong><br>
                                    <small><?= esc($prod['part_name']) ?></small>
                                </td>
                                
                                <?php foreach ($shifts as $shift): ?>
                                    <?php 
                                        $shiftId = $shift['id'];
                                        $sData   = $prod['shifts'][$shiftId] ?? ['target' => 0, 'fg' => 0, 'eff' => 0];
                                    ?>
                                    <td class="bg-target"><?= $sData['target'] ?></td>
                                    <td><?= $sData['fg'] ?></td>
                                    <td><?= $sData['eff'] ?></td>
                                <?php endforeach; ?>

                                <td class="total-col"><?= $prod['total_target'] ?></td>
                                <td class="total-col"><?= $prod['total_fg'] ?></td>
                                <td class="total-col"><?= $prod['total_eff'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>