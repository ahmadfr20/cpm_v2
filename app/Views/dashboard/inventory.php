<h5>Inventory</h5>

<table class="table table-bordered table-sm">
<thead class="table-dark">
<tr>
    <th>Part No</th>
    <th>Part Name</th>
    <th>Raw Material Stock</th>
    <th>Finish Good Stock</th>
</tr>
</thead>
<tbody>
<?php foreach ($inventory as $row): ?>
<tr>
    <td><?= $row['part_no'] ?></td>
    <td><?= $row['part_name'] ?></td>
    <td><?= $row['raw_stock'] ?></td>
    <td><?= $row['fg_stock'] ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table>
