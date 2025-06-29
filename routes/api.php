<?php

use App\Models\Subsection;
use App\Models\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\LayoutController;
use App\Http\Controllers\PrayerRequestController;
use App\Http\Controllers\AdherentController;
use App\Http\Controllers\ContentController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return response()->json(['user' => $request->user()]);
    ;
});

Route::post('login', [UserController::class, 'login']);

// Authentification adhérent
Route::post('/adherent/login', [AdherentController::class, 'login']);

Route::middleware(['auth:sanctum', 'adherent'])->group(function () {
    Route::middleware(['auth:sanctum', 'adherent'])->get('/adherent/me', function (Request $request) {
        return response()->json(['adherent' => $request->user()]);
    });

    Route::get('/adherent/contents', [ContentController::class, 'byType']);

    Route::post('/adherent/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Déconnecté avec succès']);
    });

    Route::get('/adherents/{id}', [AdherentController::class, 'show']); // Voir un adhérent

});

Route::middleware('auth:sanctum')->group(function () {
    // --------------------------------
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Déconnecté avec succès']);
    })->middleware('auth:sanctum');

    Route::post('add_user', [UserController::class, 'addUser']);
    Route::get('liste_user', [UserController::class, 'listeUser']);
    Route::get('user/{id}', [UserController::class, 'getUser']);
    Route::post('update_user/{id}', [UserController::class, 'updateUser']);
    Route::delete('delete_user/{id}', [UserController::class, 'deleteUser']);

    Route::post('/add_page', [PageController::class, 'store']);
    Route::post('/update_page/{id}', [PageController::class, 'update']);
    Route::delete('/delete_page/{id}', [PageController::class, 'destroy']);


    Route::post('/ads', [LayoutController::class, 'store']);        // Créer
    Route::post('/ads/{id}', [LayoutController::class, 'update']);   // Mettre à jour
    Route::delete('/ads/{id}', [LayoutController::class, 'destroy']); // Supprimer

    // Prayer requests
    Route::get('/prayer-requests', [PrayerRequestController::class, 'index']);
    Route::delete('/prayer-requests/{id}', [PrayerRequestController::class, 'destroy']); // Supprimer une demande de prière
    Route::post('/prayer-requests/{id}/validate', [PrayerRequestController::class, 'validatePrayerRequest']);

    // Adherents
    Route::get('/adherents', [AdherentController::class, 'index']);
    Route::delete('/adherents/{id}', [AdherentController::class, 'destroy']); // Supprimer un adhérent
    Route::post('/adherents/{id}/validate', [AdherentController::class, 'validateAdherent']);
    Route::get('/adherents/{id}', [AdherentController::class, 'show']);
    Route::put('/adherents/{id}', [AdherentController::class, 'update']);



    // Contents
    Route::prefix('contents')->group(function () {
        Route::get('/', [ContentController::class, 'index']);
        Route::post('/', [ContentController::class, 'store']);
        Route::get('{id}', [ContentController::class, 'show']);
        Route::put('{id}', [ContentController::class, 'update']);
        Route::delete('{id}', [ContentController::class, 'destroy']);
    });
});
Route::post('/adherents', [AdherentController::class, 'store']);
Route::post('/prayer-requests', [PrayerRequestController::class, 'store']);


// --------------
Route::get('/subsections/{id}', function ($id) {
    return Subsection::findOrFail($id);
});


Route::get('/pages', [PageController::class, 'index']);
Route::get('/page/{id}', [PageController::class, 'get']);
Route::get('/pages/{slug}', [PageController::class, 'show']);
Route::get('/ads', [LayoutController::class, 'index']);        // Liste tous
Route::get('/ads/{id}', [LayoutController::class, 'show']);     // Voir un