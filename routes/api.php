<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CourtController;
use App\Http\Controllers\ProfileController;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/image', [ProfileController::class, 'uploadImage']);

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
});
