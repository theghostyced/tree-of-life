<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\InvitationImportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\InvitationAcceptanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Entrepreneur\DashboardController as EntrepreneurDashboardController;
use App\Http\Controllers\Entrepreneur\EmployeeInvitationController;
use App\Http\Controllers\Entrepreneur\ProfileController as EntrepreneurProfileController;
use App\Http\Controllers\Mentor\DashboardController as MentorDashboardController;
use App\Http\Controllers\Mentor\ProfileController as MentorProfileController;
use App\Http\Controllers\Onboarding\DocumentController;
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
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/invitations', [InvitationController::class, 'index'])->name('invitations.index');
        Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
        Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
        Route::delete('/invitations/{invitation}', [InvitationController::class, 'revoke'])->name('invitations.revoke');
        Route::get('/invitations/import/template', [InvitationImportController::class, 'template'])->name('invitations.import.template');
        Route::post('/invitations/import', [InvitationImportController::class, 'store'])->name('invitations.import.store');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::post('/users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::prefix('entrepreneur')->name('entrepreneur.')->middleware('role:entrepreneur')->group(function () {
        Route::get('/onboarding', [EntrepreneurProfileController::class, 'edit'])->name('onboarding');
        Route::get('/dashboard', [EntrepreneurDashboardController::class, 'index'])->middleware('account.active')->name('dashboard');
        Route::patch('/profile', [EntrepreneurProfileController::class, 'update'])->name('profile.update');
        Route::post('/employees', [EmployeeInvitationController::class, 'store'])->name('employees.store');
    });

    Route::prefix('mentor')->name('mentor.')->middleware('role:mentor')->group(function () {
        Route::get('/onboarding', [MentorProfileController::class, 'edit'])->name('onboarding');
        Route::get('/dashboard', [MentorDashboardController::class, 'index'])->middleware('account.active')->name('dashboard');
        Route::patch('/profile', [MentorProfileController::class, 'update'])->name('profile.update');
    });

    /*
     * Onboarding: profile document uploads (entrepreneurs and mentors). Profile
     * fields are saved incrementally via each role's profile.update route.
     */
    Route::post('/onboarding/documents', [DocumentController::class, 'store'])->name('onboarding.documents.store');
    Route::get('/onboarding/documents/{document}', [DocumentController::class, 'show'])->name('onboarding.documents.show');
});
