<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CPM - Shop Floor</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
    body {
        overflow-x: hidden;
        background-color: #f8fafc;
    }

    .sidebar {
        width: 260px;
        min-height: 100vh;
        background-color: #1e293b;
        transition: all .3s;
    }

    .sidebar .nav-link {
        color: #cbd5e1;
        border-radius: 6px;
        padding: 10px 14px;
        margin-bottom: 6px;

        /* BORDER BARU */
        border: 1px solid #334155;
        background-color: transparent;
    }

    .sidebar .nav-link:hover {
        background-color: #334155;
        color: #ffffff;
        border-color: #475569;
    }

    .sidebar .nav-link.active {
        background-color: #475569;
        color: #ffffff;
        border-color: #64748b;
        font-weight: 500;
    }

    .sidebar .nav-item.small {
        color: #94a3b8;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: .08em;
        margin-top: 12px;
        margin-bottom: 6px;
    }

    @media (max-width: 768px) {
        .sidebar {
            position: fixed;
            left: -260px;
            z-index: 1050;
        }
        .sidebar.show {
            left: 0;
        }
    }

    .sidebar .nav-link {
        font-size: 14px;
    }

    .sidebar .nav-link i.bi-chevron-down {
        font-size: 12px;
        opacity: 0.7;
    }

    .sidebar .collapse .nav-link {
        padding: 8px 12px;
        font-size: 13px;
    }

    /* Sidebar spacing compact */
#sidebar .nav-link {
    padding: 6px 10px;   /* default bootstrap: 8px 16px */
    font-size: 13.5px;
}

#sidebar .nav-item {
    margin-bottom: 2px;
}

/* Submenu lebih rapat */
#sidebar .collapse .nav-link {
    padding: 5px 10px;
    font-size: 13px;
}

/* Section title spacing */
#sidebar .nav-item.mt-3 {
    margin-top: 10px !important;
}

</style>

</head>

<body>

<?= $this->include('layout/header') ?>

<div class="d-flex">
    <?= $this->include('layout/sidebar') ?>

    <main class="flex-fill p-3">
        <?= $this->renderSection('content') ?>
    </main>
</div>

<?= $this->include('layout/footer') ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('show');
    }
</script>

</body>
</html>
