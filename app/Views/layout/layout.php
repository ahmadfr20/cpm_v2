<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CPM - Shop Floor</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
/* ================= RESET ================= */
html, body {
    height: 100%;
    margin: 0;
}

body {
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
    background: #f8fafc;
    overflow: hidden;
}

/* ================= HEADER ================= */
.app-header {
    height: 56px;
    background: #111827;
    color: #fff;
    display: flex;
    align-items: center;
    padding: 0 16px;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1100;
}

/* ================= SIDEBAR ================= */
.sidebar {
    position: fixed;
    top: 56px;
    left: 0;
    bottom: 0;
    width: 260px;
    background: #1e293b;
    color: #cbd5e1;
    overflow-y: auto;
    transform: translateX(-100%);
    transition: transform .25s ease;
    z-index: 1200;
}

body.sidebar-open .sidebar {
    transform: translateX(0);
}

.sidebar .nav-link {
    color: #cbd5e1;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 14px;
}

.sidebar .nav-link:hover {
    background: #334155;
    color: #fff;
}

.sidebar .nav-link.active {
    background: #475569;
    color: #fff;
    font-weight: 500;
}

.nav-section {
    margin: 16px 0 6px;
    font-size: 11px;
    text-transform: uppercase;
    color: #94a3b8;
}

.nav-sub {
    padding-left: 12px;
}

/* ================= SIDEBAR ================= */
.sidebar-inner {
    padding: 12px;
}

.sidebar-nav .nav-link {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #cbd5e1;
    font-size: 14px;
    padding: 8px 10px;
    border-radius: 6px;
}

.sidebar-nav .nav-link i {
    width: 18px;
    text-align: center;
    font-size: 15px;
}

.sidebar-nav .nav-link:hover {
    background-color: #334155;
    color: #fff;
}

.sidebar-nav .nav-link.active {
    background-color: #475569;
    color: #fff;
    font-weight: 500;
}

.nav-collapse {
    justify-content: space-between;
}

.nav-collapse .caret {
    font-size: 12px;
    opacity: .8;
}

.nav-sub {
    margin-top: 6px;
    padding-left: 18px;
}

.nav-sub .nav-link {
    font-size: 13px;
    padding: 6px 10px;
}

.nav-section {
    margin: 18px 0 6px;
    font-size: 11px;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #94a3b8;
}

.nav-subtitle {
    margin: 10px 0 4px;
    font-size: 11px;
    color: #94a3b8;
}



/* ================= OVERLAY ================= */
.sidebar-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.4);
    z-index: 1100;
    display: none;
}

body.sidebar-open .sidebar-overlay {
    display: block;
}

/* ================= MAIN CONTENT ================= */
.main-content {
    padding: 72px 16px 16px;
    height: 100vh;
    overflow-y: auto;
    overflow-x: hidden;
}

/* ================= TABLE SCROLL ================= */
.table-scroll {
    width: 100%;
    overflow-x: auto;
}

.production-table {
    min-width: 2600px;
    table-layout: fixed;
}

/* ================= TOGGLE BUTTON ================= */
#toggleSidebarBtn {
    background: transparent;
    border: none;
    color: #fff;
    font-size: 20px;
    margin-right: 12px;
}
</style>
</head>

<body>

<!-- HEADER -->
<header class="app-header">
    <button id="toggleSidebarBtn" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>

    <span class="fw-semibold">CPM Shop Floor</span>

    <div class="ms-auto d-flex align-items-center">
        <span class="me-3 small">
            <?= session()->get('fullname') ?> (<?= session()->get('role') ?>)
        </span>
        <a href="/logout" class="btn btn-sm btn-outline-light">Logout</a>
    </div>
</header>

<!-- SIDEBAR -->
<aside class="sidebar">
    <?= $this->include('layout/sidebar') ?>
</aside>

<!-- OVERLAY -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- MAIN -->
<main class="main-content">
    <?= $this->renderSection('content') ?>
</main>

<footer class="text-center text-muted small py-2">
    CPM Shop Floor © <?= date('Y') ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
function toggleSidebar() {
    document.body.classList.toggle('sidebar-open');

    const icon = document.querySelector('#toggleSidebarBtn i');
    icon.className = document.body.classList.contains('sidebar-open')
        ? 'bi bi-x-lg'
        : 'bi bi-list';
}
</script>

</body>
</html>
