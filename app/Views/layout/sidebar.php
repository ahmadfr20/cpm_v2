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

                    <!-- ✅ PRODUCTION STANDARD (BARU) -->
                    <li class="nav-item">
                        <a class="nav-link <?= isActive('master/production-standard',$currentUrl)?'active':'' ?>"
                        href="/master/production-standard">
                            Production Standard
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?= isActive('master/customer',$currentUrl)?'active':'' ?>"
                        href="/master/customer">Customer</a>
                    </li>

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
                                        <i class="bi bi-calendar-check me-2"></i> Production Daily Schedule
                                    </a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link" href="/production/daily-schedule/list">
                                        <i class="bi bi-list-ul me-2"></i> Daily Schedule List
                                    </a>
                                </li>


                                <!-- RAW MATERIAL -->
                                <!-- <li class="nav-item">
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
                                </li> -->

                                <!-- DIE CASTING -->
                                <li>
                                    <a class="nav-link text-white d-flex justify-content-between align-items-center"
                                    data-bs-toggle="collapse" href="#menuDieCasting"
                                    aria-expanded="<?= isActive('die-casting',$currentUrl)?'true':'false' ?>">
                                        <span><i class="bi bi-cpu me-2"></i> Die Casting</span>
                                        <i class="bi bi-chevron-down"></i>
                                    </a>

                                    <div class="collapse <?= isActive('die-casting',$currentUrl)?'show':'' ?>" id="menuDieCasting">
                                    <ul class="nav flex-column ms-3 mt-1">

                                    <li>
                                        <a class="nav-link <?= isActive('die-casting/schedule',$currentUrl)?'active':'' ?>"
                                        href="/die-casting/schedule">
                                            <i class="bi bi-calendar-plus me-2"></i> Die Casting Schedule
                                        </a>
                                    </li>

                                    <li>
                                        <a class="nav-link <?= isActive('die-casting/dandori',$currentUrl)?'active':'' ?>"
                                        href="/die-casting/dandori">
                                            <i class="bi bi-tools me-2"></i> Daily Dandori
                                        </a>
                                    </li>

                                    </ul>
                                    </div>
                                    </li>
<!-- 
                                <li class="nav-item">
                                    <a class="nav-link text-white d-flex justify-content-between"
                                    data-bs-toggle="collapse"
                                    href="#menuShotBlast">
                                        <span><i class="bi bi-truck me-2"></i> Shot Blast</span>
                                        <i class="bi bi-chevron-down"></i>
                                    </a>

                                    <div class="collapse" id="menuShotBlast">
                                        <ul class="nav flex-column ms-3">
                                            <li><a class="nav-link" href="/shotblast/schedule">Daily Schedule</a></li>
                                            <li><a class="nav-link" href="/shotblast/send">Send to External</a></li>
                                            <li><a class="nav-link" href="/shotblast/receive">Receive from External</a></li>
                                        </ul>
                                    </div>
                                </li> -->

                                <!-- <li class="nav-item">
                                    <a class="nav-link text-white d-flex justify-content-between"
                                    data-bs-toggle="collapse"
                                    href="#menuBaritori">
                                        <span><i class="bi bi-arrow-repeat me-2"></i> Baritori</span>
                                        <i class="bi bi-chevron-down"></i>
                                    </a>

                                    <div class="collapse" id="menuBaritori">
                                        <ul class="nav flex-column ms-3">
                                            <li><a class="nav-link" href="/baritori/schedule">Daily Schedule</a></li>
                                            <li><a class="nav-link" href="/baritori/send-external">Send to External</a></li>
                                            <li><a class="nav-link" href="/baritori/receive-external">Receive from External</a></li>
                                            <li><a class="nav-link" href="/baritori/send-internal">Send to Internal</a></li>
                                        </ul>
                                    </div>
                                </li> -->

                                <!-- machining -->
                                <li class="nav-item">
                                    <a class="nav-link text-white d-flex justify-content-between"
                                    data-bs-toggle="collapse"
                                    href="#menuMachining"
                                    aria-expanded="<?= 
                                        isActive('machining/schedule',$currentUrl) ||
                                        isActive('machining/production',$currentUrl) ||
                                        isActive('machining/dandori',$currentUrl) ||
                                        isActive('machining/sub-assy',$currentUrl)
                                        ? 'true' : 'false' ?>">
                                        <span><i class="bi bi-tools me-2"></i> Machining</span>
                                        <i class="bi bi-chevron-down"></i>
                                    </a>

                                    <div class="collapse <?= 
                                        isActive('machining/schedule',$currentUrl) ||
                                        isActive('machining/production',$currentUrl) ||
                                        isActive('machining/dandori',$currentUrl) ||
                                        isActive('machining/sub-assy',$currentUrl)
                                        ? 'show' : '' ?>"
                                        id="menuMachining">

                                        <ul class="nav flex-column ms-3">

                                            <!-- ✅ DAILY SCHEDULE (BARU) -->
                                            <li class="nav-item">
                                                <a class="nav-link <?= isActive('machining/schedule',$currentUrl)?'active':'' ?>"
                                                href="/machining/schedule">
                                                    <i class="bi bi-calendar-plus me-2"></i> Daily Schedule
                                                </a>
                                            </li>

                                            <!-- DAILY PRODUCTION -->


                                            <!-- DAILY DANDORI -->
                                            <li class="nav-item">
                                                <a class="nav-link <?= isActive('machining/dandori',$currentUrl)?'active':'' ?>"
                                                href="/machining/dandori">
                                                    <i class="bi bi-tools me-2"></i> Daily Dandori
                                                </a>
                                            </li>

                                            <!-- DAILY SUB ASSY -->
                                            <li class="nav-item">
                                                <a class="nav-link <?= isActive('machining/sub-assy',$currentUrl)?'active':'' ?>"
                                                href="/machining/sub-assy">
                                                    <i class="bi bi-diagram-3 me-2"></i> Daily Sub Assy
                                                </a>
                                            </li>

                                        </ul>
                                    </div>
                                </li>


                                <!-- <li class="nav-item">
                                    <a class="nav-link text-white d-flex justify-content-between"
                                    data-bs-toggle="collapse"
                                    href="#menuPainting">
                                        <span><i class="bi bi-palette me-2"></i> Painting</span>
                                        <i class="bi bi-chevron-down"></i>
                                    </a>

                                    <div class="collapse" id="menuPainting">
                                        <ul class="nav flex-column ms-3">
                                            <li><a class="nav-link" href="/painting/schedule">Daily Schedule</a></li>
                                            <li><a class="nav-link" href="/painting/send">Send Painting</a></li>
                                            <li><a class="nav-link" href="/painting/receive-external">Receive from External</a></li>
                                        </ul>
                                    </div>
                                </li> -->




                            </ul>
                        </div>
                    </li>
                    <?php endif; ?>

                </ul>
            </div>
        </li>

        <?php if (in_array($role, ['ADMIN','PRODUCTION','OPERATOR'])): ?>
<li class="nav-item mt-3">
<a class="nav-link text-white d-flex justify-content-between align-items-center"
   data-bs-toggle="collapse" href="#menuProductionSection"
   aria-expanded="<?= isActive('die-casting/production',$currentUrl)?'true':'false' ?>">
    <span><i class="bi bi-gear-wide-connected me-2"></i> Production</span>
    <i class="bi bi-chevron-down"></i>
</a>

<div class="collapse <?= isActive('die-casting/production',$currentUrl)?'show':'' ?>"
     id="menuProductionSection">
<ul class="nav flex-column ms-3 mt-1">

<li>
    <a class="nav-link <?= isActive('die-casting/production',$currentUrl)?'active':'' ?>"
       href="/die-casting/production">
        <i class="bi bi-cpu me-2"></i> Die Casting Production Per Shift
    </a>
</li>
<li>
    <a class="nav-link <?= isActive('die-casting/hourly',$currentUrl)?'active':'' ?>"
       href="/die-casting/hourly">
        <i class="bi bi-clock-history me-2"></i> Hourly Production Die Casting
    </a>
</li>

    <li class="nav-item">
    <a class="nav-link <?= isActive('machining/production',$currentUrl)?'active':'' ?>"
    href="/machining/production">
        <i class="bi bi-gear-wide-connected me-2"></i> Machining Production Per Shift
    </a>
    </li>

<li class="nav-item">
    <a class="nav-link <?= isActive('machining/hourly',$currentUrl)?'active':'' ?>"
       href="/machining/hourly">
        <i class="bi bi-clock-history me-2"></i> Hourly Production Machining
    </a>
</li>



</ul>
</div>
</li>
<?php endif; ?>

        <!-- REPORT
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
        </li> -->

    </ul>
</div>