<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Tambah Customer</h4>

<form method="post" action="/master/customer/store">
    <div class="mb-3">
        <label>Customer Code</label>
        <input type="text" name="customer_code" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>Customer Name</label>
        <input type="text" name="customer_name" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>Address</label>
        <textarea name="address" class="form-control"></textarea>
    </div>

    <div class="mb-3">
        <label>PIC</label>
        <input type="text" name="pic" class="form-control">
    </div>

    <div class="mb-3">
        <label>Phone</label>
        <input type="text" name="phone" class="form-control">
    </div>

    <div class="mb-3">
        <label>Email</label>
        <input type="email" name="email" class="form-control">
    </div>

    <div class="mb-3">
        <label>Notes</label>
        <textarea name="notes" class="form-control"></textarea>
    </div>

    <button class="btn btn-primary">Simpan</button>
    <a href="/master/customer" class="btn btn-secondary">Kembali</a>
</form>

<?= $this->endSection() ?>
