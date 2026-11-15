<?php

use App\Http\Controllers\Admin\DuplicateController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WebsiteController;
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
    Route::get('/calls', \App\Http\Controllers\CallHistoryController::class)->name('calls.index');

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

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
        Route::post('/settings/{list}', [SettingsController::class, 'add'])->name('settings.add');
        Route::post('/settings/{list}/{id}/toggle', [SettingsController::class, 'toggle'])->name('settings.toggle');

        Route::get('/export', [\App\Http\Controllers\Admin\ExportController::class, '__invoke'])->name('export');

        Route::get('/backups', [\App\Http\Controllers\Admin\BackupController::class, 'index'])->name('backups');
        Route::post('/backups/run', [\App\Http\Controllers\Admin\BackupController::class, 'runNow'])->name('backups.run')->middleware('throttle:3,10');
        Route::get('/backups/google', [\App\Http\Controllers\Admin\BackupController::class, 'connectGoogle'])->name('backups.google');
        Route::get('/backups/google/callback', [\App\Http\Controllers\Admin\BackupController::class, 'googleCallback'])->name('backups.google.callback');

        Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
        Route::post('/imports', [ImportController::class, 'store'])->name('imports.store')->middleware('throttle:10,1');
        Route::get('/imports/{batch}/map', [ImportController::class, 'map'])->name('imports.map');
        Route::post('/imports/{batch}/start', [ImportController::class, 'start'])->name('imports.start');
        Route::post('/imports/{batch}/cancel', [ImportController::class, 'cancel'])->name('imports.cancel');
        Route::get('/imports/{batch}', [ImportController::class, 'show'])->name('imports.show');

        Route::get('/websites', [WebsiteController::class, 'index'])->name('websites');
        Route::post('/websites', [WebsiteController::class, 'store'])->name('websites.store');
        Route::put('/websites/{connection}', [WebsiteController::class, 'update'])->name('websites.update');
        Route::post('/websites/{connection}/toggle', [WebsiteController::class, 'toggle'])->name('websites.toggle');
        Route::post('/websites/{connection}/key', [WebsiteController::class, 'newKey'])->name('websites.key');

        Route::get('/duplicates', [DuplicateController::class, 'index'])->name('duplicates');
        Route::post('/duplicates/{review}/keep', [DuplicateController::class, 'keep'])->name('duplicates.keep');
        Route::post('/duplicates/{review}/merge', [DuplicateController::class, 'merge'])->name('duplicates.merge');
    });
});
