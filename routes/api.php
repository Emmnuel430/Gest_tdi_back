<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\PageController;

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

/* 
 
 Je vais essayer de créer un CMS bonne chance à moi même
Je suis un dur 😎, j'ai réussi !

*/

Route::get('/pages', [PageController::class, 'index']);
Route::post('/add_page', [PageController::class, 'store']);
Route::post('/update_page/{id}', [PageController::class, 'update']);
Route::delete('/delete_page/{id}', [PageController::class, 'destroy']);
Route::get('/pages/{slug}', [PageController::class, 'show']);
Route::get('/page/{id}', [PageController::class, 'get']);

// --------------
use App\Models\Subsection;

Route::get('/subsections/{id}', function ($id) {
    return Subsection::findOrFail($id);
});

