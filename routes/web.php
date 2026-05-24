<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExchangeRequestController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShipmentWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/healthz', function () {
    \App\Support\MailConfigurator::apply();

    return response()->json([
        'ok' => true,
        'app' => config('app.name'),
        'time' => now()->toIso8601String(),
        'build_manifest' => file_exists(public_path('build/manifest.json')),
        'mail' => [
            'mailer' => config('mail.default'),
            'sendgrid_configured' => \App\Support\MailConfigurator::usesApiMailer(),
            'from' => config('mail.from.address'),
        ],
    ]);
});

Route::get('/healthz/diag', function () {
    try {
        $html = view('auth.login', ['prefillEmail' => ''])->render();

        return response()->json([
            'ok' => true,
            'rendered_bytes' => strlen($html),
        ]);
    } catch (\Throwable $exception) {
        return response()->json([
            'ok' => false,
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ], 500);
    }
});

Route::get('/', [ItemController::class, 'landing'])->name('home');
Route::get('/explore', [ItemController::class, 'index'])->name('items.index');
Route::get('/add-item', [ItemController::class, 'create'])->name('items.create');
Route::get('/items/{item}', [ItemController::class, 'show'])->name('items.show');
Route::post('/items', [ItemController::class, 'store'])->name('items.store');
Route::get('/my-items', [ItemController::class, 'myItems'])->middleware('auth')->name('items.my');
Route::get('/my-dashboard', [ItemController::class, 'myDashboard'])->middleware('auth')->name('items.dashboard');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/my-exchanges', [ExchangeRequestController::class, 'index'])->name('exchanges.index');
Route::post('/demo/generate-exchange-data', [ExchangeRequestController::class, 'generateDemoData'])
    ->middleware('throttle:2,10')
    ->name('demo.generate-exchange-data');
Route::post('/items/{item}/request', [ExchangeRequestController::class, 'store'])
    ->middleware('throttle:12,1')
    ->name('exchanges.store');
Route::post('/items/{item}/chat-start', [ExchangeRequestController::class, 'startChat'])
    ->middleware('throttle:20,1')
    ->name('chat.start');
Route::get('/chat/{exchangeRequest?}', [MessageController::class, 'index'])->name('chat.index');
Route::get('/api/locations/suggest', [ItemController::class, 'suggestLocations'])
    ->middleware('throttle:45,1')
    ->name('items.suggest-locations');
Route::get('/api/categories/suggest', [ItemController::class, 'suggestCategories'])
    ->middleware('throttle:45,1')
    ->name('items.suggest-categories');
Route::get('/api/locations/reverse', [ItemController::class, 'reverseGeocode'])
    ->middleware('throttle:45,1')
    ->name('items.reverse-location');
Route::post('/saved-searches', [ItemController::class, 'saveSearch'])
    ->middleware('throttle:20,1')
    ->name('saved-searches.store');
Route::delete('/saved-searches/{savedSearch}', [ItemController::class, 'deleteSavedSearch'])
    ->middleware('throttle:20,1')
    ->name('saved-searches.destroy');
Route::post('/webhooks/shipping/{provider}', ShipmentWebhookController::class)->name('webhooks.shipping');
Route::post('/webhooks/payments/razorpay', [PaymentController::class, 'webhookRazorpay'])->name('webhooks.payments.razorpay');
Route::match(['get', 'post'], '/payments/orders/{order}/razorpay/callback', [PaymentController::class, 'razorpayCallback'])->name('payments.razorpay-callback');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::patch('/exchanges/{exchangeRequest}/status', [ExchangeRequestController::class, 'updateStatus'])->name('exchanges.update-status');
    Route::patch('/exchanges/{exchangeRequest}/confirm', [ExchangeRequestController::class, 'confirm'])->name('exchanges.confirm');
    Route::patch('/exchanges/{exchangeRequest}/shipment-request', [ExchangeRequestController::class, 'requestShipmentProcess'])->name('exchanges.shipment-request');
    Route::patch('/exchanges/{exchangeRequest}/shipment-approve', [ExchangeRequestController::class, 'approveShipmentProcess'])->name('exchanges.shipment-approve');
    Route::get('/exchanges/{exchangeRequest}/deal-terms', [ExchangeRequestController::class, 'dealTerms'])->name('exchanges.deal-terms');
    Route::post('/exchanges/{exchangeRequest}/deal-terms', [ExchangeRequestController::class, 'dealTermsStore'])->name('exchanges.deal-terms.store');
    Route::get('/exchanges/{exchangeRequest}', [ExchangeRequestController::class, 'show'])->name('exchanges.show');

    Route::post('/chat/{exchangeRequest}', [MessageController::class, 'store'])->name('chat.store');
    Route::post('/chat/{exchangeRequest}/messages/{message}/delete', [MessageController::class, 'delete'])->name('chat.message.delete');
    Route::post('/chat/{exchangeRequest}/typing', [MessageController::class, 'typing'])->name('chat.typing');
    Route::get('/chat/{exchangeRequest}/presence', [MessageController::class, 'presence'])->name('chat.presence');
    Route::get('/chat/{exchangeRequest}/updates', [MessageController::class, 'updates'])->name('chat.updates');
    Route::get('/notifications/summary', [MessageController::class, 'notificationSummary'])->name('notifications.summary');
    Route::post('/chat/{exchangeRequest}/report', [MessageController::class, 'report'])->name('chat.report');
    Route::post('/chat/{exchangeRequest}/block', [MessageController::class, 'block'])->name('chat.block');

    Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');
    Route::patch('/shipments/{shipment}/status', [ShipmentController::class, 'updateStatus'])->name('shipments.update-status');
    Route::post('/shipments/{shipment}/schedule-pickup', [ShipmentController::class, 'schedulePickup'])->name('shipments.schedule-pickup');
    Route::post('/shipments/{shipment}/simulate-event', [ShipmentController::class, 'simulateEvent'])->name('shipments.simulate-event');
    Route::post('/shipments/{shipment}/payment/initiate', [ShipmentController::class, 'initiatePayment'])->name('shipments.initiate-payment');
    Route::post('/shipments/{shipment}/otp/generate', [ShipmentController::class, 'generateDeliveryOtp'])->name('shipments.generate-otp');
    Route::post('/shipments/{shipment}/otp/verify', [ShipmentController::class, 'verifyDeliveryOtp'])->name('shipments.verify-otp');
    Route::get('/payments/orders/{order}/checkout', [PaymentController::class, 'checkout'])->name('payments.checkout');
    Route::post('/payments/orders/{order}/init-razorpay', [PaymentController::class, 'initRazorpay'])->name('payments.init-razorpay');
    Route::post('/payments/orders/{order}/pay', [PaymentController::class, 'pay'])->name('payments.pay');

});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
Route::put('/items/{item}', [ItemController::class, 'update'])->name('items.update');
Route::delete('/items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
});

Route::get('/run-migration', function () {
    \Artisan::call('migrate', ['--force' => true]);
    return "Migration completed";
});

require __DIR__.'/auth.php';
