<select name="customer_id" class="form-control" required>
    <?php foreach ($customers as $c): ?>
    <option value="<?= $c['id'] ?>"><?= $c['customer_name'] ?></option>
    <?php endforeach; ?>
</select>
