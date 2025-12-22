<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Dashboard</h4>

<form class="row g-2 mb-3">
    <div class="col-auto">
        <input type="date" name="date" value="<?= $date ?>" class="form-control">
    </div>
    <div class="col-auto">
        <button class="btn btn-primary">Filter</button>
    </div>
</form>

<?= view('dashboard/asakai', ['asakai'=>$asakai]) ?>
<hr>
<?= view('dashboard/wip', ['wip'=>$wip]) ?>
<hr>
<?= view('dashboard/inventory', ['inventory'=>$inventory]) ?>

<?= $this->endSection() ?>
