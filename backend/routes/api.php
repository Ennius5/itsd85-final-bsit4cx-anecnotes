<?php

use App\Http\Controllers\AnecdoteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\StudentController;
use App\Http\Middleware\EnsureAccountIsActive;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware(['auth:sanctum', EnsureAccountIsActive::class, 'throttle:api'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

        Route::get('/students', [StudentController::class, 'index'])->name('students.index');
        Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');

        Route::apiResource('anecdotes', AnecdoteController::class);
        Route::post('/anecdotes/{anecdote}/resolve', [AnecdoteController::class, 'resolve'])->name('anecdotes.resolve');
        Route::get('/anecdotes/{anecdote}/analysis', [AnecdoteController::class, 'analysis'])->name('anecdotes.analysis');
    });
});
