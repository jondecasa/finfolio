<?php

namespace App\Http\Controllers;

use App\Services\PortfolioService;
use Illuminate\Http\Request;

class PositionsController extends Controller
{
    public function __construct(protected PortfolioService $portfolio) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $base = $this->portfolio->baseCurrency($user);

        // Optional filter to a single account.
        $account = null;
        if ($request->filled('account')) {
            $account = $user->accounts()->find($request->query('account'));
            abort_unless($account, 404);
        }

        $holdings = $this->portfolio->holdings($user)
            ->when($account, fn ($c) => $c->where('account_id', $account->id));

        $positions = $holdings->map(function ($h) use ($base) {
            $value = $this->portfolio->holdingValue($h, $base);
            $invested = $this->portfolio->holdingInvested($h, $base);
            $equityInvested = $this->portfolio->holdingEquityInvested($h, $base);

            return [
                'holding' => $h,
                'value' => $value,
                'invested' => $invested,
                'gain' => $this->portfolio->holdingGross($h, $base) - $invested,
                'gain_pct' => $this->portfolio->holdingGainPct($h, $base),
                'equity_invested' => $equityInvested,
                'equity_gain_pct' => $this->portfolio->holdingEquityGainPct($h, $base),
                'day_change_pct' => $h->asset->dayChangePct(),
            ];
        })->sortByDesc('value')->values();

        return view('positions', [
            'currency' => $base,
            'positions' => $positions,
            'account' => $account,
            'accounts' => $user->accounts()->get(),
        ]);
    }
}
