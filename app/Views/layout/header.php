<nav class="navbar navbar-dark bg-dark px-3">
    <button class="btn btn-outline-light d-md-none" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>

    <span class="navbar-brand ms-2">CPM Shop Floor</span>

    <div class="ms-auto d-flex align-items-center text-white">
        <span class="me-3">
            <?= session()->get('fullname') ?> (<?= session()->get('role') ?>)
        </span>
        <a href="/logout" class="btn btn-sm btn-outline-light">
            Logout
        </a>
    </div>
</nav>
