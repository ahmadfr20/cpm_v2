<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Edit Customer</h4>

<form method="post" action="/master/customer/update/<?= $customer['id'] ?>">
    <div class="mb-3">
        <label>Customer Code</label>
        <input type="text" name="customer_code" class="form-control"
               value="<?= esc($customer['customer_code']) ?>" required>
    </div>

    <div class="mb-3">
        <label>Customer Name</label>
        <input type="text" name="customer_name" class="form-control"
               value="<?= esc($customer['customer_name']) ?>" required>
    </div>

    <div class="mb-3">
        <label>Address</label>
        <textarea name="address" class="form-control"><?= esc($customer['address']) ?></textarea>
    </div>

    <div class="mb-3">
        <label>PIC</label>
        <input type="text" name="pic" class="form-control"
               value="<?= esc($customer['pic']) ?>">
    </div>

    <div class="mb-3">
        <label>Phone</label>
        <input type="text" name="phone" class="form-control"
               value="<?= esc($customer['phone']) ?>">
    </div>

    <div class="mb-3">
        <label>Email</label>
        <input type="email" name="email" class="form-control"
               value="<?= esc($customer['email']) ?>">
    </div>

    <div class="mb-3">
        <label>Notes</label>
        <textarea name="notes" class="form-control"><?= esc($customer['notes']) ?></textarea>
    </div>

    <button class="btn btn-primary">Update</button>
    <a href="/master/customer" class="btn btn-secondary">Kembali</a>
</form>

<?= $this->endSection() ?>
