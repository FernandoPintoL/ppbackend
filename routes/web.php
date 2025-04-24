<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\{ChatController, FigmaController, NodeController, PizarraController, CanvaController, FormBuilderController};


Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::resource('chat', ChatController::class);
Route::resource('pizarra', PizarraController::class);
Route::resource('canva', CanvaController::class);
Route::resource('form-builder', FormBuilderController::class);

// Form Builder Collaboration Routes
Route::post('form-builder/{id}/invite', [FormBuilderController::class, 'inviteCollaborator'])->name('form-builder.invite');
Route::post('form-builder/{id}/accept', [FormBuilderController::class, 'acceptInvitation'])->name('form-builder.accept');
Route::post('form-builder/{id}/reject', [FormBuilderController::class, 'rejectInvitation'])->name('form-builder.reject');
Route::get('form-builder/{id}/collaborators', [FormBuilderController::class, 'getCollaborators'])->name('form-builder.collaborators');
Route::get('form-builder/invite/{id}', [FormBuilderController::class, 'handleInviteLink'])->name('form-builder.invite-link');

Route::get('/node-data', NodeController::class.'@getData')->name('node-data');
Route::get('/figma/file/{fileId}', [FigmaController::class, 'getFile'])->name('figma.file');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

/*Route::get('/{any}', function () {
    return "NO ENCONTRADO";
})->where('any', '.*')->name('not-found');*/
