<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Inventory</h4>

<table class="table table-bordered">
<thead>
<tr>
    <th>Part</th>
    <th>Raw Stock</th>
    <th>Finish Good</th>
</tr>
</thead>
<tbody>
<?php foreach ($inventory as $r): ?>
<tr>
    <td><?= $r['part_no'] ?> - <?= $r['part_name'] ?></td>
    <td><?= $r['raw_stock'] ?></td>
    <td><?= $r['fg_stock'] ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table>

<?= $this->endSection() ?>
