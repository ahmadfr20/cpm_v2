<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Auth::login');
$routes->get('/login', 'Auth::login');
$routes->post('/login', 'Auth::authenticate');
$routes->get('/logout', 'Auth::logout');

$routes->group('dashboard', ['filter' => 'auth'], function ($routes) {

    $routes->get('/', 'Dashboard\HomeController::index');

    $routes->get('asakai', 'Dashboard\AsakaiController::index');
    $routes->get('wip/inventory', 'WIP\WipInventoryController::index');
    $routes->get('inventory', 'Dashboard\InventoryController::index');

});




$routes->group('master', ['filter' => 'auth'], function ($routes) {

    // Shift
    $routes->get('shift', 'Master\ShiftController::index');
    $routes->get('shift/create', 'Master\ShiftController::create');
    $routes->post('shift/store', 'Master\ShiftController::store');
    $routes->get('shift/edit/(:num)', 'Master\ShiftController::edit/$1');
    $routes->post('shift/update/(:num)', 'Master\ShiftController::update/$1');
    $routes->get('shift/delete/(:num)', 'Master\ShiftController::delete/$1');

    $routes->get('production-standard', 'Master\ProductionStandardController::index');
    $routes->get('production-standard/create', 'Master\ProductionStandardController::create');
    $routes->post('production-standard/store', 'Master\ProductionStandardController::store');
    $routes->get('production-standard/edit/(:num)', 'Master\ProductionStandardController::edit/$1');
    $routes->post('production-standard/update/(:num)', 'Master\ProductionStandardController::update/$1');
    $routes->get('production-standard/delete/(:num)', 'Master\ProductionStandardController::delete/$1');


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
    $routes->post(
    'machine/(:num)/delete',
    'Master\MachineController::deleteMachine/$1');

    $routes->get ('production-flow', 'Master\ProductProcessFlowController::index');
    $routes->post('production-flow/save', 'Master\ProductProcessFlowController::save');
    $routes->post(
    'production-flow/bulk-update',
    'Master\ProductProcessFlowController::bulkUpdate'
);

    $routes->post('production-flow/save-individual', 'Master\ProductProcessFlowController::saveIndividual');





    $routes->get('machine/products/(:num)', 'Master\MachineController::manageProducts/$1');
    $routes->post('machine/save-products/(:num)', 'Master\MachineController::saveProducts/$1');

    $routes->get('customer', 'Master\CustomerController::index');
    $routes->get('customer/create', 'Master\CustomerController::create');
    $routes->post('customer/store', 'Master\CustomerController::store');
    $routes->get('customer/edit/(:num)', 'Master\CustomerController::edit/$1');
    $routes->post('customer/update/(:num)', 'Master\CustomerController::update/$1');
    $routes->get('customer/delete/(:num)', 'Master\CustomerController::delete/$1');

    // Process
    $routes->get('process', 'Master\ProcessController::index');

    // User (ADMIN)
    $routes->get('user', 'Master\UserController::index');
});

$routes->group('production', ['filter'=>'auth'], function($routes){
    $routes->get('daily-schedule', 'Production\DailyScheduleController::index');
    $routes->post('daily-schedule/store', 'Production\DailyScheduleController::store');
    $routes->get('daily-schedule/view/(:num)', 'Production\DailyScheduleController::view/$1');
    $routes->get('get-machines', 'Production\DailyScheduleController::getMachines');

    $routes->get('get-products', 'Production\DailyScheduleController::getProducts');
    $routes->get('calculate-target', 'Production\DailyScheduleController::calculateTarget');

});



$routes->group('material', ['filter' => 'auth'], function ($routes) {
    $routes->get('incoming', 'Material\IncomingController::index');
    $routes->post('incoming/store', 'Material\IncomingController::store');
});

$routes->group('material', ['filter'=>'auth'], function ($routes) {
    $routes->get('transfer-dc', 'Material\TransferToDieCastingController::index');
    $routes->post('transfer-dc/store', 'Material\TransferToDieCastingController::store');
});

$routes->group('wip', ['filter'=>'auth'], function ($routes) {
    $routes->get('inventory', 'WIP\WipInventoryController::index');
});

$routes->group('die-casting', ['filter' => 'auth'], function ($routes) {

    // =====================================================
    // 1️⃣ DAILY PRODUCTION SCHEDULE (PLAN vs ACTUAL)
    // =====================================================
    // Ringkasan harian: target (P) vs actual (A & NG)
    $routes->get(
        'daily-production-schedule',
        'DieCasting\DailyProductionScheduleController::index'
    );

    $routes->post(
        'daily-production-schedule/save',
        'DieCasting\DailyProductionScheduleController::save'
    );

    // =====================================================
    // 2️⃣ DAILY PRODUCTION (HOURLY INPUT – OPERATOR)
    // =====================================================
    // Input FG & NG per jam (sesuai time slot & shift)
    $routes->get(
        'daily-production',
        'DieCasting\DailyProductionController::index'
    );

    $routes->post(
        'daily-production/store',
        'DieCasting\DailyProductionController::store'
    );

    $routes->post(
        'daily-production/save-slot',
        'DieCasting\DailyProductionController::saveSlot'
    );

    $routes->post('daily-production/finish-shift', 'DieCasting\DailyProductionController::finishShift');


    // =====================================================
    // 3️⃣ DAILY SCHEDULE DIE CASTING (PLAN / TARGET – PPIC)
    // =====================================================
    // Input target P per mesin & shift
    $routes->get(
        'daily-schedule',
        'DieCasting\DailyScheduleController::index'
    );

    $routes->post(
        'daily-schedule/store',
        'DieCasting\DailyScheduleController::store'
    );

    // View hasil daily schedule
    $routes->get(
        'daily-schedule/view',
        'DieCasting\DailyScheduleController::view'
    );

    // AJAX: product by machine
    $routes->get(
        'daily-schedule/products',
        'DieCasting\DailyScheduleController::getProducts'
    );

    // (opsional) AJAX: product + target
    $routes->get('daily-schedule/getProductAndTarget', 
        'DieCasting\DailyScheduleController::getProductAndTarget');

    // =====================================================
    // 4️⃣ PRODUCTION PER SHIFT (OPERATOR / PRODUCTION)
    // =====================================================
    $routes->get(
        'production',
        'DieCasting\DailyProductionController::index'
    );

    $routes->post(
        'production/store',
        'DieCasting\DailyProductionController::store'
    );

    // =====================================================
    // 5️⃣ HOURLY LEGACY / BACKUP
    // =====================================================
    $routes->get(
        'hourly',
        'DieCasting\HourlyController::index'
    );

    $routes->post(
        'hourly/store',
        'DieCasting\HourlyController::store'
    );

    // =====================================================
    // 6️⃣ DANDORI
    // =====================================================
    $routes->get(
        'dandori',
        'DieCasting\DandoriController::index'
    );

    $routes->post(
        'dandori/store',
        'DieCasting\DandoriController::store'
    );

        // =====================================================
    // 5️⃣ DAILY PRODUCTION ACHIEVEMENT (END SHIFT KOREKSI)
    // =====================================================
    // Rekap per shift + koreksi FG, NG, NG Category, Downtime
    $routes->get(
        'daily-production-achievement',
        'DieCasting\DailyProductionAchievementController::index'
    );

    $routes->post(
        'daily-production-achievement/store',
        'DieCasting\DailyProductionAchievementController::store'
    );

});



$routes->group('shot-blasting', ['filter' => 'auth'], function ($routes) {
    $routes->get('delivery', 'ShotBlasting\DeliveryController::index');
    $routes->post('delivery/store', 'ShotBlasting\DeliveryController::store');

        $routes->get('receiving', 'ShotBlasting\ReceivingController::index');
    $routes->post('receiving/store', 'ShotBlasting\ReceivingController::store');
});

$routes->group('baritori', ['filter' => 'auth'], function ($routes) {

    // Schedule
    $routes->get('schedule', 'Baritori\ScheduleController::index');
    $routes->post('schedule/store', 'Baritori\ScheduleController::store');

    // External
    $routes->get('send-external', 'Baritori\SendExternalController::index');
    $routes->post('send-external/store', 'Baritori\SendExternalController::store');

    $routes->get('receiving', 'Baritori\ReceivingController::index');
    $routes->post('receiving/store', 'Baritori\ReceivingController::store');

    // Internal
    $routes->get('delivery', 'Baritori\DeliveryController::index');
    $routes->post('delivery/store', 'Baritori\DeliveryController::store');
});

$routes->group('machining', ['filter' => 'auth'], function ($routes) {

    /* ================= DAILY PRODUCTION ================= */
    $routes->get(
        'production',
        'Machining\ProductionController::index'
    );

    $routes->get('daily-schedule/incoming-wip', 'Machining\DailyScheduleController::incomingWip');
    $routes->post('daily-schedule/assign-incoming-wip-bulk', 'Machining\DailyScheduleController::assignIncomingWipBulk');


    $routes->post(
        'production/store',
        'Machining\ProductionController::store'
    );

    /* ================= DAILY SCHEDULE ================= */
    $routes->get(
        'daily-schedule',
        'Machining\DailyScheduleController::index'
    );

    $routes->post(
        'daily-schedule/store',
        'Machining\DailyScheduleController::store'
    );

    $routes->get(
        'daily-schedule/product-target',
        'Machining\DailyScheduleController::getProductAndTarget'
    );

    $routes->get(
        'daily-schedule/result',
        'Machining\DailyScheduleResultController::index'
    );

    $routes->get('daily-production-achievement', 'Machining\DailyProductionAchievementController::index');
    $routes->post('daily-production-achievement/store', 'Machining\DailyProductionAchievementController::store');


    /* ================= HOURLY INPUT ================= */
    $routes->get(
        'hourly',
        'Machining\HourlyController::index'
    );

    $routes->post(
        'hourly/store',
        'Machining\HourlyController::store'
    );
    $routes->post('hourly/finish-shift', 'Machining\HourlyController::finishShift');


    $routes->get('leak-test/schedule/incoming-wip', 'Machining\LeakTestDailyScheduleController::incomingWip');
    $routes->post('leak-test/schedule/assign-incoming-wip', 'Machining\LeakTestDailyScheduleController::assignIncomingWip');

    $routes->get(
    'leak-test',
    'Machining\LeakTestDailyProductionController::index'
    );

    $routes->post(
        'leak-test/hourly/store',
        'Machining\LeakTestDailyProductionController::store'
    );

    $routes->get(
    'leak-test/production-shift',
    'Machining\LeakTestProductionController::index'
    );

    $routes->get('leak-test/schedule', 'Machining\LeakTestDailyScheduleController::index');
    $routes->get('leak-test/schedule/product-target', 'Machining\LeakTestDailyScheduleController::getProductAndTarget');
    $routes->post('leak-test/schedule/store', 'Machining\LeakTestDailyScheduleController::store');

    $routes->get(
    'assy-bushing/hourly',
    'Machining\AssyBushingDailyProductionController::index'
    );

    $routes->post(
        'assy-bushing/hourly/store',
        'Machining\AssyBushingDailyProductionController::store'
    );
    $routes->post('leak-test/hourly/finish-shift', 'Machining\LeakTestDailyProductionController::finishShift');

    $routes->get('assy-bushing/achievement', 'Machining\AssyBushingDailyProductionAchievementController::index');
    $routes->post('assy-bushing/achievement/store', 'Machining\AssyBushingDailyProductionAchievementController::store');


    $routes->get('assy-bushing/schedule/incoming-wip', 'Machining\AssyBushingDailyScheduleController::incomingWip');
    $routes->post('assy-bushing/schedule/assign-incoming-wip', 'Machining\AssyBushingDailyScheduleController::assignIncomingWip');

$routes->post('assy-bushing/hourly/finish-shift', 'Machining\AssyBushingDailyProductionController::finishShift');
    $routes->get('assy-bushing/schedule', 'Machining\AssyBushingDailyScheduleController::index');
    $routes->get('assy-bushing/schedule/product-target', 'Machining\AssyBushingDailyScheduleController::getProductAndTarget');
    $routes->post('assy-bushing/schedule/store', 'Machining\AssyBushingDailyScheduleController::store');

    $routes->get(
        'assy-shaft/hourly',
        'Machining\AssyShaftDailyProductionController::index'
    );

    $routes->post(
        'assy-shaft/hourly/store',
        'Machining\AssyShaftDailyProductionController::store'
    );

    $routes->get(
    'assy-shaft/production/shift',
    'Machining\AssyShaftShiftProductionController::index'
    );

    $routes->get('assy-shaft/schedule', 'Machining\AssyShaftDailyScheduleController::index');
    $routes->get('assy-shaft/schedule/product-target', 'Machining\AssyShaftDailyScheduleController::getProductAndTarget');
    $routes->post('assy-shaft/schedule/store', 'Machining\AssyShaftDailyScheduleController::store');
    $routes->get('assy-shaft/schedule/incoming-wip', 'Machining\AssyShaftDailyScheduleController::incomingWip');
    $routes->post('assy-shaft/schedule/assign-incoming-wip', 'Machining\AssyShaftDailyScheduleController::assignIncomingWip');
    $routes->post('assy-shaft/hourly/finish-shift', 'Machining\AssyShaftDailyProductionController::finishShift');




    /* ================= SUPPORT MODULE ================= */
    $routes->get('dandori', 'Machining\DandoriController::index');
    $routes->post('dandori/store', 'Machining\DandoriController::store');

    $routes->get('sub-assy', 'Machining\SubAssyController::index');
    $routes->post('sub-assy/store', 'Machining\SubAssyController::store');
});


$routes->group('painting', ['filter' => 'auth'], function ($routes) {

    $routes->get('schedule', 'Painting\ScheduleController::index');
    $routes->post('schedule/store', 'Painting\ScheduleController::store');

    $routes->get('send', 'Painting\SendController::index');
    $routes->post('send/store', 'Painting\SendController::store');

    $routes->get('receive-external', 'Painting\ReceiveExternalController::index');
    $routes->post('receive-external/store', 'Painting\ReceiveExternalController::store');
});

$routes->group('machining', ['filter' => 'auth'], function ($routes) {

    // 🔹 Schedule Machining (PPIC)
    $routes->get('schedule', 'Machining\DailyScheduleController::index');
    $routes->post('schedule/store', 'Machining\DailyScheduleController::store');

    // 🔹 Production Machining (Operator)
    $routes->get('production', 'Machining\ProductionController::index');
    $routes->post('production/store', 'Machining\ProductionController::store');

    // 🔹 Dandori
    $routes->get('dandori', 'Machining\DandoriController::index');
    $routes->post('dandori/store', 'Machining\DandoriController::store');

    // 🔹 Sub Assy
    $routes->get('sub-assy', 'Machining\SubAssyController::index');
    $routes->post('sub-assy/store', 'Machining\SubAssyController::store');
});

$routes->get(
    'production/daily-schedule/list',
    'Production\DailyScheduleController::list'
);

$routes->get('/asakai', 'AsakaiController::index', ['filter' => 'auth']);











