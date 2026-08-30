<?php

namespace App\Http\Controllers;

use App\Services\PortfolioService;
use Illuminate\Http\Request;

class NetWorthController extends Controller
{
    public function __construct(protected PortfolioService $portfolio) {}

    public function index(Request $request)
    {
        $user = $request->user();

        return view('networth', [
            'overview' => $this->portfolio->overview($user),
            'series' => $this->portfolio->series($user, $request->query('range', '1M')),
            'ranges' => config('finfolio.ranges'),
            'activeRange' => $request->query('range', '1M'),
        ]);
    }
}
