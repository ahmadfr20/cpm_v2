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
    inset: 0 0 auto 0;
    z-index: 1100;
}

/* ================= SIDEBAR ================= */
.sidebar {
    position: fixed;
    top: 28px;
    left: 0;
    bottom: 0;
    width: 260px;
    background: #1e293b;
    color: #cbd5e1;
    overflow-y: auto;
    transition: transform .25s ease;
    z-index: 1000;
}

/* desktop default open */
body.sidebar-open .sidebar {
    transform: translateX(0);
}

body:not(.sidebar-open) .sidebar {
    transform: translateX(-260px);
}

/* ================= MAIN CONTENT ================= */
.main-content {
    padding: 72px 20px 20px;
    height: 100vh;
    overflow-y: auto;
    transition: margin-left .25s ease;
}

/* sidebar open → content geser */
body.sidebar-open .main-content {
    margin-left: 260px;
}

/* sidebar close */
body:not(.sidebar-open) .main-content {
    margin-left: 0;
}

/* ================= OVERLAY (MOBILE) ================= */
.sidebar-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.4);
    z-index: 900;
    display: none;
}

/* mobile behavior */
@media (max-width: 992px) {
    body.sidebar-open .sidebar-overlay {
        display: block;
    }

    body.sidebar-open .main-content {
        margin-left: 0;
    }

    .sidebar {
        transform: translateX(-260px);
    }

    body.sidebar-open .sidebar {
        transform: translateX(0);
    }
}

/* ================= TOGGLE BUTTON ================= */
#toggleSidebarBtn {
    background: transparent;
    border: none;
    color: #fff;
    font-size: 20px;
    margin-right: 12px;
    cursor: pointer;
}

/* ================= SIDEBAR LINK COLOR FIX ================= */
.sidebar a,
.sidebar .nav-link {
    color: #cbd5e1 !important;
    text-decoration: none;
}

/* HOVER */
.sidebar .nav-link:hover {
    background-color: #334155;
    color: #ffffff !important;
}

/* ACTIVE */
.sidebar .nav-link.active {
    background-color: #475569;
    color: #ffffff !important;
    font-weight: 500;
}

/* SUB MENU (LEVEL DALAM) */
.sidebar .nav-sub .nav-link,
.sidebar ul ul .nav-link {
    color: #cbd5e1 !important;
}

/* SUB MENU ACTIVE */
.sidebar ul ul .nav-link.active {
    background-color: #475569;
    color: #ffffff !important;
}

</style>
</head>

<body class="sidebar-open"><!-- default OPEN -->

<!-- ================= HEADER ================= -->
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

<!-- ================= SIDEBAR ================= -->
<aside class="sidebar">
    <?= $this->include('layout/sidebar') ?>
</aside>

<!-- ================= OVERLAY ================= -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- ================= MAIN ================= -->
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
