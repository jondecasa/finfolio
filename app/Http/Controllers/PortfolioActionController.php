<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\PortfolioService;
use App\Services\PriceService;
use Illuminate\Http\Request;

class PortfolioActionController extends Controller
{
    public function __construct(
        protected PriceService $prices,
        protected PortfolioService $portfolio,
    ) {}

    public function refresh(Request $request)
    {
        $assetIds = $this->portfolio->holdings($request->user())
            ->pluck('asset_id')
            ->unique();

        $assets = Asset::whereIn('id', $assetIds)->where('type', '!=', 'cash')->get();

        $updated = $this->prices->refresh($assets);
        $this->portfolio->snapshot($request->user());

        return back()->with('status', "Prices updated ({$updated} assets).");
    }

    public function toggleVisibility(Request $request)
    {
        $user = $request->user();
        $user->update(['values_hidden' => ! $user->values_hidden]);

        return back();
    }
}
