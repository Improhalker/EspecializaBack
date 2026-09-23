<?php

use App\Http\Controllers\Api\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Api\Admin\MediaController as AdminMediaController;
use App\Http\Controllers\Api\Admin\PageAppearanceController as AdminPageAppearanceController;
use App\Http\Controllers\Api\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Api\Admin\SharedFaqController as AdminSharedFaqController;
use App\Http\Controllers\Api\Admin\TestimonialController as AdminTestimonialController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\MediaDeliveryController;
use App\Http\Controllers\Api\TestimonialController;
use App\Http\Controllers\Api\WhatsappClickController;
use Illuminate\Support\Facades\Route;

Route::get('/media/{media}', MediaDeliveryController::class)->whereUuid('media')->name('media.delivery');

Route::get('/home', HomeController::class)->name('home');
Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
Route::get('/courses/{slug}', [CourseController::class, 'show'])->name('courses.show');
Route::get('/testimonials', [TestimonialController::class, 'index'])->name('testimonials.index');
Route::post('/whatsapp-clicks', [WhatsappClickController::class, 'store'])->middleware('throttle:30,1')->name('whatsapp-clicks.store');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::post('/auth/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1')->name('auth.login');

    Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
        Route::get('/auth/me', [AdminAuthController::class, 'me'])->name('auth.me');
        Route::delete('/auth/logout', [AdminAuthController::class, 'logout'])->name('auth.logout');
        Route::put('/auth/initial-password', [AdminAuthController::class, 'updateInitialPassword'])->name('auth.initial-password');

        Route::middleware('password.changed')->group(function (): void {
            foreach (['media' => 'media.', 'v1/media' => 'v1.media.'] as $prefix => $name) {
                Route::prefix($prefix)->name($name)->group(function (): void {
                    Route::get('/', [AdminMediaController::class, 'index'])->name('index');
                    Route::post('/', [AdminMediaController::class, 'store'])->middleware('throttle:30,1')->name('store');
                    Route::get('/{media}', [AdminMediaController::class, 'show'])->whereNumber('media')->name('show');
                    Route::patch('/{media}', [AdminMediaController::class, 'update'])->whereNumber('media')->name('update');
                    Route::delete('/{media}', [AdminMediaController::class, 'destroy'])->whereNumber('media')->name('destroy');
                    Route::get('/{media}/usages', [AdminMediaController::class, 'usages'])->whereNumber('media')->name('usages');
                });
            }

            Route::apiResource('courses', AdminCourseController::class);
            Route::get('/page-appearances', [AdminPageAppearanceController::class, 'index'])->name('page-appearances.index');
            Route::get('/page-appearances/{pageKey}', [AdminPageAppearanceController::class, 'show'])->name('page-appearances.show');
            Route::patch('/page-appearances/{pageKey}', [AdminPageAppearanceController::class, 'update'])->name('page-appearances.update');
            Route::patch('/courses/{course}/publication', [AdminCourseController::class, 'updatePublication'])->name('courses.publication');
            Route::apiResource('testimonials', AdminTestimonialController::class);
            Route::patch('/testimonials/{testimonial}/publication', [AdminTestimonialController::class, 'updatePublication'])->name('testimonials.publication');
            Route::apiResource('faqs', AdminSharedFaqController::class);
            Route::patch('/faqs/{faq}/publication', [AdminSharedFaqController::class, 'updatePublication'])->name('faqs.publication');
            Route::apiResource('categories', AdminCategoryController::class);
            Route::get('/attendances', AdminAttendanceController::class)->name('attendances.index');
            Route::get('/settings', [AdminSettingController::class, 'index'])->name('settings.index');
            Route::put('/settings', [AdminSettingController::class, 'update'])->name('settings.update');
        });
    });
});
