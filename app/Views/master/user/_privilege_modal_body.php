<?php
  $isAdmin = (($user['role'] ?? '') === 'ADMIN');
  $accessOptions = ['ALL' => 'Semua', 'SECTION' => 'Section', 'OWN' => 'Own'];

  // indent style
  function indentStyle(int $level): string {
    // level 0 = root, 1 = child, 2 = grandchild
    $pad = 8 + ($level * 18);
    return "padding-left: {$pad}px;";
  }

  function levelLabel(int $level): string {
    if ($level === 0) return 'fw-semibold';
    if ($level === 1) return '';
    return 'text-muted';
  }
?>

<?php if ($isAdmin): ?>
  <div class="alert alert-info mb-0">
    Role <b>ADMIN</b> memiliki <b>All Access</b>. Privilege tidak perlu diatur.
  </div>
<?php else: ?>

<form method="post" action="<?= site_url('master/user/'.$user['id'].'/privilege') ?>">
  <?= csrf_field() ?>

  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="fw-semibold">
      Privilege untuk: <?= esc($user['fullname'] ?? $user['username']) ?>
    </div>

    <div class="d-flex gap-2">
      <button type="button" class="btn btn-sm btn-outline-primary" onclick="checkAllPriv(true)">Check all</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="checkAllPriv(false)">Uncheck all</button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered table-sm align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:80px;">No.</th>
          <th>Menu</th>
          <th class="text-center" style="width:90px;">Read</th>
          <th class="text-center" style="width:90px;">Create</th>
          <th class="text-center" style="width:90px;">Update</th>
          <th class="text-center" style="width:90px;">Delete</th>
          <th style="width:160px;">Data Access</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($menuRows ?? [])): ?>
          <tr>
            <td colspan="7" class="text-center text-muted py-4">Menu belum tersedia</td>
          </tr>
        <?php else: ?>
          <?php foreach ($menuRows as $row): ?>
            <?php
              $m   = $row['menu'];
              $mid = (int)$m['id'];
              $lvl = (int)$row['level'];

              $p = $privMap[$mid] ?? null;
              $canRead   = (int)($p['can_read'] ?? 0) === 1;
              $canCreate = (int)($p['can_create'] ?? 0) === 1;
              $canUpdate = (int)($p['can_update'] ?? 0) === 1;
              $canDelete = (int)($p['can_delete'] ?? 0) === 1;
              $dataAccess= (string)($p['data_access'] ?? 'ALL');

              // optional: root row lebih “header-like”
              $rowClass = $lvl === 0 ? 'table-light' : '';
            ?>
            <tr class="<?= $rowClass ?>">
              <td><?= esc($row['number']) ?></td>

              <td style="<?= indentStyle($lvl) ?>">
                <div class="<?= levelLabel($lvl) ?>">
                  <?= esc($m['name'] ?? '-') ?>
                </div>
                <?php if (!empty($m['route'])): ?>
                  <small class="text-muted"><?= esc($m['route']) ?></small>
                <?php endif; ?>
                <input type="hidden" name="menu_id[]" value="<?= esc($mid, 'attr') ?>">
              </td>

              <td class="text-center">
                <input class="form-check-input priv-cb" type="checkbox" name="can_read[<?= $mid ?>]" <?= $canRead ? 'checked' : '' ?>>
              </td>
              <td class="text-center">
                <input class="form-check-input priv-cb" type="checkbox" name="can_create[<?= $mid ?>]" <?= $canCreate ? 'checked' : '' ?>>
              </td>
              <td class="text-center">
                <input class="form-check-input priv-cb" type="checkbox" name="can_update[<?= $mid ?>]" <?= $canUpdate ? 'checked' : '' ?>>
              </td>
              <td class="text-center">
                <input class="form-check-input priv-cb" type="checkbox" name="can_delete[<?= $mid ?>]" <?= $canDelete ? 'checked' : '' ?>>
              </td>

              <td>
                <select class="form-select form-select-sm" name="data_access[<?= $mid ?>]">
                  <?php foreach ($accessOptions as $key => $label): ?>
                    <option value="<?= esc($key, 'attr') ?>" <?= $dataAccess === $key ? 'selected' : '' ?>>
                      <?= esc($label) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="modal-footer d-flex justify-content-end gap-2">
    <button type="submit" class="btn btn-primary">
      <i class="bi bi-save"></i> Simpan Privilege
    </button>
  </div>
</form>

<script>
  function checkAllPriv(checked){
    document.querySelectorAll('.priv-cb').forEach(cb => cb.checked = checked);
  }
</script>

<?php endif; ?>
