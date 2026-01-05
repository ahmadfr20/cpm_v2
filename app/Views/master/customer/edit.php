<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<h4>Edit Customer</h4>

<form method="post" action="/master/customer/update/<?= $customer['id'] ?>">

<div class="mb-3">
    <label>Customer Code</label>
    <input type="text" name="customer_code"
           value="<?= esc($customer['customer_code']) ?>"
           class="form-control" required>
</div>

<div class="mb-3">
    <label>Customer Name</label>
    <input type="text" name="customer_name"
           value="<?= esc($customer['customer_name']) ?>"
           class="form-control" required>
</div>

<div class="mb-3">
    <label>Address</label>
    <textarea name="address"
              class="form-control"
              rows="3"><?= esc($customer['address']) ?></textarea>
</div>

<div class="mb-3">
    <label>Phone</label>
    <input type="text" name="phone"
           value="<?= esc($customer['phone']) ?>"
           class="form-control">
</div>

<div class="mb-3">
    <label>Email</label>
    <input type="email" name="email"
           value="<?= esc($customer['email']) ?>"
           class="form-control">
</div>

<button class="btn btn-primary">Update</button>
<a href="/master/customer" class="btn btn-secondary">Kembali</a>

</form>

<?= $this->endSection() ?>
