<?php

use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\AgentTravellerController;
use App\Http\Controllers\Api\AirlineController;
use App\Http\Controllers\Api\AirportController;
use App\Http\Controllers\Api\B2CValueController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\CmsController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerMarginController;
use App\Http\Controllers\Api\DepositController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\FlightController;
use App\Http\Controllers\Api\FlyDubaiController;
use App\Http\Controllers\Api\GroupTicketController;
use App\Http\Controllers\Api\ModifyRequestController;
use App\Http\Controllers\Api\OfflineBookingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PromoImageController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\SegmentMarginController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VisaController;
use App\Http\Controllers\Api\HolidayPackageController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\UmrahPackageController;
use App\Http\Controllers\Api\AgentDataController;
use App\Http\Controllers\Api\DepositDataController;
use App\Http\Controllers\Api\AgentLedgerController;
use App\Http\Controllers\Api\ZohoController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\NotificationController;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// Register broadcasting routes for API clients (token-based authentication)
Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::post('/login/request-otp', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'requestLoginOtp']);
Route::post('/login/verify-otp', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'verifyLoginOtp']);



Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user()->load('customer');
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::resource('users', UserController::class);
});


Route::middleware(['auth:sanctum', 'log.route'])->group(function () {





    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::delete('/delete-activity-logs', [ActivityLogController::class, 'delete']);


    Route::get('uploads', action: [FileUploadController::class, 'index']);
    Route::post('uploads', [FileUploadController::class, 'store']);
    Route::delete('uploads/{file_name}', [FileUploadController::class, 'destroy']);

    Route::get('countries', [CountryController::class, 'index']);

    Route::resource('cities', CityController::class);
    Route::get('users-summary', [UserController::class, 'UsersSummary']);
    Route::post('/add-staff', [UserController::class, 'saveStaff']);
    Route::post('/update-staff', [UserController::class, 'updateStaff']);

    Route::put('update-status', [UserController::class, 'updateStatus']);
    Route::post('update-card-allowance', [UserController::class, 'updateCardAllowance']);
    Route::post('add-agent-data', [UserController::class, 'storAgentData']);

    Route::post('/agents', [AgentDataController::class, 'store']);
    Route::post('/save-admin-agent', [AgentDataController::class, 'saveAdminAgent']);

    Route::get('agentsData', [AgentDataController::class, 'show']);
    Route::post('update-agents-data', [AgentDataController::class, 'update']);
    Route::post('/deposit-data', [DepositDataController::class, 'store']);
    Route::get('/get-deposit-data', [DepositDataController::class, 'getAgentDeposits']);
    Route::delete('delete-deposit-data', [DepositDataController::class, 'destroy']);
    Route::post('/save-agent-margin', [AgentDataController::class, 'setAgentMargin']);
    Route::post('/save-agent-charges', [AgentDataController::class, 'setAgentCharges']);
    Route::post('/update-agent-charge-status', [AgentDataController::class, 'updateAgentChargeStatus']);
    Route::get('/show-all-charges', [AgentDataController::class, 'showAllCharges']);

    Route::get('/get-agents-deposits', [DepositDataController::class, 'getAllDepositsWithAgentData']);
    Route::get('/deposit-details', [DepositDataController::class, 'getDepositDetails']);
    Route::put('/update-deposit-status', [DepositDataController::class, 'updateDepositStatus']);
    Route::get('/approved-deposits-total', [DepositDataController::class, 'getApprovedDepositsTotal']);

    Route::get('flights', [FlightController::class, 'index']);
    Route::get('flight/{id}/{supplier}/{isSooperFlight}', [FlightController::class, 'show']);
    Route::get('flight-quotation', [FlightController::class, 'sendQuotation']);
    Route::get('booking-details', [FlightController::class, 'getBookingDetails']);

    Route::post('updateAirlineMargin', [AirlineController::class, 'updateAirline']);
    Route::post('airlines', [AirlineController::class, 'store']);
    Route::delete('airlines', [AirlineController::class, 'destroy']);

    Route::post('save-airport-margins', [AirportController::class, 'storeMargins']);

    Route::delete('delete-offline-booking', [OfflineBookingController::class, 'destroy']);
    Route::get('get-offline-booking-details', [OfflineBookingController::class, 'getFlightBookingDetails']);
    Route::get('get-offline-bookings', [OfflineBookingController::class, 'index']);
    Route::post('update-offline-booking', [OfflineBookingController::class, 'update']);
    Route::post('offline-bookings', [OfflineBookingController::class, 'store']);
    Route::post('send-offline-booking-email', [OfflineBookingController::class, 'sendEmail']);


    Route::post('bookings', [BookingController::class, 'store']);
    Route::get('get-bookings', [BookingController::class, 'index']);
    Route::get('get-flight-booking-details', [BookingController::class, 'getBookingDetails']);
    // Route::post('cancel-pnr', [BookingController::class, 'cancelPnrDetails']);
    Route::post('void-booking', [BookingController::class, 'voidBooking']);
    Route::post('void-request', [BookingController::class, 'voidRequest']);
    Route::post('approve-booking', [BookingController::class, 'approveBooking']);
    Route::post('admin-booking', [BookingController::class, 'saveAdminBooking']);
    Route::get('get-admin-bookings', [BookingController::class, 'getAdminBookings']);
    Route::get('get-admin-booking', [BookingController::class, 'getAdminBooking']);

    Route::post('send-price-request', [BookingController::class, 'sendPriceRequest']);
    Route::get('get-customer-bookings', [BookingController::class, 'getCustomerBookings']);

    Route::get('agent-statment', [AgentLedgerController::class, 'index']);
    Route::get('profit-loss-report', [AgentLedgerController::class, 'profitLossReport']);

    Route::get('transactions', [TransactionController::class, 'index']);
    Route::post('transactions', [TransactionController::class, 'store']);
    Route::put('transactions', [TransactionController::class, 'update']);

    Route::resource('deposits', DepositController::class);

    Route::post('currencies', [CurrencyController::class, 'store']);
    Route::post('update-currencies', [CurrencyController::class, 'update']);
    Route::delete('currencies', [CurrencyController::class, 'destroy']);

    Route::get('banks', [BankController::class, 'index']);
    Route::post('banks', [BankController::class, 'store']);
    Route::put('banks', [BankController::class, 'update']);
    Route::delete('banks', [BankController::class, 'destroy']);

    Route::post('visas', [VisaController::class, 'store']);
    Route::put('visas', [VisaController::class, 'update']);
    Route::delete('visas', [VisaController::class, 'destroy']);
    Route::post('visa-header-images', [VisaController::class, 'saveVisaHeaderImages']);
    Route::delete('visa-header-images', [VisaController::class, 'deleteVisaHeaderImages']);

    Route::post('holidays', [HolidayPackageController::class, 'store']);
    Route::put('holidays', [HolidayPackageController::class, 'update']);
    Route::delete('holidays', [HolidayPackageController::class, 'destroy']);
    Route::post('holiday-header-images', [HolidayPackageController::class, 'saveHolidayHeaderImages']);
    Route::delete('holiday-header-images', [HolidayPackageController::class, 'deleteHolidayHeaderImages']);

    Route::post('umrah-packages', [UmrahPackageController::class, 'store']);
    Route::patch('umrah-packages', [UmrahPackageController::class, 'update']);
    Route::delete('umrah-packages', [UmrahPackageController::class, 'destroy']);
    Route::post('umrah-header-images', [UmrahPackageController::class, 'saveUmrahHeaderImages']);
    Route::delete('umrah-header-images', [UmrahPackageController::class, 'deleteUmrahHeaderImages']);

    Route::post('group-tickets', [GroupTicketController::class, 'store']);
    Route::put('group-tickets', [GroupTicketController::class, 'update']);
    Route::delete('group-tickets', [GroupTicketController::class, 'destroy']);

    Route::post('agent-profile', [SettingsController::class, 'saveAgentProfile']);

    Route::post('save-promo-image', [PromoImageController::class, 'store']);
    Route::post('update-promo-image', [PromoImageController::class, 'updateIsHome']);
    Route::delete('delete-promo-image', [PromoImageController::class, 'destroy']);

    Route::get('get-travellers', [AgentTravellerController::class, 'index']);
    Route::post('save-travellers', [AgentTravellerController::class, 'store']);
    Route::put('update-travellers', [AgentTravellerController::class, 'update']);
    Route::delete('delete-travellers', [AgentTravellerController::class, 'destroy']);
    Route::post('assign-ticket-number', [BookingController::class, 'assignTicketNumber']);

    Route::post('update-booking', [AdminSettingsController::class, 'updateBooking']);
    Route::get('get-booking-status-settings', [AdminSettingsController::class, 'getBookiongStatusSettings']);

    Route::get('get-customer-margin-values', [CustomerMarginController::class, 'index']);
    Route::post('save-customer-margin-values', [CustomerMarginController::class, 'update']);

    Route::get('/notifications', [NotificationController::class, 'getNotifications']);
    Route::post('/notifications/read/{id}', [NotificationController::class, 'isReadNotification']);
    // Route::delete('/notifications/{id}', [NotificationController::class, 'deleteNotification']);
    Route::delete('/notifications/clear-all', [NotificationController::class, 'clearAllNotifications']);

/////////public

///


    Route::get('airports', [AirportController::class, 'index']);
    Route::get('get-promo-images', [PromoImageController::class, 'index']);
    Route::get('group-tickets', [GroupTicketController::class, 'index']);
    Route::get('umrah-packages', [UmrahPackageController::class, 'index']);
    Route::get('umrah-header-images', [UmrahPackageController::class, 'getUmrahHeaderImages']);
    Route::get('holiday-header-images', [HolidayPackageController::class, 'getHolidayHeaderImages']);
    Route::get('holidays', [HolidayPackageController::class, 'index']);
    Route::get('visas', [VisaController::class, 'index']);
    Route::get('visa-header-images', [VisaController::class, 'getVisaHeaderImages']);
    Route::get('airlines', [AirlineController::class, 'index']);
    Route::get('flight-providers', [FlightController::class, 'fetchProviders']);
    Route::post('sort-flights', [FlightController::class, 'sortFlights']);
    Route::get('flights', [FlightController::class, 'index']);
    Route::post('ancillaries', [BookingController::class, 'getAncillaries']);
    Route::post('patch-ancillaries', [BookingController::class, 'patchAncillaries']);
    Route::post('cancel-pnr', [BookingController::class, 'cancelPnrDetails']);

    Route::post('confirm-pnr', [BookingController::class, 'confirmPnr']);

    Route::get('flight/{id}/{supplier}/{isSooperFlight}', [FlightController::class, 'show']);
    Route::get('get-customer-margin-values', [CustomerMarginController::class, 'index']);
    Route::post('save-customer-margin-values', [CustomerMarginController::class, 'update']);
    Route::get('get-booking-status-settings', [AdminSettingsController::class, 'getBookiongStatusSettings']);
    Route::get('get-customer-margin-values', [CustomerMarginController::class, 'index']);

    Route::get('countries', [CountryController::class, 'index']);
    Route::post('bookings', [BookingController::class, 'store']);
    Route::get('get-customer-flight-booking-details', [BookingController::class, 'getCustomerBookingDetails']);

    Route::get('customers', [CustomerController::class, 'getCustomers']);
    Route::get('customer-data', [CustomerController::class, 'getCustomerData']);
    Route::post('update-customer-settings', [CustomerController::class, 'updateCustomerSettings']);
    Route::post('update-customer-data', [CustomerController::class, 'updateCustomerData']);

    Route::get('safepay-url', [BookingController::class, 'initiatePayment']);

    Route::post('send-payment-request', [PaymentController::class, 'createIntent']);
    Route::post('initialize-abhipay', [PaymentController::class, 'initializeAbhiPay']);
    Route::get('check-payment-status', [PaymentController::class, 'checkPaymentStatus']);

    Route::post('send-email', [BookingController::class, 'sendMail']);

    Route::post('save-keys', [ZohoController::class, 'store']);
    Route::get('fetch-keys', [ZohoController::class, 'getKeys']);
    Route::post('zoho-token', [ZohoController::class, 'getToken']);
    Route::get('zoho-organization', [ZohoController::class, 'getOrganization']);
    Route::get('zoho-contacts', [ZohoController::class, 'fetchOrCreateCustomer']);
    Route::get('create-invoice', [ZohoController::class, 'createInvoice']);
Route::get('promotions/providers', [FlightController::class, 'fetchProviders']);
Route::resource('promotions', PromotionController::class);
Route::get('segment-margins/providers', [FlightController::class, 'fetchProviders']);
Route::resource('segment-margins', SegmentMarginController::class);
    Route::get('/test-email', function () {
        Mail::raw('This is a test email', function ($message) {
            $message->to('recipient@example.com')->subject('Test Email');
        });
        return 'Email sent';
    });

});

// Route::get('/auth/google', [GoogleController::class, 'redirect']);
Route::get('airports', [AirportController::class, 'index']);
Route::post('update-booking-amount', [BookingController::class, 'updateBookingAmount']);
Route::get('get-promo-images', [PromoImageController::class, 'index']);
Route::get('airports', [AirportController::class, 'index']);
Route::get('get-promo-images', [PromoImageController::class, 'index']);
Route::get('group-tickets', [GroupTicketController::class, 'index']);
Route::get('umrah-packages', [UmrahPackageController::class, 'index']);
Route::get('umrah-header-images', [UmrahPackageController::class, 'getUmrahHeaderImages']);
Route::get('holiday-header-images', [HolidayPackageController::class, 'getHolidayHeaderImages']);
Route::get('holidays', [HolidayPackageController::class, 'index']);
Route::get('visas', [VisaController::class, 'index']);
Route::get('visa-header-images', [VisaController::class, 'getVisaHeaderImages']);
Route::get('airlines', [AirlineController::class, 'index']);
Route::get('flights', [FlightController::class, 'index']);
Route::get('flight-providers', [FlightController::class, 'fetchProviders']);
Route::post('sort-flights', [FlightController::class, 'sortFlights']);
Route::get('flight/{id}/{supplier}/{isSooperFlight}', [FlightController::class, 'show']);
Route::get('get-customer-margin-values', [CustomerMarginController::class, 'index']);
Route::post('save-customer-margin-values', [CustomerMarginController::class, 'update']);
Route::get('get-booking-status-settings', [AdminSettingsController::class, 'getBookiongStatusSettings']);
Route::get('get-customer-margin-values', [CustomerMarginController::class, 'index']);
Route::get('countries', [CountryController::class, 'index']);
Route::post('bookings', [BookingController::class, 'store']);
Route::get('get-customer-flight-booking-details', [BookingController::class, 'getCustomerBookingDetails']);
Route::post('send-sooper-quote', [BookingController::class, 'sendSooperQuote']);
Route::post('send-add-to-cart', [FlyDubaiController::class, 'AddToCart']);
Route::get('ancillaries', [BookingController::class, 'getAncillaries']);
Route::post('patch-ancillaries', [BookingController::class, 'patchAncillaries']);
Route::get('get-pnr-details', [BookingController::class, 'getPnrDetails']);
Route::get('get-pnr-data', [BookingController::class, 'getPnrData']);
Route::get('get-public-bookings', [BookingController::class, 'getPublicBookings']);
Route::get('customer-settings', [CustomerController::class, 'getCustomerSettings']);
Route::post('update-customer-type', [CustomerController::class, 'updateCustomerType']);

Route::get('safepay-url', [BookingController::class, 'initiatePayment']);
Route::get('get-airport-margins', [AirportController::class, 'getMargins']);

Route::post('send-payment-request', [PaymentController::class, 'createIntent']);
Route::post('contact-messages', [ContactController::class, 'store'])
    ->middleware('throttle:5,10')
    ->name('api/contact-messages');
Route::post('save-keys', [ZohoController::class, 'store']);
Route::get('fetch-keys', [ZohoController::class, 'getKeys']);
Route::post('zoho-token', [ZohoController::class, 'getToken']);
Route::get('zoho-organization', [ZohoController::class, 'getOrganization']);
Route::get('zoho-contacts', [ZohoController::class, 'fetchOrCreateCustomer']);
Route::get('create-invoice', [ZohoController::class, 'createInvoice']);
Route::get('segment-margins/providers', [FlightController::class, 'fetchProviders']);
Route::resource('segment-margins', SegmentMarginController::class);
Route::post('save-request', [ModifyRequestController::class, 'store']);
Route::get('fetch-requests', [ModifyRequestController::class, 'index']);
Route::get('fetch-modify-request-data', [ModifyRequestController::class, 'fetchModifyRequestData']);
Route::post('update-modify-request-status', [ModifyRequestController::class, 'updateStatus']);
Route::post('send-reply', [ModifyRequestController::class, 'addMessage']);
Route::get('currencies', [CurrencyController::class, 'index']);

Route::post('/save-popular-route', [CmsController::class, 'storePopularRoutes']);
Route::get('/fetch-popular-routes', [CmsController::class, 'getPopularRoutes']);
Route::delete('/deletePopularRoute/{id}', [CmsController::class, 'deletePopularRoutes']);

Route::post('/save-blog', [BlogController::class, 'store']);
Route::post('/update-blog', [BlogController::class, 'update']);
Route::get('/fetch-blogs', [BlogController::class, 'index']);
Route::get('/fetch-blog/{id}', [BlogController::class, 'show']);
Route::delete('/delete-blog/{id}', [BlogController::class, 'delete']);
Route::get('/blog/slugs', [BlogController::class, 'slugs']);
Route::get('/blog/{slug}', [BlogController::class, 'show']);
