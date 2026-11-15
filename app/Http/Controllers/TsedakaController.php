<?php

namespace App\Http\Controllers;

use App\Models\Tsedaka;

class TsedakaController extends Controller
{
    public function list()
    {
        $tsedakas = Tsedaka::with('transaction')->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $tsedakas,
        ]);
    }
}
