<?php

namespace App\Http\Controllers;

use App\Models\Tsedaka;
use Illuminate\Http\Request;

class TsedakaController extends Controller
{
    public function list()
    {
        $tsedakas = Tsedaka::with('transaction')->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $tsedakas
        ]);
    }
}
