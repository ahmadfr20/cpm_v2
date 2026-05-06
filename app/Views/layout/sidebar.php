<?php
$role      = session()->get('role');
$loggedIn  = (bool) session()->get('logged_in');
$currentUrl = service('uri')->getPath();

function isActive($path, $currentUrl)
{
    return str_starts_with($currentUrl, $path);
}
?>

<div id="sidebar" class="sidebar bg-dark p-3">
<ul class="nav nav-pills flex-column">

<!-- ================= PUBLIC MENU (semua orang) ================= -->
<li class="nav-item">
    <a class="nav-link <?= isActive('panduan', $currentUrl) ? 'active' : 'text-info' ?>"
       href="/panduan">
        <i class="bi bi-book-half me-2"></i> Panduan Aplikasi
    </a>
</li>

<li class="nav-item mt-2">
    <a class="nav-link <?= $currentUrl === 'dashboard' || $currentUrl === '' ? 'active' : '' ?>"
       href="/dashboard">
        <i class="bi bi-speedometer2 me-2"></i> Dashboard
    </a>
</li>

<li class="nav-item mt-2">
    <a class="nav-link <?= isActive('wip/inventory', $currentUrl) ? 'active' : '' ?>"
       href="/wip/inventory">
        <i class="bi bi-alarm me-2"></i> WIP List
    </a>
</li>

<li class="nav-item mt-2">
    <a class="nav-link <?= isActive('inventory-fg', $currentUrl) ? 'active' : 'text-white' ?>"
       href="/inventory-fg">
        <i class="bi bi-box-seam me-2 text-success"></i> Inventory FG
    </a>
</li>

<?php if ($loggedIn): ?>

<li class="nav-item mt-3">
    <a class="nav-link <?= isActive('dashboard/daily-performance', $currentUrl) ? 'active' : '' ?>"
       href="/dashboard/daily-performance">
        <i class="bi bi-graph-up-arrow me-2"></i> Daily Performance
    </a>
</li>
<li class="nav-item mt-2">
    <a class="nav-link <?= isActive('dashboard/asakai', $currentUrl) ? 'active' : '' ?>"
       href="/dashboard/asakai">
        <i class="bi bi-bar-chart-line me-2"></i> ASAKAI
    </a>
</li>
<li class="nav-item mt-2">
    <a class="nav-link <?= isActive('dashboard/dandori', $currentUrl) ? 'active' : '' ?>"
       href="/dashboard/dandori">
        <i class="bi bi-tools me-2 text-warning"></i> Dandori Report
    </a>
</li>

<!-- ================= MASTER DATA ================= -->
<?php if (in_array($role, ['ADMIN','PPIC'])): ?>
<li class="nav-item mt-3">
    <a class="nav-link text-white d-flex justify-content-between align-items-center"
       data-bs-toggle="collapse"
       href="#menuMaster">
        <span><i class="bi bi-database me-2"></i> Master Data</span>
        <i class="bi bi-chevron-down"></i>
    </a>

    <div class="collapse <?= isActive('master',$currentUrl)?'show':'' ?>" id="menuMaster">
        <ul class="nav flex-column ms-3">
            <li><a class="nav-link <?= isActive('master/user',$currentUrl)?'active':'' ?>" href="/master/user">Manage Users</a></li>
            <li><a class="nav-link <?= isActive('master/operator',$currentUrl)?'active':'' ?>" href="/master/operator">Operator</a></li>
            <li><a class="nav-link <?= isActive('master/shift',$currentUrl)?'active':'' ?>" href="/master/shift">Shift</a></li>
            <li><a class="nav-link <?= isActive('master/time-slot',$currentUrl)?'active':'' ?>" href="/master/time-slot">Time Slot</a></li>
            <li><a class="nav-link <?= isActive('master/product',$currentUrl)?'active':'' ?>" href="/master/product">Product</a></li>
            <li><a class="nav-link <?= isActive('master/machine',$currentUrl)?'active':'' ?>" href="/master/machine">Machine</a></li>
            <li><a class="nav-link <?= isActive('master/production-standard',$currentUrl)?'active':'' ?>" href="/master/production-standard">Production Standard</a></li>
            <li><a class="nav-link <?= isActive('master/production-flow',$currentUrl)?'active':'' ?>" href="/master/production-flow">Production Flow</a></li>
            <li><a class="nav-link <?= isActive('master/customer',$currentUrl)?'active':'' ?>" href="/master/customer">Customer</a></li>
            <li><a class="nav-link <?= isActive('master/vendor',$currentUrl)?'active':'' ?>" href="/master/vendor">Vendor</a></li>
            <li><a class="nav-link <?= isActive('master/ng-categories',$currentUrl)?'active':'' ?>" href="/master/ng-categories">NG Categories</a></li>
            <li><a class="nav-link <?= isActive('master/downtime-categories',$currentUrl)?'active':'' ?>" href="/master/downtime-categories">Downtime Categories</a></li>
        </ul>
    </div>
</li>
<?php endif; ?>

<?php if (in_array($role, ['ADMIN', 'PPIC'])): ?>
<!-- ================= PPC ================= -->
<li class="nav-item mt-3">
<a class="nav-link text-white d-flex justify-content-between align-items-center"
   data-bs-toggle="collapse"
   href="#menuPPC">
    <span><i class="bi bi-clipboard-check me-2"></i> PPC</span>
    <i class="bi bi-chevron-down"></i>
</a>

<div class="collapse <?= isActive('ppc',$currentUrl)?'show':'' ?>" id="menuPPC">
<ul class="nav flex-column ms-3">

<li><a class="nav-link" href="/ppc/good-receive">Good Receive</a></li>
<li><a class="nav-link <?= isActive('ppc/qc-schedule',$currentUrl)?'active':'' ?>" href="/ppc/qc-schedule">QC Schedule</a></li>

<li class="text-secondary small mt-2">Die Casting</li>
<li><a class="nav-link" href="/die-casting/daily-schedule">Daily Schedule</a></li>
<li><a class="nav-link" href="/die-casting/dandori">Daily Dandori Schedule</a></li>

<li class="text-secondary small mt-2">Machining</li>
<li><a class="nav-link" href="/machining/daily-schedule">Daily Schedule</a></li>
<li><a class="nav-link" href="/machining/dandori">Daily Dandori Schedule</a></li>
<li><a class="nav-link" href="/machining/sub-assy-daily-schedule">Sub Assy Daily Schedule</a></li>

<li class="text-secondary small mt-2">Shotblast</li>
<li><a class="nav-link" href="/shot-blasting/schedule">Daily Schedule</a></li>

<li class="text-secondary small mt-2">Baritori</li>
<li><a class="nav-link" href="/baritori/schedule">Daily Schedule</a></li>

<li class="text-secondary small mt-2">Painting</li>
<li><a class="nav-link" href="/painting/delivery-external">Delivery to External</a></li>
<li><a class="nav-link" href="/painting/receive-external">Receiving from External</a></li>

<li class="text-secondary small mt-2">Finished Good</li>
<li><a class="nav-link <?= isActive('finished-good/delivery-schedule',$currentUrl)?'active':'' ?>" href="/finished-good/delivery-schedule"><i class="bi bi-calendar2-check me-1"></i> FG Delivery Schedule</a></li>

<li class="text-secondary small mt-2">Others</li>
<li><a class="nav-link" href="/maintenance">Maintenance</a></li>
<li><a class="nav-link" href="/production/transfer-machining">Part Transfer to Machining</a></li>

</ul>
</div>
</li>
<?php endif; ?>

<?php if (in_array($role, ['ADMIN', 'PPIC'])): ?>
<!-- ================= STOCK OPNAME (STO) ================= -->
<li class="nav-item mt-3">
<a class="nav-link text-white d-flex justify-content-between align-items-center"
   data-bs-toggle="collapse"
   href="#menuSTO">
    <span><i class="bi bi-ui-checks-grid me-2 text-warning"></i> Stock Opname</span>
    <i class="bi bi-chevron-down"></i>
</a>

<div class="collapse <?= isActive('sto',$currentUrl)?'show':'' ?>" id="menuSTO">
<ul class="nav flex-column ms-3">
    <li><a class="nav-link <?= (trim($currentUrl, '/') == 'sto')?'active':'' ?>" href="/sto">Data Riwayat STO</a></li>
    <li><a class="nav-link <?= isActive('sto/input',$currentUrl)?'active':'' ?>" href="/sto/input">Input Manual STO</a></li>
    <li><a class="nav-link <?= isActive('sto/import',$currentUrl)?'active':'' ?>" href="/sto/import"><i class="bi bi-file-earmark-excel me-1 text-success"></i> Import Excel</a></li>
</ul>
</div>
</li>
<?php endif; ?>

<!-- ================= CASTING ================= -->
<li class="nav-item mt-3">
<a class="nav-link text-white d-flex justify-content-between align-items-center"
   data-bs-toggle="collapse"
   href="#menuCasting">
    <span><i class="bi bi-fire me-2"></i> Casting</span>
    <i class="bi bi-chevron-down"></i>
</a>

<div class="collapse <?= isActive('casting',$currentUrl)?'show':'' ?>" id="menuCasting">
<ul class="nav flex-column ms-3">
<li><a class="nav-link" href="#">Request Ingot - SPK/SPB</a></li>
<li><a class="nav-link" href="#">Melting Output</a></li>
<li><a class="nav-link" href="#">Supply Ingot</a></li>
<li><a class="nav-link" href="/die-casting/production">Production Result per Jam</a></li>
<li><a class="nav-link" href="/die-casting/daily-production-achievement">Production Result per Shift</a></li>
<li><a class="nav-link text-danger" href="/casting/scrap">Casting - Scrap</a></li>
</ul>
</div>
</li>

<!-- ================= DELIVERY ================= -->
<li class="nav-item mt-3">
<a class="nav-link text-white d-flex justify-content-between align-items-center"
   data-bs-toggle="collapse"
   href="#menuDelivery">
    <span><i class="bi bi-truck me-2"></i> Delivery</span>
    <i class="bi bi-chevron-down"></i>
</a>

        <div class="collapse <?= isActive('delivery',$currentUrl)?'show':'' ?>" id="menuDelivery">
            <ul class="nav flex-column ms-3">
            <li><a class="nav-link" href="/shot-blasting/delivery">Delivery to Shot Blast</a></li>
            <li><a class="nav-link" href="/baritori/delivery">Delivery to Baritori</a></li>
    </ul>
</div>
</li>

<!-- ================= RECEIVING ================= -->
<li class="nav-item mt-3">
<a class="nav-link text-white d-flex justify-content-between align-items-center"
   data-bs-toggle="collapse"
   href="#menuReceiving">
    <span><i class="bi bi-truck-flatbed me-2"></i> Receiving</span>
    <i class="bi bi-chevron-down"></i>
</a>

        <div class="collapse <?= isActive('receiving',$currentUrl)?'show':'' ?>" id="menuReceiving">
            <ul class="nav flex-column ms-3">
            <li><a class="nav-link" href="/shot-blasting/receiving">Receiving from Shot Blast</a></li>
            <li><a class="nav-link" href="/baritori/receiving">Receiving from Baritori</a></li>
</div>
</li>

<!-- ================= MACHINING ================= -->
<li class="nav-item mt-3">
<a class="nav-link text-white d-flex justify-content-between align-items-center"
   data-bs-toggle="collapse"
   href="#menuMachining">
    <span><i class="bi bi-gear me-2"></i> Machining</span>
    <i class="bi bi-chevron-down"></i>
</a>

        <div class="collapse <?= isActive('machining',$currentUrl)?'show':'' ?>" id="menuMachining">
            <ul class="nav flex-column ms-3">
            <li><a class="nav-link" href="/machining/hourly">Production Result per Jam</a></li>
            <li><a class="nav-link" href="/machining/daily-production-achievement">Production Result per Shift</a></li>
            <li><a class="nav-link text-danger" href="#">Scrap</a></li>

            <li class="text-secondary small mt-2">Leak Test</li>
            <li><a class="nav-link" href="/machining/leak-test">Production Result per Jam</a></li>
            <li><a class="nav-link" href="/machining/leak-test/production-shift">Production Result per Shift</a></li>

            <li class="text-secondary small mt-2">Assy Bushing</li>
            <li><a class="nav-link" href="/machining/assy-bushing/hourly">Production Result per Jam</a></li>
            <li><a class="nav-link" href="/machining/assy-bushing/achievement">Production Result per Shift</a></li>

            <li class="text-secondary small mt-2">Assy Shaft</li>
            <li><a class="nav-link" href="/machining/assy-shaft/hourly">Production Result per Jam</a></li>
            <li><a class="nav-link" href="/machining/assy-shaft/production/shift">Production Result per Shift</a></li>

            <li class="text-secondary small mt-2">Jig Plug</li>
            <li><a class="nav-link" href="/machining/jig-plug/hourly">Production Result per Jam</a></li>
            <li><a class="nav-link" href="/machining/jig-plug/daily-production-achievement">Production Result per Shift</a></li>

            <li class="text-secondary small mt-2">Painting</li>
            <li><a class="nav-link" href="/painting/hourly">Production Result per Jam</a></li>
            <li><a class="nav-link" href="/painting/daily-production-achievement">Production Result per Shift</a></li>
    </ul>
</div>
</li>
<?php endif; // end loggedIn (menutup block yang dibuka di atas) ?>

<?php if (in_array($role, ['ADMIN', 'QC'])): ?>
<!-- ================= QUALITY CONTROL ================= -->
<li class="nav-item mt-3">
    <a class="nav-link text-white d-flex justify-content-between align-items-center"
       data-bs-toggle="collapse"
       href="#menuQC">
        <span><i class="bi bi-shield-check me-2"></i> Quality Control</span>
        <i class="bi bi-chevron-down"></i>
    </a>

    <div class="collapse <?= isActive('qc', $currentUrl) ? 'show' : '' ?>" id="menuQC">
        <ul class="nav flex-column ms-3">
            <li><a class="nav-link <?= ($currentUrl === 'qc' || $currentUrl === '/qc') ? 'active' : '' ?>" href="/qc">QC Inspection</a></li>
            <li><a class="nav-link <?= isActive('qc/completed-items', $currentUrl) ? 'active' : '' ?>" href="/qc/completed-items">QC Completed History</a></li>
            <li><a class="nav-link <?= isActive('qc/defect-ongoing', $currentUrl) ? 'active' : '' ?>" href="/qc/defect-ongoing">
                <i class="bi bi-bug me-1 text-danger"></i> Defect Ongoing
            </a></li>
            <li><a class="nav-link <?= isActive('qc/summary-defect-ongoing', $currentUrl) ? 'active' : '' ?>" href="/qc/summary-defect-ongoing">
                <i class="bi bi-bar-chart-steps me-1 text-warning"></i> Summary Defect Yearly
            </a></li>
        </ul>
    </div>
</li>
<?php endif; ?>

<?php if ($loggedIn): ?>

<!-- ================= FG DELIVERY ================= -->
<li class="nav-item mt-3">
    <a class="nav-link <?= isActive('finished-good/delivery', $currentUrl) ? 'active' : '' ?>"
       href="/finished-good/delivery">
        <i class="bi bi-box-seam me-2"></i> FG Delivery
    </a>
</li>

<!-- ================= RAW MATERIAL ================= -->
<?php if (in_array($role, ['ADMIN', 'PPIC'])): ?>
<li class="nav-item mt-3">
    <a class="nav-link text-white d-flex justify-content-between align-items-center"
       data-bs-toggle="collapse"
       href="#menuRawMaterial">
        <span><i class="bi bi-box-seam me-2"></i> Raw Material</span>
        <i class="bi bi-chevron-down"></i>
    </a>

    <div class="collapse <?= isActive('raw-material', $currentUrl) ? 'show' : '' ?>" id="menuRawMaterial">
        <ul class="nav flex-column ms-3">
            <li><a class="nav-link <?= isActive('raw-material/ingot', $currentUrl) ? 'active' : '' ?>" href="/raw-material/ingot">Input Ingot</a></li>
            <li><a class="nav-link <?= isActive('raw-material/scrap', $currentUrl) ? 'active' : '' ?>" href="/raw-material/scrap">Input Scrap</a></li>
            <li><a class="nav-link <?= isActive('raw-material/stock', $currentUrl) ? 'active' : '' ?>" href="/raw-material/stock">Inventory Stock</a></li>
        </ul>
    </div>
</li>
<?php endif; ?>

<?php endif; // end loggedIn ?>

<!-- ================= DELIVERY CONTROL BOARD (Public) ================= -->
<li class="nav-item mt-2">
    <a class="nav-link <?= isActive('finished-good/delivery-control-board', $currentUrl) ? 'active' : '' ?>"
       href="/finished-good/delivery-control-board">
        <i class="bi bi-clipboard2-data me-2 text-warning"></i> Delivery Control Board
    </a>
</li>

<!-- ================= SPECIAL CONTROL DELIVERY (Public) ================= -->
<li class="nav-item mt-1">
    <a class="nav-link <?= isActive('finished-good/special-control-delivery', $currentUrl) ? 'active' : '' ?>"
       href="/finished-good/special-control-delivery">
        <i class="bi bi-truck-front me-2 text-success"></i> Special Control Delivery
    </a>
</li>

</ul>
</div>
