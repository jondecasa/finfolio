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
        $overview = $this->portfolio->overview($user);
        $base = $overview['currency'];

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

            return [
                'holding' => $h,
                'value' => $value,
                'invested' => $invested,
                'gain' => $this->portfolio->holdingGross($h, $base) - $invested,
                'gain_pct' => $this->portfolio->holdingGainPct($h, $base),
                'day_change_pct' => $h->asset->dayChangePct(),
            ];
        })->sortByDesc('value')->values();

        // Headline figures: whole portfolio, or just the picked account.
        if ($account) {
            $row = collect($overview['accounts'])->firstWhere('account.id', $account->id);
            $summary = [
                'currency' => $base,
                'total_value' => $row['value'] ?? 0,
                'total_invested' => $row['invested'] ?? 0,
                'total_gain' => $row['gain'] ?? 0,
                'total_gain_pct' => $row['gain_pct'] ?? null,
                'day_change' => $row['day_change'] ?? 0,
                'day_change_pct' => $row['day_change_pct'] ?? null,
            ];
        } else {
            $summary = $overview;
        }

        return view('positions', [
            'summary' => $summary,
            'positions' => $positions,
            'account' => $account,
            'accounts' => $user->accounts()->get(),
        ]);
    }
}
