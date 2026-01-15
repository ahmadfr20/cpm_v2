<?php if ($pager->getPageCount() > 0): ?>
<nav>
    <ul class="pagination pagination-sm justify-content-end">
        <?php foreach ($pager->links() as $link): ?>
            <li class="page-item <?= $link['active'] ? 'active' : '' ?>">
                <a class="page-link" href="<?= $link['uri'] ?>">
                    <?= $link['title'] ?>
                </a>
            </li>
        <?php endforeach ?>
    </ul>
</nav>
<?php endif ?>
