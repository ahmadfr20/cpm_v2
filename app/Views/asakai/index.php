<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h3 class="mb-3">
    <i class="bi bi-sunrise me-2"></i> ASAKAI – Daily Production Summary
</h3>

<form method="get" class="mb-3">
    <input type="date"
           name="date"
           value="<?= esc($date) ?>"
           class="form-control w-auto">
</form>

<table class="table table-bordered text-center align-middle">
<thead class="table-light">
<tr>
    <th>Section</th>
    <th>Target</th>
    <th>Actual (FG)</th>
    <th>Efficiency</th>
    <th>Note</th>
</tr>
</thead>

<tbody>

<?php
function renderRow($label, $data) {
    $eff = $data['eff'] ?? 0;

    if ($eff >= 95) {
        $class = 'table-success';
        $note  = 'Normal';
    } elseif ($eff >= 80) {
        $class = 'table-warning';
        $note  = 'Recovery';
    } else {
        $class = 'table-danger';
        $note  = 'Abnormal';
    }
?>
<tr>
    <td><b><?= $label ?></b></td>
    <td><?= $data['target'] ?? 0 ?></td>
    <td><?= $data['fg'] ?? 0 ?></td>
    <td class="<?= $class ?>">
        <?= $eff ?> %
    </td>
    <td><?= $note ?></td>
</tr>
<?php } ?>

<?php renderRow('Die Casting', $dieCasting) ?>
<?php renderRow('Machining', $machining) ?>

</tbody>
</table>

<?= $this->endSection() ?>
