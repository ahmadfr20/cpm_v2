<?php
$role = session()->get('role');
$currentUrl = service('uri')->getPath();

function isActive($path, $currentUrl)
{
    return str_starts_with($currentUrl, $path);
}
?>

<div id="sidebar" class="sidebar bg-dark p-3">
    <ul class="nav nav-pills flex-column gap-0">

        <!-- DASHBOARD -->
        <li class="nav-item">
            <a class="nav-link <?= $currentUrl === 'dashboard' ? 'active' : '' ?>"
               href="/dashboard">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
        </li>

        <!-- MASTER DATA -->
        <?php if (in_array($role, ['ADMIN','PPIC'])): ?>
        <li class="nav-item mt-3">
            <a class="nav-link text-white d-flex justify-content-between align-items-center"
               data-bs-toggle="collapse"
               href="#menuMaster"
               aria-expanded="<?= isActive('master', $currentUrl) ? 'true' : 'false' ?>">
                <span><i class="bi bi-database me-2"></i> Master Data</span>
                <i class="bi bi-chevron-down"></i>
            </a>

            <div class="collapse <?= isActive('master', $currentUrl) ? 'show' : '' ?>"
                 id="menuMaster">
                <ul class="nav flex-column ms-3 mt-1">
                    <li class="nav-item"><a class="nav-link <?= isActive('master/shift',$currentUrl)?'active':'' ?>" href="/master/shift">Shift</a></li>
                    <li class="nav-item"><a class="nav-link <?= isActive('master/time-slot',$currentUrl)?'active':'' ?>" href="/master/time-slot">Time Slot</a></li>
                    <li class="nav-item"><a class="nav-link <?= isActive('master/product',$currentUrl)?'active':'' ?>" href="/master/product">Product</a></li>
                    <li class="nav-item"><a class="nav-link <?= isActive('master/machine',$currentUrl)?'active':'' ?>" href="/master/machine">Machine</a></li>
                    <li class="nav-item"><a class="nav-link <?= isActive('master/customer',$currentUrl)?'active':'' ?>" href="/master/customer">Customer</a></li>
                </ul>
            </div>
        </li>
        <?php endif; ?>

        <!-- TRANSACTION -->
        <li class="nav-item mt-3">
            <a class="nav-link text-white d-flex justify-content-between align-items-center"
               data-bs-toggle="collapse"
               href="#menuTransaction"
               aria-expanded="<?= isActive('production',$currentUrl) || isActive('material',$currentUrl) || isActive('die-casting',$currentUrl) ? 'true' : 'false' ?>">
                <span><i class="bi bi-clipboard-check me-2"></i> Transaction</span>
                <i class="bi bi-chevron-down"></i>
            </a>

            <div class="collapse <?= isActive('production',$currentUrl) || isActive('material',$currentUrl) || isActive('die-casting',$currentUrl) ? 'show' : '' ?>"
                 id="menuTransaction">
                <ul class="nav flex-column ms-3 mt-1">

                    <!-- PPC -->
                    <?php if (in_array($role, ['ADMIN','PPIC'])): ?>
                    <li class="nav-item">
                        <a class="nav-link text-white d-flex justify-content-between align-items-center"
                           data-bs-toggle="collapse"
                           href="#menuPPC"
                           aria-expanded="<?= 
                               isActive('production/daily-schedule',$currentUrl) || 
                               isActive('material',$currentUrl) || 
                               isActive('die-casting',$currentUrl)
                               ? 'true' : 'false' ?>">
                            <span><i class="bi bi-diagram-3 me-2"></i> PPC</span>
                            <i class="bi bi-chevron-down"></i>
                        </a>

                        <div class="collapse <?= 
                            isActive('production/daily-schedule',$currentUrl) || 
                            isActive('material',$currentUrl) || 
                            isActive('die-casting',$currentUrl)
                            ? 'show' : '' ?>"
                             id="menuPPC">
                            <ul class="nav flex-column ms-3 mt-1">

                                <!-- DAILY SCHEDULE -->
                                <li class="nav-item">
                                    <a class="nav-link <?= isActive('production/daily-schedule',$currentUrl)?'active':'' ?>"
                                       href="/production/daily-schedule">
                                        <i class="bi bi-calendar-check me-2"></i> Daily Schedule
                                    </a>
                                </li>

                                <!-- RAW MATERIAL -->
                                <li class="nav-item">
                                    <a class="nav-link text-white d-flex justify-content-between align-items-center"
                                       data-bs-toggle="collapse"
                                       href="#menuRawMaterial"
                                       aria-expanded="<?= isActive('material/incoming',$currentUrl) || isActive('material/transfer-dc',$currentUrl) ? 'true' : 'false' ?>">
                                        <span><i class="bi bi-box-seam me-2"></i> Penerimaan Bahan Baku</span>
                                        <i class="bi bi-chevron-down"></i>
                                    </a>

                                    <div class="collapse <?= isActive('material/incoming',$currentUrl) || isActive('material/transfer-dc',$currentUrl) ? 'show' : '' ?>"
                                         id="menuRawMaterial">
                                        <ul class="nav flex-column ms-3 mt-1">
                                            <li class="nav-item">
                                                <a class="nav-link <?= isActive('material/incoming',$currentUrl)?'active':'' ?>"
                                                   href="/material/incoming">
                                                    <i class="bi bi-box-arrow-in-down me-2"></i> Receiving
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link <?= isActive('material/transfer-dc',$currentUrl)?'active':'' ?>"
                                                   href="/material/transfer-dc">
                                                    <i class="bi bi-arrow-right-square me-2"></i> Transfer to Die Casting
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </li>

                                <!-- DIE CASTING -->
                                <li class="nav-item">
                                    <a class="nav-link text-white d-flex justify-content-between align-items-center"
                                       data-bs-toggle="collapse"
                                       href="#menuDieCasting"
                                       aria-expanded="<?= isActive('die-casting',$currentUrl) ? 'true' : 'false' ?>">
                                        <span><i class="bi bi-cpu me-2"></i> Die Casting</span>
                                        <i class="bi bi-chevron-down"></i>
                                    </a>

                                    <div class="collapse <?= isActive('die-casting',$currentUrl) ? 'show' : '' ?>"
                                         id="menuDieCasting">
                                        <ul class="nav flex-column ms-3 mt-1">
                                            <li class="nav-item">
                                                <a class="nav-link <?= isActive('die-casting/production',$currentUrl)?'active':'' ?>"
                                                   href="/die-casting/production">
                                                    <i class="bi bi-clipboard-data me-2"></i> Daily Production
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link <?= isActive('die-casting/dandori',$currentUrl)?'active':'' ?>"
                                                   href="/die-casting/dandori">
                                                    <i class="bi bi-tools me-2"></i> Daily Dandori
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </li>

                            </ul>
                        </div>
                    </li>
                    <?php endif; ?>

                </ul>
            </div>
        </li>

        <!-- REPORT -->
        <li class="nav-item mt-3">
            <a class="nav-link text-white d-flex justify-content-between align-items-center"
               data-bs-toggle="collapse"
               href="#menuReport"
               aria-expanded="<?= isActive('report',$currentUrl)?'true':'false' ?>">
                <span><i class="bi bi-bar-chart-line me-2"></i> Report</span>
                <i class="bi bi-chevron-down"></i>
            </a>

            <div class="collapse <?= isActive('report',$currentUrl)?'show':'' ?>"
                 id="menuReport">
                <ul class="nav flex-column ms-3 mt-1">
                    <li class="nav-item"><a class="nav-link <?= isActive('report/wip',$currentUrl)?'active':'' ?>" href="/report/wip">WIP</a></li>
                    <li class="nav-item"><a class="nav-link <?= isActive('report/production',$currentUrl)?'active':'' ?>" href="/report/production">Production Report</a></li>
                </ul>
            </div>
        </li>

    </ul>
</div>
