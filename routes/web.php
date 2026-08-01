<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\InvitationImportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\InvitationAcceptanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Chat\ConversationMessagesController;
use App\Http\Controllers\Chat\MarkReadController;
use App\Http\Controllers\Chat\MessagesController;
use App\Http\Controllers\Chat\SendMessageController;
use App\Http\Controllers\Entrepreneur\DashboardController as EntrepreneurDashboardController;
use App\Http\Controllers\Entrepreneur\EmployeeInvitationController;
use App\Http\Controllers\Entrepreneur\MeetingController as EntrepreneurMeetingController;
use App\Http\Controllers\Entrepreneur\MentorController as EntrepreneurMentorController;
use App\Http\Controllers\Entrepreneur\PairingController as EntrepreneurPairingController;
use App\Http\Controllers\Entrepreneur\ProfileController as EntrepreneurProfileController;
use App\Http\Controllers\Mentor\AvailabilityController as MentorAvailabilityController;
use App\Http\Controllers\Mentor\DashboardController as MentorDashboardController;
use App\Http\Controllers\Mentor\MeetingController as MentorMeetingController;
use App\Http\Controllers\Mentor\MeetingReportController;
use App\Http\Controllers\Mentor\MenteeController;
use App\Http\Controllers\Mentor\ProfileController as MentorProfileController;
use App\Http\Controllers\Mentor\RescheduleController;
use App\Http\Controllers\Mentorship\MeetingRescheduleController;
use App\Http\Controllers\NotificationController;
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
        Route::post('/invitations/bulk', [InvitationController::class, 'bulk'])->name('invitations.bulk');
        Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
        Route::delete('/invitations/{invitation}', [InvitationController::class, 'revoke'])->name('invitations.revoke');
        Route::get('/invitations/import/template', [InvitationImportController::class, 'template'])->name('invitations.import.template');
        Route::post('/invitations/import', [InvitationImportController::class, 'store'])->name('invitations.import.store');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users/bulk', [UserController::class, 'bulk'])->name('users.bulk');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::post('/users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::prefix('entrepreneur')->name('entrepreneur.')->middleware('role:entrepreneur')->group(function () {
        Route::get('/onboarding', [EntrepreneurProfileController::class, 'edit'])->name('onboarding');
        Route::get('/dashboard', [EntrepreneurDashboardController::class, 'index'])->middleware('account.active')->name('dashboard');
        Route::patch('/profile', [EntrepreneurProfileController::class, 'update'])->name('profile.update');
        Route::get('/mentors', [EntrepreneurMentorController::class, 'index'])->middleware('account.active')->name('mentors.index');
        Route::get('/mentors/{mentor}', [EntrepreneurMentorController::class, 'show'])->middleware('account.active')->name('mentors.show');
        Route::post('/pairings', [EntrepreneurPairingController::class, 'store'])->middleware('account.active')->name('pairings.store');
        Route::get('/meetings', [EntrepreneurMeetingController::class, 'index'])->middleware('account.active')->name('meetings.index');
        Route::post('/meetings', [EntrepreneurMeetingController::class, 'store'])->middleware('account.active')->name('meetings.store');
        Route::post('/meetings/{meeting}/reschedule', [MeetingRescheduleController::class, 'store'])->middleware('account.active')->name('meetings.reschedule');
        Route::get('/messages', [MessagesController::class, 'index'])->middleware('account.active')->name('messages');
        Route::get('/messages/{conversation}', [MessagesController::class, 'index'])->middleware('account.active')->name('messages.show');
        Route::post('/employees', [EmployeeInvitationController::class, 'store'])->name('employees.store');
    });

    Route::prefix('mentor')->name('mentor.')->middleware('role:mentor')->group(function () {
        Route::get('/onboarding', [MentorProfileController::class, 'edit'])->name('onboarding');
        Route::get('/dashboard', [MentorDashboardController::class, 'index'])->middleware('account.active')->name('dashboard');
        Route::middleware('account.active')->group(function () {
            Route::get('/availability', [MentorAvailabilityController::class, 'index'])->name('availability.index');
            Route::post('/availability', [MentorAvailabilityController::class, 'store'])->name('availability.store');
            Route::delete('/availability/{slot}', [MentorAvailabilityController::class, 'destroy'])->name('availability.destroy');
            Route::get('/mentees', [MenteeController::class, 'index'])->name('mentees.index');
            Route::get('/meetings', [MentorMeetingController::class, 'index'])->name('meetings.index');
            Route::post('/meetings/{meeting}/reschedule', [MeetingRescheduleController::class, 'store'])->name('meetings.reschedule');
            Route::get('/messages', [MessagesController::class, 'index'])->name('messages');
            Route::get('/messages/{conversation}', [MessagesController::class, 'index'])->name('messages.show');
        });
        Route::post('/reschedules/{reschedule}/accept', [RescheduleController::class, 'accept'])->name('reschedules.accept');
        Route::post('/reschedules/{reschedule}/decline', [RescheduleController::class, 'decline'])->name('reschedules.decline');
        Route::post('/meetings/{meeting}/report', [MeetingReportController::class, 'store'])->name('meetings.report.store');
        Route::patch('/profile', [MentorProfileController::class, 'update'])->name('profile.update');
    });

    /*
     * Onboarding: profile document uploads (entrepreneurs and mentors). Profile
     * fields are saved incrementally via each role's profile.update route.
     */
    Route::post('/onboarding/documents', [DocumentController::class, 'store'])->name('onboarding.documents.store');
    Route::get('/onboarding/documents/{document}', [DocumentController::class, 'show'])->name('onboarding.documents.show');
    Route::get('/onboarding/documents/{document}/preview', [DocumentController::class, 'preview'])->name('onboarding.documents.preview');

    Route::get('/conversations/{conversation}/messages', [ConversationMessagesController::class, 'index'])
        ->name('conversations.messages.index');
    Route::post('/conversations/{conversation}/messages', [SendMessageController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('conversations.messages.store');
    Route::post('/conversations/{conversation}/read', [MarkReadController::class, 'store'])
        ->name('conversations.read');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
});
