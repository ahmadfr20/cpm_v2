<?php
$role = session()->get('role');
$currentUrl = service('uri')->getPath();

function isActive($path, $currentUrl)
{
    return str_starts_with($currentUrl, $path);
}
?>

<div id="sidebar" class="sidebar p-3">

<ul class="nav nav-pills flex-column sidebar-nav">

    <!-- ================= DASHBOARD ================= -->
    <li class="nav-item">
        <a class="nav-link <?= $currentUrl === 'dashboard' ? 'active' : '' ?>"
           href="/dashboard">
            <i class="bi bi-speedometer2 me-2"></i>
            <span>Dashboard</span>
        </a>
    </li>

    <!-- ================= MASTER DATA ================= -->
    <?php if (in_array($role, ['ADMIN','PPIC'])): ?>
    <li class="nav-section">MASTER DATA</li>

    <li class="nav-item">
        <a class="nav-link nav-collapse <?= isActive('master',$currentUrl)?'active':'' ?>"
           data-bs-toggle="collapse"
           href="#menuMaster"
           aria-expanded="<?= isActive('master',$currentUrl)?'true':'false' ?>">
            <span>
                <i class="bi bi-database me-2"></i> Master Data
            </span>
            <i class="bi bi-chevron-down"></i>
        </a>

        <div class="collapse <?= isActive('master',$currentUrl)?'show':'' ?>" id="menuMaster">
            <ul class="nav flex-column nav-sub">

                <li class="nav-item">
                    <a class="nav-link <?= isActive('master/shift',$currentUrl)?'active':'' ?>"
                       href="/master/shift">Shift</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= isActive('master/time-slot',$currentUrl)?'active':'' ?>"
                       href="/master/time-slot">Time Slot</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= isActive('master/product',$currentUrl)?'active':'' ?>"
                       href="/master/product">Product</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= isActive('master/machine',$currentUrl)?'active':'' ?>"
                       href="/master/machine">Machine</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= isActive('master/production-standard',$currentUrl)?'active':'' ?>"
                       href="/master/production-standard">Production Standard</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= isActive('master/customer',$currentUrl)?'active':'' ?>"
                       href="/master/customer">Customer</a>
                </li>

            </ul>
        </div>
    </li>
    <?php endif; ?>


    <!-- ================= PPC ================= -->
    <li class="nav-section">PPC</li>

    <li class="nav-item">
        <a class="nav-link nav-collapse <?= 
            isActive('production',$currentUrl) ||
            isActive('material',$currentUrl) ||
            isActive('die-casting',$currentUrl) ||
            isActive('machining',$currentUrl)
            ? 'active' : '' ?>"
           data-bs-toggle="collapse"
           href="#menuPPC"
           aria-expanded="<?= 
                isActive('production',$currentUrl) ||
                isActive('material',$currentUrl) ||
                isActive('die-casting',$currentUrl) ||
                isActive('machining',$currentUrl)
                ? 'true' : 'false' ?>">
            <span>
                <i class="bi bi-clipboard-check me-2"></i> PPC
            </span>
            <i class="bi bi-chevron-down"></i>
        </a>

        <div class="collapse <?= 
                isActive('production',$currentUrl) ||
                isActive('material',$currentUrl) ||
                isActive('die-casting',$currentUrl) ||
                isActive('machining',$currentUrl)
                ? 'show' : '' ?>" id="menuPPC">

            <ul class="nav flex-column nav-sub">

            <?php if (in_array($role, ['ADMIN','PPIC'])): ?>

                <li class="nav-subtitle">Production Plan</li>

                <li class="nav-item">
                    <a class="nav-link <?= isActive('production/daily-schedule',$currentUrl)?'active':'' ?>"
                       href="/production/daily-schedule">
                        Daily Schedule
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= isActive('production/daily-schedule/list',$currentUrl)?'active':'' ?>"
                       href="/production/daily-schedule/list">
                        Schedule List
                    </a>
                </li>

                <li class="nav-subtitle">Raw Material</li>

                <li class="nav-item">
                    <a class="nav-link <?= isActive('material/incoming',$currentUrl)?'active':'' ?>"
                       href="/material/incoming">Receiving</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= isActive('material/transfer-dc',$currentUrl)?'active':'' ?>"
                       href="/material/transfer-dc">Transfer to Die Casting</a>
                </li>

                <li class="nav-subtitle">Die Casting</li>

                <li class="nav-item">
                    <a class="nav-link <?= isActive('die-casting/daily-schedule',$currentUrl)?'active':'' ?>"
                       href="/die-casting/daily-schedule">Schedule</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= isActive('die-casting/dandori',$currentUrl)?'active':'' ?>"
                       href="/die-casting/dandori">Daily Dandori</a>
                </li>

                <li class="nav-subtitle">Machining</li>

                <li class="nav-item">
                    <a class="nav-link <?= isActive('machining/schedule',$currentUrl)?'active':'' ?>"
                       href="/machining/schedule">Daily Schedule</a>
                </li>

            <?php endif; ?>

            </ul>
        </div>
    </li>


    <!-- ================= PRODUCTION ================= -->
    <?php if (in_array($role, ['ADMIN','PRODUCTION','OPERATOR'])): ?>

    <li class="nav-section">PRODUCTION</li>

    <li class="nav-item">
        <a class="nav-link <?= isActive('die-casting/production',$currentUrl)?'active':'' ?>"
           href="/die-casting/production">
            <i class="bi bi-cpu me-2"></i> Die Casting Production
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?= isActive('die-casting/hourly',$currentUrl)?'active':'' ?>"
           href="/die-casting/hourly">
            <i class="bi bi-clock-history me-2"></i> Hourly DC
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?= isActive('machining/production',$currentUrl)?'active':'' ?>"
           href="/machining/production">
            <i class="bi bi-gear-wide-connected me-2"></i> Machining Shift
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?= isActive('machining/hourly',$currentUrl)?'active':'' ?>"
           href="/machining/hourly">
            <i class="bi bi-clock-history me-2"></i> Hourly Machining
        </a>
    </li>

    <?php endif; ?>

</ul>
</div>
