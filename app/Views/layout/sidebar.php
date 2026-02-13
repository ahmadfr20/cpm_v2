<?php
$role = session()->get('role');
$currentUrl = service('uri')->getPath();

function isActive($path, $currentUrl)
{
    return str_starts_with($currentUrl, $path);
}
?>

<div id="sidebar" class="sidebar bg-dark p-3">
<ul class="nav nav-pills flex-column">

<!-- ================= DASHBOARD ================= -->
<li class="nav-item">
    <a class="nav-link <?= $currentUrl === 'dashboard' ? 'active' : '' ?>"
       href="/dashboard">
        <i class="bi bi-speedometer2 me-2"></i> Dashboard
    </a>
</li>

<!-- <li class="nav-item mt-3">
    <a class="nav-link <?= $currentUrl === 'dashboard' ? 'active' : '' ?>"
       href="/production/daily-schedule/list">
        <i class="bi bi-alarm me-2"></i> All Daily Schedules
    </a>
</li> -->

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
            <li><a class="nav-link <?= isActive('master/shift',$currentUrl)?'active':'' ?>" href="/master/shift">Shift</a></li>
            <li><a class="nav-link <?= isActive('master/time-slot',$currentUrl)?'active':'' ?>" href="/master/time-slot">Time Slot</a></li>
            <li><a class="nav-link <?= isActive('master/product',$currentUrl)?'active':'' ?>" href="/master/product">Product</a></li>
            <li><a class="nav-link <?= isActive('master/machine',$currentUrl)?'active':'' ?>" href="/master/machine">Machine</a></li>
            <li><a class="nav-link <?= isActive('master/production-standard',$currentUrl)?'active':'' ?>" href="/master/production-standard">Production Standard</a></li>
            <li><a class="nav-link <?= isActive('master/production-flow',$currentUrl)?'active':'' ?>" href="/master/production-flow">Production Flow</a></li>
            <li><a class="nav-link <?= isActive('master/customer',$currentUrl)?'active':'' ?>" href="/master/customer">Customer</a></li>
            <li><a class="nav-link <?= isActive('master/vendor',$currentUrl)?'active':'' ?>" href="/master/vendor">Vendor</a></li>
            <li><a class="nav-link <?= isActive('master/ng-categories',$currentUrl)?'active':'' ?>" href="/master/ng-categories">NG Categories</a></li>
        </ul>
    </div>
</li>
<?php endif; ?>

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

<li class="text-secondary small mt-2">Die Casting</li>
<li><a class="nav-link" href="/die-casting/daily-schedule">Daily Schedule</a></li>
<li><a class="nav-link" href="/die-casting/dandori">Daily Dandori Schedule</a></li>

<li class="text-secondary small mt-2">Machining</li>
<li><a class="nav-link" href="/machining/daily-schedule">Daily Schedule</a></li>
<li><a class="nav-link" href="/machining/dandori">Daily Dandori Schedule</a></li>
<li><a class="nav-link" href="/machining/sub-assy-daily-schedule">Sub Assy Daily Schedule</a></li>

<li class="text-secondary small mt-2">Machining Leak Test</li>
<li><a class="nav-link" href="/machining/leak-test/schedule">Daily Schedule</a></li>

<li class="text-secondary small mt-2">Machining Assy Bushing</li>
<li><a class="nav-link" href="/machining/assy-bushing/schedule">Daily Schedule</a></li>

<li class="text-secondary small mt-2">Machining Assy Shaft</li>
<li><a class="nav-link" href="/machining/assy-shaft/schedule">Daily Schedule</a></li>

<li class="text-secondary small mt-2">Shotblast</li>
<li><a class="nav-link" href="/shotblast/daily-schedule">Daily Schedule</a></li>
<li><a class="nav-link" href="/shotblast/delivery-external">Delivery to External</a></li>
<li><a class="nav-link" href="/shotblast/receive-external">Receiving from External</a></li>

<li class="text-secondary small mt-2">Baritori</li>
<li><a class="nav-link" href="/baritori/daily-schedule">Daily Schedule</a></li>
<li><a class="nav-link" href="/baritori/delivery-external">Delivery to External</a></li>
<li><a class="nav-link" href="/baritori/receive-external">Receiving from External</a></li>
<li><a class="nav-link" href="/baritori/internal">Internal Schedule</a></li>

<li class="text-secondary small mt-2">Painting</li>
<li><a class="nav-link" href="/painting/daily-schedule">Daily Schedule</a></li>
<li><a class="nav-link" href="/painting/delivery-external">Delivery to External</a></li>
<li><a class="nav-link" href="/painting/receive-external">Receiving from External</a></li>

<li class="text-secondary small mt-2">Others</li>
<li><a class="nav-link" href="/raw-material">Raw Material</a></li>
<li><a class="nav-link" href="/maintenance">Maintenance</a></li>
<li><a class="nav-link" href="/part-transfer/machining">Part Transfer to Machining</a></li>

</ul>
</div>
</li>

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

<!-- Delivery -->
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

<!-- receiving -->
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
    </ul>
</div>
</li>




<li class="nav-item mt-3">
    <a class="nav-link <?= $currentUrl === 'wip/inventory' ? 'active' : '' ?>"
       href="/wip/inventory">
        <i class="bi bi-alarm me-2"></i> WIP List
    </a>
</li>

<li class="nav-item mt-3">
    <a class="nav-link <?= $currentUrl === 'qc' ? 'active' : '' ?>"
       href="final-inspection/daily-production">
        <i class="bi bi-hand-thumbs-up me-2"></i> QC
    </a>
</li>

</ul>
</div>
