<?php

use App\Http\Controllers\AffiliationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/afiliacion')->name('home');

Route::get('afiliacion', [AffiliationController::class, 'create'])
    ->name('affiliation.create');
Route::post('afiliacion/extraer-ine', [AffiliationController::class, 'extractIne'])
    ->middleware('throttle:10,1')
    ->name('affiliation.extract-ine');
Route::post('afiliacion', [AffiliationController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('affiliation.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('administracion/afiliar', [AffiliationController::class, 'createAdministrative'])
        ->name('admin.affiliation.create');
    Route::post('administracion/afiliar/extraer-ine', [AffiliationController::class, 'extractIne'])
        ->middleware('throttle:20,1')
        ->name('admin.affiliation.extract-ine');
    Route::post('administracion/afiliar', [AffiliationController::class, 'storeAdministrative'])
        ->middleware('throttle:10,1')
        ->name('admin.affiliation.store');

    Route::middleware('administrator')->group(function () {
        Route::get('administracion/usuarios', [UserManagementController::class, 'index'])
            ->name('admin.users.index');
        Route::post('administracion/usuarios', [UserManagementController::class, 'store'])
            ->name('admin.users.store');
    });
});

require __DIR__.'/settings.php';
