<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\PostController;
use App\Http\Middleware\EnsureGenerationAccess;
use Illuminate\Support\Facades\Route;

Route::get('/__deploy/setup', [DeploymentController::class, 'show'])->name('deployment.setup');
Route::post('/__deploy/setup', [DeploymentController::class, 'run'])->name('deployment.run');

Route::get('/', [PostController::class, 'index'])->name('posts.index');
Route::redirect('/studio', '/');
Route::post('/trial', [PostController::class, 'preview'])
    ->middleware(['guest', 'throttle:6,10'])
    ->name('posts.preview');
Route::post('/studio', [PostController::class, 'store'])->middleware(['auth', 'verified', EnsureGenerationAccess::class])->name('posts.store');
Route::post('/trial/publish', [PostController::class, 'publishPreview'])
    ->middleware(['auth', 'verified', EnsureGenerationAccess::class])
    ->name('posts.preview.publish');
Route::delete('/studio/{post}', [PostController::class, 'destroy'])->middleware(['auth', 'verified'])->name('posts.destroy');
Route::get('/posts/recent', [PostController::class, 'recent'])->name('posts.recent');
Route::get('/posts/{post}/related', [PostController::class, 'related'])->name('posts.related');
Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
Route::post('/stripe/webhook', [BillingController::class, 'webhook'])->name('billing.webhook');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::get('/register/check-username', [AuthController::class, 'checkUsername'])->name('register.username.check');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/account/password', [AuthController::class, 'showChangePassword'])->name('password.change');
    Route::put('/account/password', [AuthController::class, 'updatePassword'])->name('password.update');
    Route::get('/email/verify', [AuthController::class, 'showVerifyNotice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationEmail'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::get('/billing/success', [BillingController::class, 'success'])->name('billing.success');
    Route::post('/billing/checkout/{plan}', [BillingController::class, 'checkout'])
        ->middleware('verified')
        ->name('billing.checkout');
    Route::post('/billing/portal', [BillingController::class, 'portal'])
        ->middleware('verified')
        ->name('billing.portal');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
