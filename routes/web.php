<?php

use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Auth\InvitationAcceptanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Entrepreneur\EmployeeInvitationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

/*
 * Guest authentication + invitation acceptance (token-gated, no session required).
 */
Route::inertia('/login', 'auth/Login')->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::get('/invitations/accept/{token}', [InvitationAcceptanceController::class, 'show'])
    ->name('invitations.accept.show');
Route::post('/invitations/accept/{token}', [InvitationAcceptanceController::class, 'store'])
    ->name('invitations.accept.store');

/*
 * Authenticated areas, gated by role and (for full capabilities) account status.
 */
Route::middleware('auth')->group(function () {
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::inertia('/dashboard', 'admin/Dashboard')->name('dashboard');
        Route::inertia('/invitations', 'admin/invitations/Index')->name('invitations.index');
        Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
    });

    Route::prefix('entrepreneur')->name('entrepreneur.')->middleware('role:entrepreneur')->group(function () {
        Route::inertia('/onboarding', 'entrepreneur/Onboarding')->name('onboarding');
        Route::inertia('/dashboard', 'entrepreneur/Dashboard')->middleware('account.active')->name('dashboard');
        Route::post('/employees', [EmployeeInvitationController::class, 'store'])->name('employees.store');
    });

    Route::prefix('mentor')->name('mentor.')->middleware('role:mentor')->group(function () {
        Route::inertia('/onboarding', 'mentor/Onboarding')->name('onboarding');
        Route::inertia('/dashboard', 'mentor/Dashboard')->middleware('account.active')->name('dashboard');
    });
});

// TEMP (remove after QA): unguarded render of the invitations UI for a screenshot.
Route::inertia('/__preview/invitations', 'admin/invitations/Index');
