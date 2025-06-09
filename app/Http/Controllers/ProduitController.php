<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProduitController extends Controller
{
    public function addProduit(Request $request)
    {
        $request->validate([
            'nom' => 'required|string|max:255',
            'description' => 'nullable|string',
            'prix' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|file|image|max:2048', // max 2MB
            'est_actif' => 'boolean',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('produits', 'public');
        }

        $produit = Produit::create([
            'nom' => $request->nom,
            'description' => $request->description,
            'prix' => $request->prix,
            'stock' => $request->stock,
            'image' => $imagePath ? Storage::url($imagePath) : null,
            'est_actif' => $request->est_actif ?? true,
        ]);

        return response()->json(['message' => 'Produit ajouté avec succès', 'produit' => $produit], 201);
    }

    public function listeProduits()
    {
        $produits = Produit::all();
        return response()->json($produits);
    }

    public function updateProduit(Request $request, $id)
    {
        $produit = Produit::findOrFail($id);

        $request->validate([
            'nom' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'prix' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'image' => 'nullable|file|image|max:2048',
            'est_actif' => 'boolean',
        ]);

        $data = $request->only(['nom', 'description', 'prix', 'stock', 'est_actif']);

        if ($request->hasFile('image')) {
            if ($produit->image && Storage::exists(str_replace('/storage/', '', $produit->image))) {
                Storage::delete(str_replace('/storage/', '', $produit->image));
            }

            $imagePath = $request->file('image')->store('produits', 'public');
            $data['image'] = Storage::url($imagePath);
        }

        $produit->update($data);

        return response()->json(['message' => 'Produit mis à jour', 'produit' => $produit]);
    }

    public function deleteProduit($id)
    {
        $produit = Produit::findOrFail($id);

        if ($produit->image && Storage::exists(str_replace('/storage/', '', $produit->image))) {
            Storage::delete(str_replace('/storage/', '', $produit->image));
        }

        $produit->delete();

        return response()->json(['message' => 'Produit supprimé', 'statut' => 'deleted']);
    }
}
