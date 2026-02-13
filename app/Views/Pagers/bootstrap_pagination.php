<?php
/** @var \CodeIgniter\Pager\PagerRenderer $pager */

$links = $pager->links(); // array: title, uri, active
if (empty($links)) {
    return;
}

$pageCount = count($links);
if ($pageCount <= 1) {
    return;
}

// cari current page dari links (yang active)
$current = 1;
foreach ($links as $idx => $lnk) {
    if (!empty($lnk['active'])) {
        $current = $idx + 1; // karena idx mulai 0
        break;
    }
}

// tampilkan angka sekitar current (2 kiri & 2 kanan)
$side  = 2;
$start = max(1, $current - $side);
$end   = min($pageCount, $current + $side);

// ambil uri dari page tertentu (1-based)
$getUri = function(int $page) use ($links) {
    return $links[$page - 1]['uri'] ?? '#';
};
?>

<nav aria-label="Pagination">
  <ul class="pagination pagination-sm justify-content-end mb-0">

    <!-- First & Prev -->
    <li class="page-item <?= $current > 1 ? '' : 'disabled' ?>">
      <a class="page-link" href="<?= $current > 1 ? $getUri(1) : '#' ?>" aria-label="First">&laquo;</a>
    </li>
    <li class="page-item <?= $current > 1 ? '' : 'disabled' ?>">
      <a class="page-link" href="<?= $current > 1 ? $getUri($current - 1) : '#' ?>" aria-label="Previous">&lsaquo;</a>
    </li>

    <!-- 1 ... -->
    <?php if ($start > 1): ?>
      <li class="page-item">
        <a class="page-link" href="<?= $getUri(1) ?>">1</a>
      </li>
      <?php if ($start > 2): ?>
        <li class="page-item disabled"><span class="page-link">…</span></li>
      <?php endif; ?>
    <?php endif; ?>

    <!-- middle pages -->
    <?php for ($i = $start; $i <= $end; $i++): ?>
      <li class="page-item <?= $i === $current ? 'active' : '' ?>">
        <a class="page-link" href="<?= $getUri($i) ?>"><?= $i ?></a>
      </li>
    <?php endfor; ?>

    <!-- ... last -->
    <?php if ($end < $pageCount): ?>
      <?php if ($end < $pageCount - 1): ?>
        <li class="page-item disabled"><span class="page-link">…</span></li>
      <?php endif; ?>
      <li class="page-item">
        <a class="page-link" href="<?= $getUri($pageCount) ?>"><?= $pageCount ?></a>
      </li>
    <?php endif; ?>

    <!-- Next & Last -->
    <li class="page-item <?= $current < $pageCount ? '' : 'disabled' ?>">
      <a class="page-link" href="<?= $current < $pageCount ? $getUri($current + 1) : '#' ?>" aria-label="Next">&rsaquo;</a>
    </li>
    <li class="page-item <?= $current < $pageCount ? '' : 'disabled' ?>">
      <a class="page-link" href="<?= $current < $pageCount ? $getUri($pageCount) : '#' ?>" aria-label="Last">&raquo;</a>
    </li>

  </ul>
</nav>
