<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\{
    ChatController,
    NodeController
};


Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::resource('chat', ChatController::class);

Route::get('/node-data', NodeController::class.'@getData')->name('node-data');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

/*Route::get('/{any}', function () {
    return "NO ENCONTRADO";
})->where('any', '.*')->name('not-found');*/


