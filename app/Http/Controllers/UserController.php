<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private const ALLOWED_ROLES = [
        'super_admin',
        'staff',
    ];

    private function isAdmin(?User $user): bool
    {
        return $user && in_array($user->role, ['super_admin', 'admin'], true);
    }

    // 🔐 Ajouter un nouvel utilisateur
    public function addUser(Request $request)
    {
        $authUser = $request->user();
        if (!$this->isAdmin($authUser)) {
            return response()->json([
                'error' => 'Accès refusé. Privilèges administrateur requis.'
            ], 403);
        }

        if (User::where('pseudo', $request->input('pseudo'))->exists()) {
            return response()->json(['error' => 'Le pseudo est déjà utilisé.'], 400);
        }

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'pseudo' => 'required|string|max:255|unique:users,pseudo',
            'password' => 'required|string|min:6',
            'role' => ['required', 'string', Rule::in(self::ALLOWED_ROLES)],
        ]);

        $user = new User;
        $user->name = $validated['nom'];
        $user->pseudo = $validated['pseudo'];
        $user->password = Hash::make($validated['password']);
        $user->role = $validated['role'];
        $user->save();

        return response()->json(['message' => 'Utilisateur ajouté', 'user' => $user]);
    }

    // ✏️ Modifier un utilisateur
    public function updateUser(Request $request, $id)
    {
        $authUser = $request->user();
        $user = User::findOrFail($id);

        $isAdmin = $this->isAdmin($authUser);
        $isSelf = $authUser && ((int) $authUser->id === (int) $user->id);

        if (!$isAdmin && !$isSelf) {
            return response()->json([
                'error' => 'Accès refusé. Vous ne pouvez modifier que votre propre profil.'
            ], 403);
        }

        // Empêcher l'élévation de privilèges par un non-administrateur
        if ($request->filled('role') && $request->role !== $user->role && !$isAdmin) {
            return response()->json([
                'error' => 'Accès refusé. Seul un administrateur peut modifier les rôles.'
            ], 403);
        }

        $request->validate([
            'name' => 'nullable|string|max:255',
            'pseudo' => ['nullable', 'string', 'max:255', Rule::unique('users', 'pseudo')->ignore($user->id)],
            'role' => ['nullable', 'string', Rule::in(self::ALLOWED_ROLES)],
            'password' => 'nullable|string|min:6',
        ]);

        $user->name = $request->name ?? $user->name;
        $user->pseudo = $request->pseudo ?? $user->pseudo;
        if ($isAdmin && $request->filled('role')) {
            $user->role = $request->role;
        }

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json(['message' => 'Utilisateur mis à jour', 'user' => $user]);
    }

    // ❌ Supprimer un utilisateur (Dette #03 : Authentification stricte Sanctum + RBAC + anti auto-suppression)
    public function deleteUser(Request $request, $id)
    {
        $authUser = $request->user();

        if (!$authUser) {
            return response()->json(['status' => 'Erreur : Utilisateur non authentifié.'], 401);
        }

        if (!$this->isAdmin($authUser)) {
            return response()->json([
                'status' => 'forbidden',
                'error' => 'Accès refusé. Privilèges administrateur requis.',
            ], 403);
        }

        if ((int) $authUser->id === (int) $id) {
            return response()->json([
                'status' => 'error',
                'error' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 422);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['status' => 'Utilisateur introuvable'], 404);
        }

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé avec succès !', 'status' => 'deleted']);
    }

    // 🔓 Connexion avec Sanctum + Cookie HttpOnly (Dette #23)
    public function login(Request $request)
    {
        $user = User::where('pseudo', $request->pseudo)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Pseudo ou mot de passe incorrect'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $cookie = Cookie::make(
            'auth_token',
            $token,
            60 * 24,
            '/',
            null,
            app()->environment('production'),
            true,
            false,
            'Lax'
        );

        return response()->json(['token' => $token, 'user' => $user])->withCookie($cookie);
    }

    // Fonction pour récupérer un user spécifique par son ID
    public function getUser($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'user' => $user,
        ], 200);
    }

    // Récuperer tous les Users
    public function listeUser()
    {
        $users = User::all();

        return response()->json([
            'status' => 'success',
            'users' => $users,
        ], 200);
    }
}
