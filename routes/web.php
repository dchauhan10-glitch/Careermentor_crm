<?php

use App\Http\Controllers\Admin\DuplicateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');
    Route::get('/two-factor', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor', [TwoFactorController::class, 'verify'])->middleware('throttle:login');
});

Route::middleware(['auth', 'account.ready'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/two-factor/setup', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm')->middleware('throttle:login');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/follow-ups', FollowUpController::class)->name('follow-ups');

    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/new', [StudentController::class, 'create'])->name('students.create');
    Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    Route::post('/students/bulk-assign', [StudentController::class, 'bulkAssign'])->name('students.bulk-assign')->middleware('can:lead-team');
    Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::put('/students/{student}', [StudentController::class, 'updateDetails'])->name('students.update');
    Route::post('/students/{student}/calls', [StudentController::class, 'logCall'])->name('students.calls.store');
    Route::post('/students/{student}/assign', [StudentController::class, 'assign'])->name('students.assign');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
    // Deliberately no routes to edit or delete call entries.

    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::post('/users/{user}/team', [UserController::class, 'moveTeam'])->name('users.team')->withTrashed();
        Route::post('/users/{user}/toggle', [UserController::class, 'toggleActive'])->name('users.toggle');
        Route::post('/users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');
        Route::post('/users/{user}/handover', [UserController::class, 'handOver'])->name('users.handover');

        Route::get('/duplicates', [DuplicateController::class, 'index'])->name('duplicates');
        Route::post('/duplicates/{review}/keep', [DuplicateController::class, 'keep'])->name('duplicates.keep');
        Route::post('/duplicates/{review}/merge', [DuplicateController::class, 'merge'])->name('duplicates.merge');
    });
});
