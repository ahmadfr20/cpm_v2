<h5>WIP</h5>

<table class="table table-bordered table-sm">
<thead class="table-dark">
<tr>
    <th>Part No</th>
    <th>Part Name</th>
    <th>WIP Shot Blast</th>
    <th>WIP Machining</th>
</tr>
</thead>
<tbody>
<?php foreach ($wip as $row): ?>
<tr>
    <td><?= $row['part_no'] ?></td>
    <td><?= $row['part_name'] ?></td>
    <td><?= max(0,$row['wip_shotblast']) ?></td>
    <td><?= max(0,$row['wip_machining']) ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table>
