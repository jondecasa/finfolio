<?php

namespace App\Http\Controllers;

use App\Services\PortfolioService;
use Illuminate\Http\Request;

class DebtController extends Controller
{
    public function __construct(protected PortfolioService $portfolio) {}

    public function index(Request $request)
    {
        return view('debts.index', [
            'mortgages' => $this->portfolio->mortgages($request->user()),
        ]);
    }
}
