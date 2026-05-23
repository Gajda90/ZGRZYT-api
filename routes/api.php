<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LogController as AdminLogController;
use App\Http\Controllers\ListController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Tutaj możesz zarejestrować trasy API dla swojej aplikacji. Te trasy
| są ładowane przez RouteServiceProvider i wszystkie zostaną przypisane
| do grupy middleware "api".
|
*/
Route::get('/debug-session', function () {
    return response()->json([
        'session_id' => session()->getId(),
        'user' => auth()->user(),
        'cookies' => request()->cookies->all(),
        'headers' => request()->headers->all()
    ]);
});
// --- Trasy publiczne ---
Route::post('/login', [AuthController::class, 'login']);
Route::post('/request-account', [AuthController::class, 'requestAccount']);

// --- Trasy chronione (wymagają uwierzytelnienia przez Sanctum) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [UserController::class, 'getAuthenticatedUser'])->name('user.profile');
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/refresh', [AuthController::class, 'refresh']);



    // --- Trasy dla zgłoszeń (Tickets) ---
    // Użytkownik widzi tylko swoje, IT/Admin widzą wszystkie (do obsłużenia w kontrolerze/polityce)
    Route::apiResource('tickets', TicketController::class);

    // --- Trasy dla wiadomości (Messages) w ramach zgłoszenia ---
    Route::prefix('tickets/{ticket}')->as('tickets.')->group(function () {
        Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('messages', [MessageController::class, 'store'])->name('messages.store');
    });

    // --- Lista użytkowników (dostępna dla każdego, filtrowanie wyników pod rolę następuje w kontrolerze) ---
    Route::get('users', [UserController::class, 'index'])->name('users.index');

    // --- Trasy zarządzania użytkownikami (IT/Admin) ---
    Route::middleware('can:access-admin-features')->group(function () {
        Route::apiResource('users', UserController::class)->except(['index']);
        Route::post('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        Route::post('users/{user}/ban', [UserController::class, 'ban'])->name('users.ban');
        Route::post('users/{user}/unban', [UserController::class, 'unban'])->name('users.unban');
    });

    // ---Trasy list (paginacja danych i filtorwanie po stronie servera)
    Route::middleware('can:access-it-features')->group(function () {
        Route::get('active-tickets', [ListController::class, 'activeTickets'])->name('lists.active-tickets');
        Route::get('unassigned-tickets', [ListController::class, 'unassignedTickets'])->name('lists.unassigned-tickets');
        Route::get('banned-users', [ListController::class, 'bannedUsers'])->name('lists.banned-users');
        Route::get('inactive-users', [ListController::class, 'inactiveUsers'])->name('lists.inactive-users');
        Route::get('active-users', [ListController::class, 'activeUsers'])->name('lists.active-users');
    });
});

Broadcast::routes(['middleware' => ['auth:sanctum']]);
