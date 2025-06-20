<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    // 🔐 Ajouter un nouvel utilisateur
    public function addUser(Request $request)
    {
        // Vérifie que le pseudo est unique avant de continuer.
        if (User::where('pseudo', $request->input('pseudo'))->exists()) {
            return response()->json(['error' => 'Le pseudo est déjà utilisé.'], 400);
        }

        // Création d'une nouvelle instance de User
        $user = new User;
        $user->name = $request->input('nom');
        $user->pseudo = $request->input('pseudo');
        $user->password = Hash::make($request->input('password'));
        $user->role = $request->input('role');
        $user->save();

        return response()->json(['message' => 'Utilisateur ajouté', 'user' => $user]);
    }

    // ✏️ Modifier un utilisateur
    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $user->name = $request->name ?? $user->name;
        $user->pseudo = $request->pseudo ?? $user->pseudo;
        $user->role = $request->role ?? $user->role;

        if ($request->password) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json(['message' => 'Utilisateur mis à jour', 'user' => $user]);
    }

    // ❌ Supprimer un utilisateur
    public function deleteUser(Request $request, $id)
    {

        $authUserId = $request->query('user_id'); // ID de l’utilisateur connecté
        $authUser = User::find($authUserId);

        if (!$authUser) {
            return response()->json(['status' => 'Erreur : ID utilisateur invalide.'], 400);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['status' => 'Utilisateur introuvable'], 404);
        }

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé avec succès !', 'status' => 'deleted']);
    }

    // 🔓 Connexion avec Sanctum
    public function login(Request $request)
    {

        // Recherche l'utilisateur en fonction du pseudo fourni.
        $user = User::where('pseudo', $request->pseudo)->first();

        // Vérifie si l'utilisateur existe et si le mot de passe est correct.
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Pseudo ou mot de passe incorrect'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user]);
    }

    // Fonction pour récupérer un user spécifique par son ID
    function getUser($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé.'], 404);
        }
        // Retourne l'user correspondant à l'ID donné
        return response()->json([
            'status' => 'success',
            'user' => $user,
        ], 200);
    }

    // Récuperer tous les Users
    function listeUser()
    {
        // Retourne tous les produits sous forme de collection
        $users = User::all();
        // Retourne la collection d'utilisateurs
        return response()->json([
            'status' => 'success',
            'users' => $users,
        ], 200);
    }
}
