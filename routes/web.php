<?php

use App\Http\Controllers\Api\AgentDataController;
use App\Http\Controllers\BlogPageController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Auth\GoogleController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::get('/auth/google', [GoogleController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleController::class, 'handleGoogleCallback']);


Route::get('/receipts/{filename}', [AgentDataController::class, 'downloadReceipt'])
    ->where('filename', '.*')   // <-- this allows dots in the filename
    ->name('receipts.download');

Route::get('/blogs', [BlogPageController::class, 'index'])->name('blog.index');
Route::get('/blog', [BlogPageController::class, 'index'])->name('blog.index.legacy');
Route::get('/blog/{id}/{slug}', [BlogPageController::class, 'showLegacy'])
    ->whereNumber('id')
    ->name('blog.show.legacy');
Route::get('/blog/{blog:slug}', [BlogPageController::class, 'show'])->name('blog.show');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/about/us', [BlogPageController::class, 'about'])->name('about-us');
Route::get('/contact/us', [BlogPageController::class, 'contact'])->name('contact-us');

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '^(?!api).*$');



require __DIR__ . '/auth.php';
