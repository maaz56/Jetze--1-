<?php

use App\Http\Controllers\Api\AgentDataController;
use App\Http\Controllers\Auth\GoogleController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::get('/auth/google', [GoogleController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleController::class, 'handleGoogleCallback']);


Route::get('/receipts/{filename}', [AgentDataController::class, 'downloadReceipt'])
    ->where('filename', '.*')   // <-- this allows dots in the filename
    ->name('receipts.download');

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '^(?!api).*$');



require __DIR__ . '/auth.php';
