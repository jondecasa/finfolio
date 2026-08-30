<?php

namespace App\Http\Controllers;

use App\Services\PortfolioService;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(protected PortfolioService $portfolio) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $allocation = $this->portfolio->allocation($user);

        $tab = in_array($request->query('tab'), ['positions', 'type'], true)
            ? $request->query('tab')
            : 'positions';

        return view('analytics', [
            'allocation' => $allocation,
            'overview' => $this->portfolio->overview($user),
            'tab' => $tab,
        ]);
    }
}
