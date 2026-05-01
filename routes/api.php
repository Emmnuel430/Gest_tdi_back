<?php

use App\Http\Controllers\GalerieDossierController;
use App\Http\Controllers\GalerieImageController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PayementController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TsedakaController;
use App\Http\Controllers\VisitController;
use App\Models\Subsection;
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

    Route::get('/adherent/me', function (Request $request) {
        return response()->json(['adherent' => $request->user()]);
    });

    Route::get('/adherents/contents', [ContentController::class, 'byType']);
    Route::post('/adherents/validate', [AdherentController::class, 'updateProfile']);

    Route::post('/adherents/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Déconnecté avec succès']);
    });

    Route::get('/adherents-public/{id}', [AdherentController::class, 'show']);
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
    Route::patch('/adherents/{id}/toggle-validate', [AdherentController::class, 'toggleValidateAdherent']);
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

    // Dossiers
    Route::prefix('galerie/dossiers')->group(function () {

        Route::get('/', [GalerieDossierController::class, 'index']);
        Route::get('{id}', [GalerieDossierController::class, 'show']);
        Route::post('/', [GalerieDossierController::class, 'store']);
        Route::delete('/', [GalerieDossierController::class, 'deleteMultiple']);
        Route::put('{id}', [GalerieDossierController::class, 'update']);
        Route::delete('{id}', [GalerieDossierController::class, 'delete']);

        // 👁 toggle visibilité
        Route::patch('{id}/toggle', [GalerieDossierController::class, 'toggleDossier']);
    });

    // Images
    Route::prefix('galerie/images')->group(function () {

        // upload (single ou multiple) -> Attacher au dossier
        Route::post('/attach', [GalerieImageController::class, 'attach']);

        // list par dossier
        Route::get('/dossier/{dossierId}', [GalerieImageController::class, 'getImagesByDossier']);

        // update (titre, visibilité)
        Route::put('{id}', [GalerieImageController::class, 'update']);

        // delete (retirer du dossier)
        Route::delete('{id}', [GalerieImageController::class, 'delete']);
        Route::delete('/', [GalerieImageController::class, 'deleteMultiple']);

        // reorder
        Route::post('reorder', [GalerieImageController::class, 'reorderImages']);

        // toggle visibilité
        Route::patch('{id}/toggle', [GalerieImageController::class, 'toggle']);
    });

    // Media
    Route::prefix('media')->group(function () {

        // list globale
        Route::get('/', [MediaController::class, 'listMedia']);

        // supprimer forcé
        Route::delete('/force', [MediaController::class, 'forceDeleteMedia']);
        // supprimer (safe)
        Route::delete('{id}', [MediaController::class, 'deleteMedia']);

        // Upload (drag & drop)
        Route::post('/', [MediaController::class, 'store']);
    });

    // Visits
    Route::prefix('visits')->group(function () {
        Route::get('/stats', [VisitController::class, 'stats']);
        Route::get('/chart', [VisitController::class, 'chart']);
    });

    // Plans
    Route::prefix('subscription-plans')->group(function () {
        Route::get('/', [SubscriptionPlanController::class, 'index']);
        Route::post('/', [SubscriptionPlanController::class, 'store']);
        Route::get('{id}', [SubscriptionPlanController::class, 'show']);
        Route::put('{id}', [SubscriptionPlanController::class, 'update']);
        Route::delete('{id}', [SubscriptionPlanController::class, 'destroy']);
    });

    // Transactions
    Route::prefix('transactions')->group(function () {
        Route::get('/', [TransactionController::class, 'index']);
        Route::patch('{id}/status', [TransactionController::class, 'changeStatus']);
        Route::get('/stats', [TransactionController::class, 'stats']);
    });

    // Commandes
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::patch('{id}/status', [OrderController::class, 'changeStatus']);
    });

    // Tsedakas
    Route::get('/tsedakas', [TsedakaController::class, 'list']);

});

// --------------
Route::get('/subsections/{id}', function ($id) {
    return Subsection::findOrFail($id);
});


Route::get('/pages', [PageController::class, 'index']);
Route::get('/page/{id}', [PageController::class, 'get']);
Route::get('/pages/{slug}', [PageController::class, 'show']);
Route::get('/ads', [LayoutController::class, 'index']);        // Liste tous
Route::get('/ads/{id}', [LayoutController::class, 'show']);     // Voir un


Route::get('/public/galerie/dossiers', [GalerieDossierController::class, 'indexPublic']);

Route::post('/track', [VisitController::class, 'track']);

Route::post('/payments/initiate', [PayementController::class, 'initiate']);
Route::get('/payments/verify/{reference}', [PayementController::class, 'verify']);
Route::post('/payments/webhook', [PayementController::class, 'webhook']);

Route::get('/public/subscription-plans', [SubscriptionPlanController::class, 'indexPublic']);