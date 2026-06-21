<?php

use App\Http\Controllers\Admin\AdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

// Admin auth
Route::get('/admin/login',   [AdminController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login',  [AdminController::class, 'login'])->name('admin.login.post');
Route::post('/admin/logout', [AdminController::class, 'logout'])->name('admin.logout');

// Admin protected routes
Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard',          [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users',              [AdminController::class, 'users'])->name('users');
    Route::get('/users/{id}',         [AdminController::class, 'showUser'])->name('users.show');
    Route::delete('/users/{id}',      [AdminController::class, 'destroyUser'])->name('users.destroy');
    Route::get('/owners',                          [AdminController::class, 'owners'])->name('owners');
    Route::get('/owners/{id}',                     [AdminController::class, 'showOwner'])->name('owners.show');
    Route::get('/owners/{id}/payout',              [AdminController::class, 'createPayout'])->name('payouts.create');
    Route::post('/owners/{id}/payout',             [AdminController::class, 'storePayout'])->name('payouts.store');
    Route::get('/payouts',                         [AdminController::class, 'payouts'])->name('payouts');
    Route::post('/payouts/{id}/status',            [AdminController::class, 'updatePayoutStatus'])->name('payouts.status');
});
