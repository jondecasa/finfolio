<?php

namespace App\Http\Controllers;

use App\Services\PortfolioService;
use Illuminate\Http\Request;

class LiabilitiesController extends Controller
{
    public function __construct(protected PortfolioService $portfolio) {}

    public function index(Request $request)
    {
        return view('liabilities.index', [
            'mortgages' => $this->portfolio->mortgages($request->user()),
        ]);
    }
}
