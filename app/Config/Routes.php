<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Auth::login');
$routes->get('/login', 'Auth::login');
$routes->post('/login', 'Auth::authenticate');
$routes->get('/logout', 'Auth::logout');

/**
 * DASHBOARD: semua yang login boleh akses
 */
$routes->group('dashboard', ['filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Dashboard\HomeController::index');
    $routes->get('asakai', 'Dashboard\AsakaiController::index');
    $routes->get('inventory', 'Dashboard\InventoryController::index');

    // kalau dashboard ini mengarah ke WIP, batasi PPIC
    
});

$routes->get('wip/inventory', 'WIP\WipInventoryController::index');

/**
 * MASTER: Admin only
 */
$routes->group('master', ['filter' => 'auth:ADMIN'], function ($routes) {

    // User Management (multi-section privilege)
// User Management
    $routes->get('user', 'Master\UserController::index');
    $routes->post('user/store', 'Master\UserController::store');
    $routes->post('user/update/(:num)', 'Master\UserController::update/$1');
    $routes->post('user/(:num)/delete', 'Master\UserController::delete/$1');

    // Privilege modal (load + save)
    $routes->get('user/(:num)/privilege', 'Master\UserController::privilege/$1');
    $routes->post('user/(:num)/privilege', 'Master\UserController::savePrivilege/$1');


    // Shift
    $routes->get('shift', 'Master\ShiftController::index');
    $routes->get('shift/create', 'Master\ShiftController::create');
    $routes->post('shift/store', 'Master\ShiftController::store');
    $routes->get('shift/edit/(:num)', 'Master\ShiftController::edit/$1');
    $routes->post('shift/update/(:num)', 'Master\ShiftController::update/$1');
    $routes->get('shift/delete/(:num)', 'Master\ShiftController::delete/$1');
    $routes->post('shift/store-index', 'Master\ShiftController::storeFromIndex');
    $routes->post('shift/update-slots/(:num)', 'Master\ShiftController::updateSlots/$1');

    // NG Categories
    $routes->get('ng-categories', 'Master\NgCategoryController::index');
    $routes->post('ng-categories/store', 'Master\NgCategoryController::store');
    $routes->post('ng-categories/update/(:num)', 'Master\NgCategoryController::update/$1');
    $routes->post('ng-categories/(:num)/delete', 'Master\NgCategoryController::delete/$1');

    // Production Standard
    $routes->get('production-standard', 'Master\ProductionStandardController::index');
    $routes->get('production-standard/create', 'Master\ProductionStandardController::create');
    $routes->post('production-standard/store', 'Master\ProductionStandardController::store');
    $routes->get('production-standard/edit/(:num)', 'Master\ProductionStandardController::edit/$1');
    $routes->post('production-standard/update/(:num)', 'Master\ProductionStandardController::update/$1');
    $routes->get('production-standard/delete/(:num)', 'Master\ProductionStandardController::delete/$1');
    $routes->post('production-standard/bulk-store', 'Master\ProductionStandardController::bulkStore');
    $routes->post('production-standard/update-modal/(:num)', 'Master\ProductionStandardController::update/$1');
    $routes->post('production-standard/bulk-update', 'Master\ProductionStandardController::bulkUpdate');
    $routes->post('production-standard/bulk-delete', 'Master\ProductionStandardController::bulkDelete');

    // Time Slot
    $routes->get('time-slot', 'Master\TimeSlotController::index');
    $routes->get('time-slot/create', 'Master\TimeSlotController::create');
    $routes->post('time-slot/store', 'Master\TimeSlotController::store');
    $routes->get('time-slot/edit/(:num)', 'Master\TimeSlotController::edit/$1');
    $routes->post('time-slot/update/(:num)', 'Master\TimeSlotController::update/$1');
    $routes->get('time-slot/delete/(:num)', 'Master\TimeSlotController::delete/$1');

    // Product
    $routes->get('product', 'Master\ProductController::index');
    $routes->get('product/create', 'Master\ProductController::create');
    $routes->post('product/store', 'Master\ProductController::store');
    $routes->get('product/edit/(:num)', 'Master\ProductController::edit/$1');
    $routes->post('product/update/(:num)', 'Master\ProductController::update/$1');
    $routes->get('product/delete/(:num)', 'Master\ProductController::delete/$1');

    // Machine
    $routes->get('machine', 'Master\MachineController::index');
    $routes->get('machine/create', 'Master\MachineController::create');
    $routes->post('machine/store', 'Master\MachineController::store');
    $routes->get('machine/edit/(:num)', 'Master\MachineController::edit/$1');
    $routes->post('machine/update/(:num)', 'Master\MachineController::update/$1');
    $routes->post('machine/(:num)/delete', 'Master\MachineController::deleteMachine/$1');

    $routes->get('machine/products/(:num)', 'Master\MachineController::manageProducts/$1');
    $routes->post('machine/save-products/(:num)', 'Master\MachineController::saveProducts/$1');

    // Production Flow
    $routes->get('production-flow', 'Master\ProductProcessFlowController::index');
    $routes->post('production-flow/save', 'Master\ProductProcessFlowController::save');
    $routes->post('production-flow/bulk-update', 'Master\ProductProcessFlowController::bulkUpdate');
    $routes->post('production-flow/save-individual', 'Master\ProductProcessFlowController::saveIndividual');

    // Customer
    $routes->get('customer', 'Master\CustomerController::index');
    $routes->get('customer/create', 'Master\CustomerController::create');
    $routes->post('customer/store', 'Master\CustomerController::store');
    $routes->get('customer/edit/(:num)', 'Master\CustomerController::edit/$1');
    $routes->post('customer/update/(:num)', 'Master\CustomerController::update/$1');

    // ✅ FIX: DELETE harus POST dan path-nya sesuai form
    $routes->post('customer/(:num)/delete', 'Master\CustomerController::delete/$1');


    // Vendor
    $routes->get('vendor', 'Master\VendorController::index');
    $routes->get('vendor/create', 'Master\VendorController::create');
    $routes->post('vendor/store', 'Master\VendorController::store');
    $routes->get('vendor/edit/(:num)', 'Master\VendorController::edit/$1');
    $routes->post('vendor/update/(:num)', 'Master\VendorController::update/$1');
    $routes->post('vendor/(:num)/delete', 'Master\VendorController::delete/$1');

    // Process
    $routes->get('process', 'Master\ProcessController::index');
});

/**
 * WIP: PPIC only
 */
$routes->group('wip', ['filter' => 'auth:PPIC'], function ($routes) {
    $routes->get('inventory', 'WIP\WipInventoryController::index');
});

/**
 * PRODUCTION DAILY SCHEDULE (umum): PPIC only
 */
$routes->group('production', ['filter' => 'auth:PPIC'], function ($routes) {
    $routes->get('daily-schedule', 'Production\DailyScheduleController::index');
    $routes->post('daily-schedule/store', 'Production\DailyScheduleController::store');
    $routes->get('daily-schedule/view/(:num)', 'Production\DailyScheduleController::view/$1');

    $routes->get('get-machines', 'Production\DailyScheduleController::getMachines');
    $routes->get('get-products', 'Production\DailyScheduleController::getProducts');
    $routes->get('calculate-target', 'Production\DailyScheduleController::calculateTarget');
});

$routes->get('production/daily-schedule/list', 'Production\DailyScheduleController::list', ['filter' => 'auth:PPIC']);

/**
 * MATERIAL: PPIC only (ADMIN bypass)
 */
$routes->group('material', ['filter' => 'auth:PPIC'], function ($routes) {
    $routes->get('incoming', 'Material\IncomingController::index');
    $routes->post('incoming/store', 'Material\IncomingController::store');

    // transfer to die casting (still PPIC)
    $routes->get('transfer-dc', 'Material\TransferToDieCastingController::index');
    $routes->post('transfer-dc/store', 'Material\TransferToDieCastingController::store');
});

/**
 * DIE CASTING
 * - schedule & plan-actual: PPIC
 * - production/hourly/dandori/achievement: OPERATOR process_code = DC
 */
$routes->group('die-casting', ['filter' => 'auth'], function ($routes) {

    // ===== PPIC =====
    $routes->get('daily-production-schedule', 'DieCasting\DailyProductionScheduleController::index', ['filter' => 'auth:PPIC']);
    $routes->post('daily-production-schedule/save', 'DieCasting\DailyProductionScheduleController::save', ['filter' => 'auth:PPIC']);

    $routes->get('daily-schedule', 'DieCasting\DailyScheduleController::index', ['filter' => 'auth:PPIC']);
    $routes->post('daily-schedule/store', 'DieCasting\DailyScheduleController::store', ['filter' => 'auth:PPIC']);
    $routes->get('daily-schedule/view', 'DieCasting\DailyScheduleController::view', ['filter' => 'auth:PPIC']);
    $routes->get('daily-schedule/products', 'DieCasting\DailyScheduleController::getProducts', ['filter' => 'auth:PPIC']);
    $routes->get('daily-schedule/getProductAndTarget', 'DieCasting\DailyScheduleController::getProductAndTarget', ['filter' => 'auth:PPIC']);

    // ===== OPERATOR (DC only) =====
    $routes->get('daily-production', 'DieCasting\DailyProductionController::index', ['filter' => 'auth:OPERATOR,DC']);
    $routes->post('daily-production/store', 'DieCasting\DailyProductionController::store', ['filter' => 'auth:OPERATOR,DC']);
    $routes->post('daily-production/save-slot', 'DieCasting\DailyProductionController::saveSlot', ['filter' => 'auth:OPERATOR,DC']);
    $routes->post('daily-production/finish-shift', 'DieCasting\DailyProductionController::finishShift', ['filter' => 'auth:OPERATOR,DC']);

    $routes->get('production', 'DieCasting\DailyProductionController::index', ['filter' => 'auth:OPERATOR,DC']);
    $routes->post('production/store', 'DieCasting\DailyProductionController::store', ['filter' => 'auth:OPERATOR,DC']);

    $routes->get('hourly', 'DieCasting\HourlyController::index', ['filter' => 'auth:OPERATOR,DC']);
    $routes->post('hourly/store', 'DieCasting\HourlyController::store', ['filter' => 'auth:OPERATOR,DC']);

    $routes->get('dandori', 'DieCasting\DandoriController::index', ['filter' => 'auth:OPERATOR,DC']);
    $routes->post('dandori/store', 'DieCasting\DandoriController::store', ['filter' => 'auth:OPERATOR,DC']);

    $routes->get('daily-production-achievement', 'DieCasting\DailyProductionAchievementController::index', ['filter' => 'auth:OPERATOR,DC']);
    $routes->post('daily-production-achievement/store', 'DieCasting\DailyProductionAchievementController::store', ['filter' => 'auth:OPERATOR,DC']);
});

/**
 * SHOT BLASTING: OPERATOR process_code = SB
 */
$routes->group('shot-blasting', ['filter' => 'auth:OPERATOR,SB,PPIC'], function ($routes) {
    $routes->get('delivery', 'ShotBlasting\DeliveryController::index');
    $routes->post('delivery/store', 'ShotBlasting\DeliveryController::store');

    $routes->get('schedule', 'ShotBlasting\ScheduleController::index', ['filter' => 'auth:PPIC']);
    $routes->post('schedule/store', 'ShotBlasting\ScheduleController::store', ['filter' => 'auth:PPIC']);


    $routes->get('receiving', 'ShotBlasting\ReceivingController::index');
    $routes->post('receiving/store', 'ShotBlasting\ReceivingController::store');
});

/**
 * BARITORI: OPERATOR process_code = BT
 */
$routes->group('baritori', ['filter' => 'auth:OPERATOR,BT,PPIC'], function ($routes) {
    $routes->get('schedule', 'Baritori\ScheduleController::index', ['filter' => 'auth:PPIC']);
    $routes->post('schedule/store', 'Baritori\ScheduleController::store', ['filter' => 'auth:PPIC']);

    $routes->get('send-external', 'Baritori\SendExternalController::index');
    $routes->post('send-external/store', 'Baritori\SendExternalController::store');

    $routes->get('receiving', 'Baritori\ReceivingController::index');
    $routes->post('receiving/store', 'Baritori\ReceivingController::store');

    $routes->get('delivery', 'Baritori\DeliveryController::index');
    $routes->post('delivery/store', 'Baritori\DeliveryController::store');
});

/**
 * MACHINING
 * - schedule: PPIC
 * - production/hourly: OPERATOR process_code = MC
 * - leak test production: OPERATOR process_code = LT
 * - assy bushing production: OPERATOR process_code = AB
 * - assy shaft production: OPERATOR process_code = AS
 */
$routes->group('machining', ['filter' => 'auth'], function ($routes) {

    // ===== PPIC: schedule machining =====
    $routes->get('daily-schedule', 'Machining\DailyScheduleController::index', ['filter' => 'auth:PPIC']);
    $routes->post('daily-schedule/store', 'Machining\DailyScheduleController::store', ['filter' => 'auth:PPIC']);
    $routes->get('daily-schedule/product-target', 'Machining\DailyScheduleController::getProductAndTarget', ['filter' => 'auth:PPIC']);
    $routes->get('daily-schedule/result', 'Machining\DailyScheduleResultController::index', ['filter' => 'auth:PPIC']);

    $routes->get('daily-schedule/incoming-wip', 'Machining\DailyScheduleController::incomingWip', ['filter' => 'auth:PPIC']);
    $routes->post('daily-schedule/assign-incoming-wip-bulk', 'Machining\DailyScheduleController::assignIncomingWipBulk', ['filter' => 'auth:PPIC']);

    // duplikat versi kamu (tetap)
    $routes->get('schedule', 'Machining\DailyScheduleController::index', ['filter' => 'auth:PPIC']);
    $routes->post('schedule/store', 'Machining\DailyScheduleController::store', ['filter' => 'auth:PPIC']);

    // Leak Test schedule (PPIC)
    $routes->get('leak-test/schedule', 'Machining\LeakTestDailyScheduleController::index', ['filter' => 'auth:PPIC']);
    $routes->get('leak-test/schedule/product-target', 'Machining\LeakTestDailyScheduleController::getProductAndTarget', ['filter' => 'auth:PPIC']);
    $routes->post('leak-test/schedule/store', 'Machining\LeakTestDailyScheduleController::store', ['filter' => 'auth:PPIC']);
    $routes->get('leak-test/schedule/incoming-wip', 'Machining\LeakTestDailyScheduleController::incomingWip', ['filter' => 'auth:PPIC']);
    $routes->post('leak-test/schedule/assign-incoming-wip', 'Machining\LeakTestDailyScheduleController::assignIncomingWip', ['filter' => 'auth:PPIC']);

    // Assy Bushing schedule (PPIC)
    $routes->get('assy-bushing/schedule', 'Machining\AssyBushingDailyScheduleController::index', ['filter' => 'auth:PPIC']);
    $routes->get('assy-bushing/schedule/product-target', 'Machining\AssyBushingDailyScheduleController::getProductAndTarget', ['filter' => 'auth:PPIC']);
    $routes->post('assy-bushing/schedule/store', 'Machining\AssyBushingDailyScheduleController::store', ['filter' => 'auth:PPIC']);
    $routes->get('assy-bushing/schedule/incoming-wip', 'Machining\AssyBushingDailyScheduleController::incomingWip', ['filter' => 'auth:PPIC']);
    $routes->post('assy-bushing/schedule/assign-incoming-wip', 'Machining\AssyBushingDailyScheduleController::assignIncomingWip', ['filter' => 'auth:PPIC']);

    // Assy Shaft schedule (PPIC)
    $routes->get('assy-shaft/schedule', 'Machining\AssyShaftDailyScheduleController::index', ['filter' => 'auth:PPIC']);
    $routes->get('assy-shaft/schedule/product-target', 'Machining\AssyShaftDailyScheduleController::getProductAndTarget', ['filter' => 'auth:PPIC']);
    $routes->post('assy-shaft/schedule/store', 'Machining\AssyShaftDailyScheduleController::store', ['filter' => 'auth:PPIC']);
    $routes->get('assy-shaft/schedule/incoming-wip', 'Machining\AssyShaftDailyScheduleController::incomingWip', ['filter' => 'auth:PPIC']);
    $routes->post('assy-shaft/schedule/assign-incoming-wip', 'Machining\AssyShaftDailyScheduleController::assignIncomingWip', ['filter' => 'auth:PPIC']);

    // ===== OPERATOR: MACHINING (MC) =====
    $routes->get('production', 'Machining\ProductionController::index', ['filter' => 'auth:OPERATOR,MC']);
    $routes->post('production/store', 'Machining\ProductionController::store', ['filter' => 'auth:OPERATOR,MC']);

    $routes->get('hourly', 'Machining\HourlyController::index', ['filter' => 'auth:OPERATOR,MC']);
    $routes->post('hourly/store', 'Machining\HourlyController::store', ['filter' => 'auth:OPERATOR,MC']);
    $routes->post('hourly/finish-shift', 'Machining\HourlyController::finishShift', ['filter' => 'auth:OPERATOR,MC']);

    $routes->get('daily-production-achievement', 'Machining\DailyProductionAchievementController::index', ['filter' => 'auth:OPERATOR,MC']);
    $routes->post('daily-production-achievement/store', 'Machining\DailyProductionAchievementController::store', ['filter' => 'auth:OPERATOR,MC']);

    $routes->get('dandori', 'Machining\DandoriController::index', ['filter' => 'auth:OPERATOR,MC']);
    $routes->post('dandori/store', 'Machining\DandoriController::store', ['filter' => 'auth:OPERATOR,MC']);

    $routes->get('sub-assy', 'Machining\SubAssyController::index', ['filter' => 'auth:OPERATOR,MC']);
    $routes->post('sub-assy/store', 'Machining\SubAssyController::store', ['filter' => 'auth:OPERATOR,MC']);

    // ===== OPERATOR: LEAK TEST (LT) =====
    $routes->get('leak-test', 'Machining\LeakTestDailyProductionController::index', ['filter' => 'auth:OPERATOR,LT']);
    $routes->post('leak-test/hourly/store', 'Machining\LeakTestDailyProductionController::store', ['filter' => 'auth:OPERATOR,LT']);
    $routes->post('leak-test/hourly/finish-shift', 'Machining\LeakTestDailyProductionController::finishShift', ['filter' => 'auth:OPERATOR,LT']);
    $routes->get('leak-test/production-shift', 'Machining\LeakTestProductionController::index', ['filter' => 'auth:OPERATOR,LT']);

    // ===== OPERATOR: ASSY BUSHING (AB) =====
    $routes->get('assy-bushing/hourly', 'Machining\AssyBushingDailyProductionController::index', ['filter' => 'auth:OPERATOR,AB']);
    $routes->post('assy-bushing/hourly/store', 'Machining\AssyBushingDailyProductionController::store', ['filter' => 'auth:OPERATOR,AB']);
    $routes->post('assy-bushing/hourly/finish-shift', 'Machining\AssyBushingDailyProductionController::finishShift', ['filter' => 'auth:OPERATOR,AB']);

    $routes->get('assy-bushing/achievement', 'Machining\AssyBushingDailyProductionAchievementController::index', ['filter' => 'auth:OPERATOR,AB']);
    $routes->post('assy-bushing/achievement/store', 'Machining\AssyBushingDailyProductionAchievementController::store', ['filter' => 'auth:OPERATOR,AB']);

    // ===== OPERATOR: ASSY SHAFT (AS) =====
    $routes->get('assy-shaft/hourly', 'Machining\AssyShaftDailyProductionController::index', ['filter' => 'auth:OPERATOR,AS']);
    $routes->post('assy-shaft/hourly/store', 'Machining\AssyShaftDailyProductionController::store', ['filter' => 'auth:OPERATOR,AS']);
    $routes->post('assy-shaft/hourly/finish-shift', 'Machining\AssyShaftDailyProductionController::finishShift', ['filter' => 'auth:OPERATOR,AS']);
    $routes->get('assy-shaft/production/shift', 'Machining\AssyShaftShiftProductionController::index', ['filter' => 'auth:OPERATOR,AS']);
});

/**
 * PAINTING: OPERATOR process_code = PT
 */
$routes->group('painting', ['filter' => 'auth:OPERATOR,PT'], function ($routes) {
    $routes->get('schedule', 'Painting\ScheduleController::index');
    $routes->post('schedule/store', 'Painting\ScheduleController::store');

    $routes->get('send', 'Painting\SendController::index');
    $routes->post('send/store', 'Painting\SendController::store');

    $routes->get('receive-external', 'Painting\ReceiveExternalController::index');
    $routes->post('receive-external/store', 'Painting\ReceiveExternalController::store');
});

/**
 * FINAL INSPECTION: OPERATOR process_code = FI
 */
$routes->group('final-inspection', ['filter' => 'auth:OPERATOR,FI'], function ($routes) {
    $routes->get('daily-production', 'FinalInspection\FinalInspectionController::index');
    $routes->post('daily-production/store', 'FinalInspection\FinalInspectionController::store');
    $routes->post('daily-production/save-slot', 'FinalInspection\FinalInspectionController::saveSlot');
});

$routes->get('/asakai', 'AsakaiController::index', ['filter' => 'auth']);
