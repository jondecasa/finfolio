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
        $overview = $this->portfolio->overview($user);
        $base = $overview['currency'];

        // Optional filter to a single account (same behaviour as Positions).
        $account = null;
        if ($request->filled('account')) {
            $account = $user->accounts()->find($request->query('account'));
            abort_unless($account, 404);
        }

        $allocation = $this->portfolio->allocation($user, $account?->id);

        // Headline figures: whole portfolio, or just the picked account.
        if ($account) {
            $row = collect($overview['accounts'])->firstWhere('account.id', $account->id);
            $netValue = $row['value'] ?? 0;
            $liabilities = $row['debt'] ?? 0;
            $dayChange = $row['day_change'] ?? 0;
            $dayChangePct = $row['day_change_pct'] ?? null;
            $equityInvested = $row['equity_invested'] ?? 0;
            $equityGain = $row['equity_gain'] ?? 0;
            $equityGainPct = $row['equity_gain_pct'] ?? null;
            $positionsCount = $row['positions'] ?? 0;
        } else {
            $netValue = $overview['total_value'];
            $liabilities = $overview['total_debt'];
            $dayChange = $overview['day_change'];
            $dayChangePct = $overview['day_change_pct'];
            $equityInvested = $overview['total_equity_invested'];
            $equityGain = $overview['total_equity_gain'];
            $equityGainPct = $overview['total_equity_gain_pct'];
            $positionsCount = $overview['positions_count'];
        }

        $summary = [
            'currency' => $base,
            'net_value' => $netValue,
            'liabilities' => $liabilities,
            // Cash equity actually put in (real estate: just the down payment,
            // or the full price if bought outright; everything else: full cost
            // basis) — excludes cash holdings.
            'equity_invested' => $equityInvested,
            // Total return measured against that equity, not the full cost basis:
            // net worth (after debt) minus what was actually put in.
            'total_return' => $equityGain,
            'total_return_pct' => $equityGainPct,
            'day_change' => $dayChange,
            'day_change_pct' => $dayChangePct,
            'positions_count' => $positionsCount,
        ];

        $tab = in_array($request->query('tab'), ['positions', 'type'], true)
            ? $request->query('tab')
            : 'positions';

        return view('analytics', [
            'allocation' => $allocation,
            'summary' => $summary,
            'account' => $account,
            'accounts' => $user->accounts()->get(),
            'tab' => $tab,
        ]);
    }
}
