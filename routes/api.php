<?php

use App\Http\Controllers\MotController;
use App\Http\Controllers\ActualiteController;
use App\Http\Controllers\EvenementController;
use App\Http\Controllers\ConseillerController;
use App\Http\Controllers\SynagogueController;
use App\Http\Controllers\FondementController;
use App\Http\Controllers\EtudeController;
use App\Http\Controllers\ParachaController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProduitController;

Route::middleware('auth:sanctum')->group(function () {
});

Route::post('login', [UserController::class, 'login']);
Route::post('/logout', function (Request $request) {
    $request->user()->currentAccessToken()->delete();
    return response()->json(['message' => 'Déconnecté avec succès']);
})->middleware('auth:sanctum');


Route::post('add_user', [UserController::class, 'addUser']);
Route::get('liste_user', [UserController::class, 'listeUser']);
Route::get('user/{id}', [UserController::class, 'getUser']);
Route::post('update_user/{id}', [UserController::class, 'updateUser']);
Route::delete('delete_user/{id}', [UserController::class, 'deleteUser']);

// -----------------------------------------------
// ----------------- Mot du Rabbi ----------------
// -----------------------------------------------
Route::post('add_mot', [MotController::class, 'addMot']);
Route::get('liste_mot', [MotController::class, 'listeMot']);
Route::delete('delete_mot/{id}', [MotController::class, 'deleteMot']);
Route::post('update_mot/{id}', [MotController::class, 'updateMot']);
// -----------------------------------------------
// ----------------- Actualités ----------------
// -----------------------------------------------
Route::post('add_actualite', [ActualiteController::class, 'addActualite']);
Route::get('liste_actualite', [ActualiteController::class, 'listeActualite']);
Route::delete('delete_actualite/{id}', [ActualiteController::class, 'deleteActualite']);
Route::post('update_actualite/{id}', [ActualiteController::class, 'updateActualite']);

// -----------------------------------------------
// ----------------- Événements ----------------
// -----------------------------------------------
Route::post('add_evenement', [EvenementController::class, 'addEvenement']);
Route::get('liste_evenement', [EvenementController::class, 'listeEvenement']);
Route::delete('delete_evenement/{id}', [EvenementController::class, 'deleteEvenement']);
Route::post('update_evenement/{id}', [EvenementController::class, 'updateEvenement']);

// -----------------------------------------------
// -------------- Conseillers ---------------
// -----------------------------------------------
Route::post('add_conseiller', [ConseillerController::class, 'addConseiller']);
Route::get('liste_conseiller', [ConseillerController::class, 'listeConseillers']);
Route::delete('delete_conseiller/{id}', [ConseillerController::class, 'deleteConseiller']);
Route::post('update_conseiller/{id}', [ConseillerController::class, 'updateConseiller']);

// -----------------------------------------------
// ----------------- Fondements ----------------
// -----------------------------------------------
Route::post('add_fondement', [FondementController::class, 'addFondement']);
Route::get('liste_fondement', [FondementController::class, 'listeFondement']);
Route::delete('delete_fondement/{id}', [FondementController::class, 'deleteFondement']);
Route::post('update_fondement/{id}', [FondementController::class, 'updateFondement']);

// -----------------------------------------------
// ----------------- Études --------------------
// -----------------------------------------------
Route::post('add_etude', [EtudeController::class, 'addEtude']);
Route::get('liste_etude', [EtudeController::class, 'listeEtude']);
Route::delete('delete_etude/{id}', [EtudeController::class, 'deleteEtude']);
Route::post('update_etude/{id}', [EtudeController::class, 'updateEtude']);

// -----------------------------------------------
// ----------------- Synagogue ----------------
// -----------------------------------------------
Route::post('add_synagogue', [SynagogueController::class, 'addSynagogue']);
Route::get('liste_synagogue', [SynagogueController::class, 'listeSynagogue']);
Route::delete('delete_synagogue/{id}', [SynagogueController::class, 'deleteSynagogue']);
Route::post('update_synagogue/{id}', [SynagogueController::class, 'updateSynagogue']);

// -----------------------------------------------
// ----------------- Parachiot ------------------
// -----------------------------------------------
Route::post('add_paracha', [ParachaController::class, 'addParacha']);
Route::get('liste_paracha', [ParachaController::class, 'listeParachiot']);
Route::delete('delete_paracha/{id}', [ParachaController::class, 'deleteParacha']);
Route::post('update_paracha/{id}', [ParachaController::class, 'updateParacha']);

// ---------------- Produits ----------------
Route::post('add_produit', [ProduitController::class, 'addProduit']);
Route::get('liste_produits', [ProduitController::class, 'listeProduits']);
Route::post('update_produit/{id}', [ProduitController::class, 'updateProduit']);
Route::delete('delete_produit/{id}', [ProduitController::class, 'deleteProduit']);

/* 
 




*/