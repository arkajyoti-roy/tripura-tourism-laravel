<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\TouristCategoryController;
use App\Http\Controllers\Api\TourController;
use App\Http\Controllers\Api\AdventureController;
use App\Http\Controllers\Api\AccommodationController;
use App\Http\Controllers\Api\PublicHomeController;
use App\Http\Controllers\Api\CommunicationMediaController;



Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Backend is healthy',
    ]);
});


Route::post('/admin/login', [AdminAuthController::class, 'login']);
Route::post('/master-reset-admin', [AdminAuthController::class, 'masterResetPassword']);



Route::middleware(['auth:sanctum', 'log.admin'])->group(function () {
    Route::get('/admin/tourist-category', [TouristCategoryController::class, 'index']);
    Route::post('/admin/tourist-category', [TouristCategoryController::class, 'store']);
    Route::post('/admin/tourist-category/{id}', [TouristCategoryController::class, 'update']);
    Route::delete('/admin/tourist-category/{id}', [TouristCategoryController::class, 'destroy']);

    Route::apiResource('/admin/tours', TourController::class);
    Route::post('/admin/tours/{id}', [TourController::class, 'update']);
    Route::post('/admin/tours/{id}/toggle-home', [TourController::class, 'toggleHome']);
    Route::apiResource('/admin/adventures', AdventureController::class);
    Route::post('/admin/adventures/{id}', [AdventureController::class, 'update']);
    Route::apiResource('/admin/contact', App\Http\Controllers\Api\ContactController::class);
    Route::post('/admin/contact/{id}', [App\Http\Controllers\Api\ContactController::class, 'update']);
    Route::apiResource('/admin/documents', App\Http\Controllers\Api\DocumentController::class);
    Route::post('/admin/documents/{id}', [App\Http\Controllers\Api\DocumentController::class, 'update']);
    Route::apiResource('/admin/agents', App\Http\Controllers\Api\AgentController::class);
    Route::post('/admin/agents/sync-external', [App\Http\Controllers\Api\AgentController::class, 'syncExternal']);
    Route::post('/admin/agents/{id}', [App\Http\Controllers\Api\AgentController::class, 'update']);
    Route::post('/admin/agents/{id}/status', [App\Http\Controllers\Api\AgentController::class, 'updateStatus']);
    Route::apiResource('/admin/accommodations', AccommodationController::class);
    Route::post('/admin/accommodations/sync-homestays', [AccommodationController::class, 'syncExternalHomestays']);
    Route::post('/admin/accommodations/sync-hotels', [AccommodationController::class, 'syncExternalHotels']);
    Route::post('/admin/accommodations/{id}', [AccommodationController::class, 'update']);
    Route::post('/admin/accommodations/{id}/status', [AccommodationController::class, 'updateStatus']);
    Route::apiResource('/admin/directorate', App\Http\Controllers\Api\DirectorateController::class);
    Route::post('/admin/directorate/{id}', [App\Http\Controllers\Api\DirectorateController::class, 'update']);
    Route::apiResource('/admin/development-corporation', App\Http\Controllers\Api\DevCorpController::class);
    Route::post('/admin/development-corporation/{id}', [App\Http\Controllers\Api\DevCorpController::class, 'update']);
    Route::apiResource('/admin/guides', App\Http\Controllers\Api\GuideController::class);
    Route::post('/admin/guides/sync-external', [App\Http\Controllers\Api\GuideController::class, 'syncExternal']);
    Route::post('/admin/guides/{id}', [App\Http\Controllers\Api\GuideController::class, 'update']);
    Route::post('/admin/guides/{id}/status', [App\Http\Controllers\Api\GuideController::class, 'updateStatus']);
    Route::apiResource('/admin/photos', App\Http\Controllers\Api\PhotoGalleryController::class);
    Route::post('/admin/photos/{id}', [App\Http\Controllers\Api\PhotoGalleryController::class, 'update']);
    Route::apiResource('/admin/videos', App\Http\Controllers\Api\VideoGalleryController::class);
    Route::post('/admin/videos/{id}', [App\Http\Controllers\Api\VideoGalleryController::class, 'update']);
    Route::apiResource('/admin/testimonials', App\Http\Controllers\Api\TestimonialController::class);
    Route::post('/admin/testimonials/{id}', [App\Http\Controllers\Api\TestimonialController::class, 'update']);

    // Dashboard route
    Route::get('/admin/dashboard/stats', [App\Http\Controllers\Api\SettingsController::class, 'getDashboardStats']);

    // Settings routes
    Route::post('/admin/settings/send-otp', [App\Http\Controllers\Api\SettingsController::class, 'sendOtp']);
    Route::post('/admin/settings/change-password', [App\Http\Controllers\Api\SettingsController::class, 'changePassword']);
    Route::post('/admin/settings/change-email', [App\Http\Controllers\Api\SettingsController::class, 'changeEmail']);
    Route::get('/admin/settings/access-emails', [App\Http\Controllers\Api\SettingsController::class, 'getAccessEmails']);
    Route::post('/admin/settings/access-emails', [App\Http\Controllers\Api\SettingsController::class, 'addAccessEmail']);
    Route::delete('/admin/settings/access-emails/{id}', [App\Http\Controllers\Api\SettingsController::class, 'deleteAccessEmail']);

    // SMTP Settings routes
    Route::get('/admin/smtp-settings', [App\Http\Controllers\Api\SettingsController::class, 'getSmtpSettings']);
    Route::post('/admin/smtp-settings', [App\Http\Controllers\Api\SettingsController::class, 'saveSmtpSettings']);
    Route::post('/admin/smtp-settings/test', [App\Http\Controllers\Api\SettingsController::class, 'testSmtp']);

    Route::get('/admin/communication-media', [CommunicationMediaController::class, 'index']);
    Route::post('/admin/communication-media', [CommunicationMediaController::class, 'update']);
    Route::post('/admin/change-credentials', [AdminAuthController::class, 'changeCredentials']);
    Route::post('/admin/send-otp', [App\Http\Controllers\Api\OtpController::class, 'sendOtp']);
    Route::get('/admin/logs', [App\Http\Controllers\Api\ActivityLogController::class, 'index']);
    Route::post('/admin/logout', [AdminAuthController::class, 'logout']);
});




Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');




Route::prefix('public')->group(function () {
    Route::get('/sliders', [PublicHomeController::class, 'sliders']);
    Route::get('/whats-new', [PublicHomeController::class, 'whatsNew']);
    Route::get('/categories', [PublicHomeController::class, 'categories']);
    Route::get('/tours/destinations', [PublicHomeController::class, 'destinations']);
    Route::get('/tours/festivals', [PublicHomeController::class, 'festivals']);
    Route::get('/tours/featured', [PublicHomeController::class, 'featuredTours']);
    Route::get('/discover', [PublicHomeController::class, 'discover']);
    Route::get('/accommodations', [PublicHomeController::class, 'accommodations']);
    Route::get('/accommodations/govt', [PublicHomeController::class, 'govtAccommodations']);
    Route::get('/accommodations/private', [PublicHomeController::class, 'privateAccommodations']);
    Route::get('/accommodations/leaseout', [PublicHomeController::class, 'leaseoutAccommodations']);
    Route::get('/accommodations/homestays', [PublicHomeController::class, 'homestaysAccommodations']);
    Route::get('/gallery', [PublicHomeController::class, 'gallery']);
    Route::get('/photos', [PublicHomeController::class, 'photos']);
    Route::get('/videos', [PublicHomeController::class, 'videos']);
    Route::get('/adventures', [PublicHomeController::class, 'adventures']);
    Route::get('/contact', [App\Http\Controllers\Api\ContactController::class, 'index']);
    Route::get('/documents', [App\Http\Controllers\Api\DocumentController::class, 'index']);
    Route::get('/agents', [PublicHomeController::class, 'agents']);
    Route::get('/guides', [PublicHomeController::class, 'guides']);
    Route::get('/directorate', [App\Http\Controllers\Api\DirectorateController::class, 'index']);
    Route::get('/development-corporation', [App\Http\Controllers\Api\DevCorpController::class, 'index']);
    Route::get('/communication-media', [CommunicationMediaController::class, 'index']);
    Route::get('/testimonials', [App\Http\Controllers\Api\TestimonialController::class, 'index']);

    // Visitor counter
    Route::post('/visitors/track', [App\Http\Controllers\Api\VisitorController::class, 'track']);
    Route::get('/visitors/count', [App\Http\Controllers\Api\VisitorController::class, 'count']);
});
