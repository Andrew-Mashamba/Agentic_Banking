<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\WhatsApp\WebhookController;
use App\Livewire\SettingsManagement;
use Illuminate\Support\Facades\Route;

// WhatsApp webhook routes (public access for WhatsApp API)
Route::get('/webhooks/whatsapp', [WebhookController::class, 'verify'])->name('whatsapp.verify');
Route::post('/webhooks/whatsapp', [WebhookController::class, 'handle'])->name('whatsapp.webhook');

// Test error handling routes (development only)
if (config('app.env') !== 'production') {
    Route::prefix('test-errors')->group(function () {
        Route::get('/404', function () { abort(404); });
        Route::get('/500', function () { throw new \Exception('Test 500 error'); });
        Route::post('/validation', function () {
            request()->validate(['email' => 'required|email', 'name' => 'required|min:3']);
        });
        Route::get('/unauthorized', function () { abort(403, 'Unauthorized access'); });
        Route::get('/logs', function () {
            return response()->json(\App\Models\ErrorLog::orderBy('created_at', 'desc')->limit(50)->get());
        });
    });
}

// Root: redirect to login or settings
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('settings');
    }
    return redirect()->route('login');
})->name('home');

// Authentication
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected routes
Route::middleware(['auth:web'])->group(function () {
    // Settings (admin and manager)
    Route::get('/settings', SettingsManagement::class)->middleware(['role:admin,manager'])->name('settings');

    // Default dashboard redirect for any authenticated user
    Route::get('/dashboard', function () {
        return redirect()->route('settings');
    })->name('dashboard');
});
