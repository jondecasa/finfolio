<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PortfolioService;
use Illuminate\Http\Request;

class ChartController extends Controller
{
    public function __construct(protected PortfolioService $portfolio) {}

    public function series(Request $request)
    {
        $data = $request->validate([
            'range' => ['nullable', 'string'],
            'account_id' => ['nullable', 'integer'],
        ]);

        $accountId = $data['account_id'] ?? null;
        if ($accountId && ! $request->user()->accounts()->whereKey($accountId)->exists()) {
            abort(403);
        }

        return response()->json(
            $this->portfolio->series($request->user(), $data['range'] ?? '1W', $accountId)
        );
    }
}
