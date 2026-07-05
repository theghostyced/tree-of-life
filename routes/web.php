<?php

use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\UserReviewController;
use App\Http\Controllers\Auth\InvitationAcceptanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Entrepreneur\DashboardController as EntrepreneurDashboardController;
use App\Http\Controllers\Entrepreneur\EmployeeInvitationController;
use App\Http\Controllers\Entrepreneur\ProfileController as EntrepreneurProfileController;
use App\Http\Controllers\Mentor\DashboardController as MentorDashboardController;
use App\Http\Controllers\Mentor\ProfileController as MentorProfileController;
use App\Http\Controllers\Onboarding\DocumentController;
use App\Http\Controllers\Onboarding\SubmissionController;
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
        Route::get('/invitations', [InvitationController::class, 'index'])->name('invitations.index');
        Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
        Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
        Route::delete('/invitations/{invitation}', [InvitationController::class, 'revoke'])->name('invitations.revoke');
        Route::post('/users/{user}/approve', [UserReviewController::class, 'approve'])->name('users.approve');
        Route::post('/users/{user}/reject', [UserReviewController::class, 'reject'])->name('users.reject');
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
     * Onboarding: profile documents and submission for review (entrepreneurs and mentors).
     */
    Route::post('/onboarding/documents', [DocumentController::class, 'store'])->name('onboarding.documents.store');
    Route::get('/onboarding/documents/{document}', [DocumentController::class, 'show'])->name('onboarding.documents.show');
    Route::post('/onboarding/submit', [SubmissionController::class, 'store'])->name('onboarding.submit');
});
