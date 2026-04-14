<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CPM - Shop Floor</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

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

/* ================= PRINT MEDIA ================= */
@media print {
    body { background: #fff !important; overflow: visible !important; }
    .app-header, .sidebar, .sidebar-overlay, footer, .d-print-none, .btn, button, input[type="submit"], form[method="get"] { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; height: auto !important; overflow: visible !important; }
    .card { border: 1px solid #ccc !important; box-shadow: none !important; margin-bottom: 2rem !important; break-inside: avoid; }
    .table-responsive { overflow: visible !important; }
    .table { width: 100% !important; min-width: 0 !important; border-collapse: collapse !important; }
    
    /* Make inputs look like text */
    input[type="number"], input[type="text"], input[type="date"] { border: none !important; background: transparent !important; padding: 0 !important; width: 100% !important; }
    select { appearance: none !important; border: none !important; background: transparent !important; }
    .select2-container--default .select2-selection--single { border: none !important; background: transparent !important; }
    .select2-selection__arrow { display: none !important; }
    .form-control { border: none !important; }
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

// Global Export Function for Schedule & Hourly Pages
// The buttons are now added manually per page by user request.
function exportGenericExcel(filename = "Data_Export.xlsx") {
    if(typeof XLSX === 'undefined') {
        alert("Library Excel belum siap. Silahkan tunggu...");
        return;
    }
    
    const tables = document.querySelectorAll('.main-content table');
    if(tables.length === 0) {
        alert("Tidak ada tabel data untuk diexport!");
        return;
    }
    
    const wb = XLSX.utils.book_new();
    let sheetCounter = 1;
    
    tables.forEach(table => {
        // Abaikan tabel mini seperti ng-mini-table
        if(table.classList.contains('ng-mini-table')) return;
        
        const rows = table.querySelectorAll('tr');
        const data = [];
        
        rows.forEach(tr => {
            const rowData = [];
            const cols = tr.querySelectorAll('th, td');
            cols.forEach(cell => {
                if(cell.classList.contains('d-none')) return;
                
                let val = "";
                const selects = cell.querySelectorAll('select:not(.d-none)');
                const inputs = cell.querySelectorAll('input:not([type="hidden"]):not(.d-none):not([type="checkbox"])');
                const checks = cell.querySelectorAll('input[type="checkbox"]:not(.d-none)');
                const spans = cell.querySelectorAll('span:not(.d-none)');
                
                if (selects.length > 0) {
                    const parts = [];
                    selects.forEach(s => {
                        if(s.selectedIndex >= 0 && s.options[s.selectedIndex].text !== "-- pilih --" && s.options[s.selectedIndex].value !== "") {
                            parts.push(s.options[s.selectedIndex].text);
                        }
                    });
                    val = parts.join(" | ");
                } else if (inputs.length > 0) {
                    const parts = [];
                    inputs.forEach(inp => parts.push(inp.value));
                    val = parts.join(" | ");
                } else if (checks.length > 0) {
                    const parts = [];
                    checks.forEach(chk => { if(chk.checked) parts.push("Ya"); });
                    val = parts.join(" | ") || "";
                } else {
                    // special fallback
                    // remove small buttons texts
                    let clone = cell.cloneNode(true);
                    clone.querySelectorAll('button, .btn, .d-none').forEach(e => e.remove());
                    val = clone.innerText.trim();
                }
                
                rowData.push(val);
            });
            
            if(rowData.length > 0 && rowData.some(v => v !== "")) {
                data.push(rowData);
            }
        });
        
        if (data.length > 0) {
            let sheetName = "Sheet" + sheetCounter;
            const parentCard = table.closest('.card');
            if(parentCard && parentCard.querySelector('.card-header')) {
                const headerText = parentCard.querySelector('.card-header').innerText.trim().replace(/[^a-zA-Z0-9 ]/g, '').substring(0, 30);
                if(headerText) sheetName = headerText;
            } else if (table.id) {
                sheetName = table.id.substring(0,30);
            }
            
            if(wb.SheetNames.includes(sheetName)) {
                sheetName = sheetName + " " + sheetCounter;
            }
            
            const ws = XLSX.utils.aoa_to_sheet(data);
            XLSX.utils.book_append_sheet(wb, ws, sheetName);
            sheetCounter++;
        }
    });
    
    if(wb.SheetNames.length > 0) {
        XLSX.writeFile(wb, filename);
    } else {
        alert("Tidak ada data untuk diexport!");
    }
}
</script>

</body>
</html>
