<?php

namespace App\Http\Controllers;

use App\Services\PortfolioService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct(protected PortfolioService $portfolio) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $range = $request->query('range', '1W');

        $overview = $this->portfolio->overview($user);
        $series = $this->portfolio->series($user, $range);

        return view('home', [
            'overview' => $overview,
            'series' => $series,
            'ranges' => config('finfolio.ranges'),
            'activeRange' => $range,
        ]);
    }
}
