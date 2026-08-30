<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PriceService;
use Illuminate\Http\Request;

class AssetSearchController extends Controller
{
    public function __construct(protected PriceService $prices) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:60'],
            'type' => ['nullable', 'in:crypto,stock,etf,index,fund,commodity'],
        ]);

        $results = $this->prices->search($data['q'], $data['type'] ?? null);

        return response()->json(['results' => $results]);
    }
}
