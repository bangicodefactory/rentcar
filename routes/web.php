<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\VehicleTypeController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\InspectionTypeController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\OptionController;
use App\Http\Controllers\AddonController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\ExpenseTypeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RentalAgreementController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReminderTypeController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\TvaController;
use App\Http\Controllers\TvaRenumberController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\RequestBookingController;
use App\Http\Controllers\TrafficViolationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

require __DIR__ . '/auth.php';

Route::get('/', [HomeController::class, 'index'])->middleware(
    [

        'XSS',
    ]
);

// Crawler-facing endpoints (BAN-262). Generated, not static, because which
// pages exist depends on the client's feature flags.
Route::get('/sitemap.xml', [\App\Http\Controllers\SeoController::class, 'sitemap'])->name('seo.sitemap');

// Public B2C rental storefront: the fleet/booking landing plus the pages its
// layout partials link to. Guarded by `feature:public_storefront` (BAN-261) so a
// client whose public face is not a rental storefront 404s the whole family
// instead of serving pages aimed at the opposite audience.
Route::middleware('feature:public_storefront')->group(function () {
    // Public landing (client) home page using new modular Blade layout
    Route::get('/landing', [HomeController::class, 'landing'])->name('client.home');

    // Simple placeholder public pages used by layout partials (can be replaced with real controllers later)
    Route::view('/contact', 'client.pages.contact')->name('contact');
    Route::get('/search', function (\Illuminate\Http\Request $request) {
        $q = $request->get('q');
        return view('client.pages.search', compact('q'));
    })->name('search');
    Route::post('/newsletter/subscribe', function (\Illuminate\Http\Request $request) {
        $data = $request->validate(['email' => 'required|email']);
        // TODO: store subscription or dispatch job
        return back()->with('status', 'Subscribed with ' . $data['email']);
    })->name('newsletter.subscribe');
});

Route::get('home', [HomeController::class, 'index'])->name('home')->middleware(
    [

        'XSS',
    ]
);
Route::get('dashboard', [HomeController::class, 'index'])->name('dashboard')->middleware(
    [

        'XSS',
    ]
);

//-------------------------------User-------------------------------------------

Route::resource('users', UserController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);

//-------------------------------Settings-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {
        Route::get('settings/account', [SettingController::class, 'account'])->name('setting.account');
        // perf-audit F-22: the settings write (POST) routes share their GET's URI
        // and previously duplicated the GET's name, which made `route:cache` throw.
        // They now carry NO name — the GET keeps `setting.X`, and forms post to
        // `route('setting.X')` (the GET URL), which matches these POSTs by path +
        // verb. URIs and the GET names are unchanged.
        Route::post('settings/account', [SettingController::class, 'accountData']);
        Route::delete('settings/account/delete', [SettingController::class, 'accountDelete'])->name('setting.account.delete');

        Route::get('settings/password', [SettingController::class, 'password'])->name('setting.password');
        Route::post('settings/password', [SettingController::class, 'passwordData']);

        Route::get('settings/general', [SettingController::class, 'general'])->name('setting.general');
        Route::post('settings/general', [SettingController::class, 'generalData']);

        Route::get('settings/smtp', [SettingController::class, 'smtp'])->name('setting.smtp');
        Route::post('settings/smtp', [SettingController::class, 'smtpData']);

        Route::get('settings/smtp-test', [SettingController::class, 'smtpTest'])->name('setting.smtp.test');
        Route::post('settings/smtp-test', [SettingController::class, 'smtpTestMailSend'])->name('setting.smtp.testing');

        Route::get('settings/payment', [SettingController::class, 'payment'])->name('setting.payment');
        Route::post('settings/payment', [SettingController::class, 'paymentData']);

        Route::get('settings/company', [SettingController::class, 'company'])->name('setting.company');
        Route::post('settings/company', [SettingController::class, 'companyData']);

        Route::post('theme/settings', [SettingController::class, 'themeSettings'])->name('theme.settings');

        Route::get('settings/site-seo', [SettingController::class, 'siteSEO'])->name('setting.site.seo');
        Route::post('settings/site-seo', [SettingController::class, 'siteSEOData']);

        Route::get('settings/google-recaptcha', [SettingController::class, 'googleRecaptcha'])->name('setting.google.recaptcha');
        Route::post('settings/google-recaptcha', [SettingController::class, 'googleRecaptchaData']);

        Route::get('settings/branding', [SettingController::class, 'branding'])->name('setting.branding');
        Route::post('settings/branding', [SettingController::class, 'brandingData']);

        Route::post('settings/store-signature', [SettingController::class, 'storeSignature'])->name('AdminSignature.store');
    }
);
Route::get('language/{lang}', [SettingController::class, 'languageChange'])->name('language.change');
// Route::put('settings/update-signature', [SettingController::class, 'updateSignature']);
// Route::delete('settings/delete-signature', [SettingController::class, 'deleteSignature']);

//-------------------------------Role & Permissions-------------------------------------------
Route::resource('permission', PermissionController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);

Route::resource('role', RoleController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);

//-------------------------------logged History-------------------------------------------

Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {

        Route::get('logged/history', [UserController::class, 'loggedHistory'])->name('logged.history');
        Route::get('logged/{id}/history/show', [UserController::class, 'loggedHistoryShow'])->name('logged.history.show');
        Route::delete('logged/{id}/history', [UserController::class, 'loggedHistoryDestroy'])->name('logged.history.destroy');

    }
);

//-------------------------------driver-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {

        Route::get('driver/new/create', [DriverController::class, 'newCreate'])->name('driver.new.create');
        // Blacklist actions (BAN-252) — {user} is the driver's user id. Declared
        // before the resource so the literal paths aren't shadowed by {driver}.
        Route::post('driver/{user}/blacklist', [DriverController::class, 'blacklist'])->name('driver.blacklist');
        Route::post('driver/{user}/unblacklist', [DriverController::class, 'unblacklist'])->name('driver.unblacklist');
        Route::resource('driver', DriverController::class);
    }
);

//-------------------------------Vehicle Type-------------------------------------------
Route::resource('vehicle-type', VehicleTypeController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);
//-------------------------------Vehicle-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {

        Route::get('vehicle/rate/calculation', [VehicleController::class, 'getVehicleRateCalculation'])->name('vehicle.rate.calculation');
        Route::get('vehicle/available', [VehicleController::class, 'getAvailableVehicle'])->name('available.vehicle');
        Route::resource('vehicle', VehicleController::class);
    }
);

//-------------------------------Inspection-------------------------------------------
Route::resource('inspection', InspectionController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);

//-------------------------------Inspection Type-------------------------------------------
Route::resource('inspection-type', InspectionTypeController::class)->middleware(
    [
        'auth',
        'XSS',
    ]
);

//-------------------------------Booking-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {

        Route::get('planning', [BookingController::class, 'planning'])->name('planning');
        Route::get('booking/{id}/payment/create', [BookingController::class, 'paymentCreate'])->name('booking.payment.create');
        Route::post('booking/{id}/payment/store', [BookingController::class, 'paymentStore'])->name('booking.payment.store');
        Route::post('booking/{id}/payment/split-preview', [BookingController::class, 'paymentSplitPreview'])->name('booking.payment.split-preview');
        Route::delete('booking/{id}/payment/{pid}/destroy', [BookingController::class, 'paymentDestroy'])->name('booking.payment.destroy');
        Route::post('booking/import', [BookingController::class, 'importExcel'])->name('booking.import');
        Route::get('booking/template/download', [BookingController::class, 'downloadTemplate'])->name('booking.template');
        Route::post('booking/bulk-destroy', [BookingController::class, 'bulkDestroy'])->name('booking.bulk-destroy');
        Route::post('booking/bulk-mark-paid', [BookingController::class, 'bulkMarkPaid'])->name('booking.bulk-mark-paid');
        // Must precede the resource route so the literal path isn't captured by booking/{booking} (show).
        Route::get('booking/matching-ids', [BookingController::class, 'matchingIds'])->name('booking.matching-ids');
        Route::resource('booking', BookingController::class);
        Route::post('/booking_requests/{id}/approve', [RequestBookingController::class, 'confirmBooking'])->name('booking_requests.approve');
        Route::post('/booking_requests/{id}/refuse', [RequestBookingController::class, 'refuseBooking'])
     ->name('booking_requests.refuse');

    }
);

Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {

        Route::resource('expense', ExpenseController::class);
    }
);
//-------------------------------Traffic Violation---------------------------------
// Optional feature (BAN-260): `feature:traffic_violations` 404s the whole module
// for clients that do not have it enabled, rather than half-rendering it.
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
            'feature:traffic_violations',
        ],
    ],
    function () {

        // Declared before the resource so the literal segments are not
        // swallowed by its {traffic_violation} parameter.
        Route::post('traffic-violation/import', [TrafficViolationController::class, 'importExcel'])
            ->name('traffic-violation.import');
        Route::get('traffic-violation/template/download', [TrafficViolationController::class, 'downloadTemplate'])
            ->name('traffic-violation.template');

        Route::post('traffic-violation/{traffic_violation}/rematch', [TrafficViolationController::class, 'rematch'])
            ->name('traffic-violation.rematch');
        Route::post('traffic-violation/{traffic_violation}/assign', [TrafficViolationController::class, 'assign'])
            ->name('traffic-violation.assign');
        Route::post('traffic-violation/{traffic_violation}/status', [TrafficViolationController::class, 'status'])
            ->name('traffic-violation.status');

        Route::resource('traffic-violation', TrafficViolationController::class);
    }
);
//-------------------------------Option-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {

        Route::resource('option', OptionController::class);
    }
);
//-------------------------------Addon-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {

        Route::get('addon/rate/calculation', [AddonController::class, 'getAddonRateCalculation'])->name('addon.rate.calculation');
        // reduction function
        Route::get('addon/rate/reduction', [AddonController::class, 'getReductionRateCalculation'])->name('addon.rate.reduction');
        Route::resource('addon', AddonController::class);
    }
);
//-------------------------------Place-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {
        Route::get('place/rate/calculation', [PlaceController::class, 'getPlaceRateCalculation'])->name('place.rate.calculation');
        Route::resource('place', PlaceController::class);
    }
);

//-------------------------------Expense Type-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {
        Route::resource('expense-type', ExpenseTypeController::class);
    }
);
//-------------------------------Rental Agreement-------------------------------------------
Route::group(
    [
        'middleware' => [
            'auth',
            'XSS',
        ],
    ],
    function () {
        Route::resource('rental-agreement', RentalAgreementController::class);
    }
);

//-------------------------------Notification-------------------------------------------
Route::resource('notification', NotificationController::class)->middleware(
    [
        'auth',
        'XSS',

    ]
);

Route::get('email-verification/{token}', [VerifyEmailController::class, 'verifyEmail'])->name('email-verification')->middleware(
    [
        'XSS',
    ]
);
//new route for reminder and reminder_type tables

//--------------------------------Reminder Types--------------------------------
// Route::group([
//     'middleware' => [
//         'auth',
//         'XSS',
//     ],
// ], function () {
//     Route::resource('reminder-type', ReminderTypeController::class);
// });

//--------------------------------Reminders--------------------------------
// Route::group([
//     'middleware' => [
//         'auth',
//         'XSS',
//     ],
// ], function () {
//     Route::resource('reminder', ReminderController::class);
// });
// Route::get('reminder/days-remaining/{reminder}', [ReminderController::class, 'getDaysRemaining'])->name('reminder.days-remaining');

Route::middleware(['auth'])->group(function () {
    Route::resource('reminder-type', ReminderTypeController::class);
    // Existing reminder routes...
    Route::resource('reminder', ReminderController::class);

    // New automatic reminder routes
    Route::get('/reminder/dashboard/data', [ReminderController::class, 'getDashboardData'])->name('reminder.dashboard.data');
    Route::get('/reminder/urgent/list', [ReminderController::class, 'getUrgentReminders'])->name('reminder.urgent.list');
    Route::get('/reminder/vehicle/{vehicle}', [ReminderController::class, 'getVehicleReminders'])->name('reminder.vehicle');
    Route::get('/reminder/statistics/data', [ReminderController::class, 'getReminderStatistics'])->name('reminder.statistics');
    Route::post('/reminder/{reminder}/complete', [ReminderController::class, 'markAsCompleted'])->name('reminder.complete');
    Route::post('/reminder/{reminder}/snooze', [ReminderController::class, 'snoozeReminder'])->name('reminder.snooze');

    // Manual update routes (for testing)
    Route::post('/reminder/update-statuses', [ReminderController::class, 'updateReminderStatuses'])->name('reminder.update.statuses');
    Route::post('/reminder/create-recurring', [ReminderController::class, 'createRecurringReminders'])->name('reminder.create.recurring');
});

//--------------------------------TVA--------------------------------
Route::group([
    'middleware' => [
        'auth',
        'XSS',
    ],
], function () {
    Route::prefix('tva/renumber')->name('tva.renumber.')->group(function () {
        Route::get('/',        [TvaRenumberController::class, 'index'])->name('index');
        Route::post('/apply',  [TvaRenumberController::class, 'apply'])->name('apply');
        Route::get('/preview', [TvaRenumberController::class, 'previewJson'])->name('preview');
    });

    Route::resource('tva', TvaController::class);
    Route::get('/tva-report', [TvaController::class, 'report'])->name('tva.report');
    Route::post('/tva/bulk-download', [TvaController::class, 'bulkDownload'])->name('tva.bulk.download');
    // genere tva par mois
    Route::post('/tva/generate', [TvaController::class, 'generateMonthlyTva'])->name('tva.generate');
});

//--------------------------------Credits--------------------------------
Route::group([
    'middleware' => [
        'auth',
        'XSS',
    ],
], function () {
    Route::get('credit', [CreditController::class, 'index'])->name('credit.index');
    Route::get('credit/create', [CreditController::class, 'create'])->name('credit.create');
    Route::post('credit', [CreditController::class, 'store'])->name('credit.store');
    Route::get('credit/{credit}', [CreditController::class, 'show'])->name('credit.show');
    Route::get('credit/{credit}/edit', [CreditController::class, 'edit'])->name('credit.edit');
    Route::put('credit/{credit}', [CreditController::class, 'update'])->name('credit.update');
    Route::delete('credit/{credit}', [CreditController::class, 'destroy'])->name('credit.destroy');
    Route::get('credit/search-drivers', [CreditController::class, 'searchDrivers'])->name('credit.search.drivers');
    Route::get('credit/driver-credit/{driver_id}', [CreditController::class, 'getDriverCredit'])->name('credit.driver.details');
});

//--------------------------------Signatures--------------------------------
Route::group([
    'middleware' => [
        'auth',
        'XSS',
    ],
], function () {
    Route::get('signature', [SignatureController::class, 'index'])->name('signature.index');
    Route::get('signature/create', [SignatureController::class, 'create'])->name('signature.create');
    Route::post('signature-pad', [SignatureController::class, 'store'])->name('signature.store');
    Route::delete('signature/{signature}', [SignatureController::class, 'destroy'])->name('signature.destroy');
});

Route::get('/drivers/search', [App\Http\Controllers\RentalAgreementController::class, 'searchDrivers'])->name('drivers.search');

// --------------------------------------------------------------------------
// Sentry smoke-test — local env only, any authenticated user.
// Throws a deliberate exception; verify it arrives in Sentry within ~1 minute.
// Remove (or keep — it's harmless) once Sentry is confirmed working.
// --------------------------------------------------------------------------
if (app()->environment('local')) {
    Route::get('/sentry-test', function () {
        throw new \RuntimeException('Sentry smoke-test — intentional exception from /sentry-test');
    })->middleware('auth')->name('sentry.test');
}

// --------------------------------------------------------------------------
// UI COMPONENT TEST ROUTES (temporary for style / JS debugging)
// Remove before production deployment.
// --------------------------------------------------------------------------
Route::prefix('ui-test')->name('ui.test.')->group(function () {
    Route::view('/', 'client.tests.index')->name('index');
    Route::view('/hero', 'client.tests.hero')->name('hero');
    Route::view('/pickup', 'client.tests.pickup')->name('pickup');
    Route::view('/feature-benefit', 'client.tests.feature-benefit')->name('feature');
    Route::view('/about', 'client.tests.about')->name('about');
    Route::view('/car-rentals', 'client.tests.car-rentals')->name('car_rentals');
    Route::view('/car-service', 'client.tests.car-service')->name('car_service');
    Route::view('/funfact', 'client.tests.funfact')->name('funfact');
    Route::view('/popular-cars', 'client.tests.popular-cars')->name('popular_cars');
    Route::view('/testimonials', 'client.tests.testimonials')->name('testimonials');
    Route::view('/gallery', 'client.tests.gallery')->name('gallery');
    Route::view('/news', 'client.tests.news')->name('news');
    Route::view('/cta-rental', 'client.tests.cta-rental')->name('cta_rental');
    Route::view('/cta-cheap-rental', 'client.tests.cta-cheap-rental')->name('cta_cheap_rental');
    Route::view('/full', 'client.home')->name('full'); // full landing page

});
    Route::get('/car/{id}', [RequestBookingController::class, 'showSimilarCars'])->name('client.details');
    Route::post('/booking_request', [RequestBookingController::class, 'storeBooking'])->name('booking.store_request');
    Route::resource('booking_requests', RequestBookingController::class);

// BAN-51 smoke-test — remove after Hello.tsx is verified
Route::get('/hello', fn () => Inertia::render('Hello'))->name('inertia.hello');

