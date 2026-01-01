<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>WIP</h4>

<table class="table table-bordered">
<thead>
<tr>
    <th>Part</th>
    <th>WIP Shot Blast</th>
    <th>WIP Machining</th>
</tr>
</thead>
<tbody>
<?php foreach ($wip as $r): ?>
<tr>
    <td><?= $r['part_no'] ?> - <?= $r['part_name'] ?></td>
    <td><?= $r['wip_shotblast'] ?></td>
    <td><?= $r['wip_machining'] ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table>

<?= $this->endSection() ?>
