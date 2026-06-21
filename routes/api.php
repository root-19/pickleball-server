<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PayoutAccountController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CourtController;
use App\Http\Controllers\FavoriteCourtController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\HelpCenterController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OwnerNotificationController;
use App\Http\Controllers\OpenPlayQueueController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\StaffController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password/check-email', [AuthController::class, 'checkEmailForReset']);
Route::post('/forgot-password/reset', [AuthController::class, 'resetPassword']);

Route::get('/payments/callback/success', [PaymentController::class, 'callbackSuccess']);
Route::get('/payments/callback/failed',  [PaymentController::class, 'callbackFailed']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/image', [ProfileController::class, 'uploadImage']);
    Route::get('/profile/stats', [ProfileController::class, 'stats']);

    Route::get('/courts/browse', [CourtController::class, 'browse'])->withoutMiddleware(['auth:sanctum']);
    Route::get('/courts/{id}/booked-slots', [BookingController::class, 'getCourtBookedSlots'])->withoutMiddleware(['auth:sanctum']);
    Route::apiResource('/courts', CourtController::class);
    Route::get('/owner/calendar', [CalendarController::class, 'ownerWeek']);
    Route::apiResource('/staff', StaffController::class)->except(['show']);

    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/owner/dashboard-stats', [BookingController::class, 'ownerStats']);
    Route::get('/owner/bookings', [BookingController::class, 'ownerBookings']);
    Route::get('/owner/earnings', [BookingController::class, 'ownerEarnings']);

    Route::get('/favorites', [FavoriteCourtController::class, 'index']);
    Route::get('/favorites/check/{courtId}', [FavoriteCourtController::class, 'check']);
    Route::post('/favorites/{courtId}', [FavoriteCourtController::class, 'store']);
    Route::delete('/favorites/{courtId}', [FavoriteCourtController::class, 'destroy']);

    Route::get('/messages/conversations', [MessageController::class, 'conversations']);
    Route::get('/messages', [MessageController::class, 'index']);
    Route::post('/messages', [MessageController::class, 'store']);

    Route::get('/help-center', [HelpCenterController::class, 'index']);
    Route::post('/help-center', [HelpCenterController::class, 'store']);

    Route::get('/marketplace', [MarketplaceController::class, 'index']);
    Route::get('/marketplace/reels', [MarketplaceController::class, 'reels']);
    Route::get('/marketplace/my-posts', [MarketplaceController::class, 'myPosts']);
    Route::post('/marketplace', [MarketplaceController::class, 'store']);
    Route::post('/marketplace/{id}/view', [MarketplaceController::class, 'incrementView']);
    Route::post('/marketplace/{id}/heart', [MarketplaceController::class, 'toggleHeart']);
    Route::post('/marketplace/{id}', [MarketplaceController::class, 'update']);
    Route::delete('/marketplace/{id}', [MarketplaceController::class, 'destroy']);

    Route::post('/open-play/join', [OpenPlayQueueController::class, 'joinQueue']);
    Route::post('/open-play/payment', [OpenPlayQueueController::class, 'processPayment']);
    Route::get('/open-play/status', [OpenPlayQueueController::class, 'getQueueStatus']);
    Route::post('/open-play/cancel', [OpenPlayQueueController::class, 'cancelQueue']);
    Route::get('/open-play/courts/{courtId}/queues', [OpenPlayQueueController::class, 'getCourtQueues']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    Route::get('/owner/notifications', [OwnerNotificationController::class, 'index']);
    Route::post('/owner/notifications/{id}/read', [OwnerNotificationController::class, 'markRead']);

    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::get('/reviews/booking/{bookingId}', [ReviewController::class, 'show']);

    Route::get('/leaderboard', [LeaderboardController::class, 'index']);

    Route::get('/payout-accounts',                [PayoutAccountController::class, 'index']);
    Route::post('/payout-accounts',               [PayoutAccountController::class, 'store']);
    Route::post('/payout-accounts/{id}/primary',  [PayoutAccountController::class, 'setPrimary']);
    Route::delete('/payout-accounts/{id}',        [PayoutAccountController::class, 'destroy']);

    Route::post('/payments/create-source', [PaymentController::class, 'createSource']);
    Route::post('/payments/verify',        [PaymentController::class, 'verify']);
    Route::post('/payments/create-qrph',   [PaymentController::class, 'createQrPh']);
    Route::post('/payments/verify-intent', [PaymentController::class, 'verifyIntent']);

    });
