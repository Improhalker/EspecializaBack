<?php

use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('site.robots');
Route::get('/sitemap.xml', SitemapController::class)->name('site.sitemap');
Route::get('/{path}', [PublicSiteController::class, 'asset'])
    ->where('path', '(?:assets|brand)/[^.][a-zA-Z0-9_./-]*|favicon\\.svg|icons\\.svg')
    ->name('site.asset');
Route::get('/', [PublicSiteController::class, 'home'])->name('site.home');
Route::get('/cursos', [PublicSiteController::class, 'courses'])->name('site.courses');
Route::get('/quem-somos', [PublicSiteController::class, 'about'])->name('site.about');
Route::get('/politica-de-privacidade', [PublicSiteController::class, 'privacy'])->name('site.privacy');
Route::get('/termos-de-uso', [PublicSiteController::class, 'terms'])->name('site.terms');
Route::get('/cursos/{slug}', [PublicSiteController::class, 'course'])->name('site.course');
Route::get('/admin/{path?}', [PublicSiteController::class, 'admin'])->where('path', '.*')->name('site.admin');
Route::fallback([PublicSiteController::class, 'missing']);
