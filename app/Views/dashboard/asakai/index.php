<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>ASAKAI</h4>

<form class="row g-2 mb-3">
    <div class="col-auto">
        <input type="date" name="date" value="<?= $date ?>" class="form-control">
    </div>
    <div class="col-auto">
        <button class="btn btn-primary">Filter</button>
    </div>
</form>

<table class="table table-bordered">
<thead class="table-light">
<tr>
    <th>Section</th>
    <th>Target</th>
    <th>Actual</th>
    <th>Achievement</th>
    <th>Note</th>
</tr>
</thead>
<tbody>

<tr>
    <td><b>Die Casting</b></td>
    <td><?= $casting['plan'] ?></td>
    <td><?= $casting['actual'] ?></td>
    <td class="<?= $casting['eff'] >= 100 ? 'table-success' : 'table-warning' ?>">
        <?= $casting['eff'] ?> %
    </td>
    <td>Achievement Casting <?= $casting['eff'] ?>%</td>
</tr>

<tr>
    <td><b>Machining</b></td>
    <td><?= $machining['plan'] ?></td>
    <td><?= $machining['actual'] ?></td>
    <td class="<?= $machining['eff'] >= 100 ? 'table-success' : 'table-warning' ?>">
        <?= $machining['eff'] ?> %
    </td>
    <td>Achievement Machining <?= $machining['eff'] ?>%</td>
</tr>

</tbody>
</table>

<?= $this->endSection() ?>
