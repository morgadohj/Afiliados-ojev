<?php

use App\Http\Controllers\AffiliationController;
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
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
