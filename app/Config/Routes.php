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
    $routes->get('wip', 'Dashboard\WipController::index');
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
    $routes->get('machine/delete/(:num)', 'Master\MachineController::delete/$1');

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



$routes->group('shotblast', ['filter' => 'auth'], function ($routes) {
    $routes->get('schedule', 'ShotBlast\ScheduleController::index');
    $routes->post('schedule/store', 'ShotBlast\ScheduleController::store');

    $routes->get('send', 'ShotBlast\SendController::index');
    $routes->post('send/store', 'ShotBlast\SendController::store');

    $routes->get('receive', 'ShotBlast\ReceiveController::index');
    $routes->post('receive/store', 'ShotBlast\ReceiveController::store');
});

$routes->group('baritori', ['filter' => 'auth'], function ($routes) {

    // Schedule
    $routes->get('schedule', 'Baritori\ScheduleController::index');
    $routes->post('schedule/store', 'Baritori\ScheduleController::store');

    // External
    $routes->get('send-external', 'Baritori\SendExternalController::index');
    $routes->post('send-external/store', 'Baritori\SendExternalController::store');

    $routes->get('receive-external', 'Baritori\ReceiveExternalController::index');
    $routes->post('receive-external/store', 'Baritori\ReceiveExternalController::store');

    // Internal
    $routes->get('send-internal', 'Baritori\SendInternalController::index');
    $routes->post('send-internal/store', 'Baritori\SendInternalController::store');
});

$routes->group('machining', ['filter' => 'auth'], function ($routes) {

    /* ================= DAILY PRODUCTION ================= */
    $routes->get(
        'production',
        'Machining\ProductionController::index'
    );

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

    /* ================= HOURLY INPUT ================= */
    $routes->get(
        'hourly',
        'Machining\HourlyController::index'
    );

    $routes->post(
        'hourly/store',
        'Machining\HourlyController::store'
    );

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











