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
            $invested = $row['invested'] ?? 0;
            $liabilities = $row['debt'] ?? 0;
            $dayChange = $row['day_change'] ?? 0;
            $dayChangePct = $row['day_change_pct'] ?? null;
        } else {
            $netValue = $overview['total_value'];
            $invested = $overview['total_invested'];
            $liabilities = $overview['total_debt'];
            $dayChange = $overview['day_change'];
            $dayChangePct = $overview['day_change_pct'];
        }

        // Total return measured on NET worth (after debt) against what was put in.
        $totalReturn = $netValue - $invested;

        $summary = [
            'currency' => $base,
            'net_value' => $netValue,
            'liabilities' => $liabilities,
            'total_return' => $totalReturn,
            'total_return_pct' => $invested > 0 ? $totalReturn / $invested * 100 : null,
            'day_change' => $dayChange,
            'day_change_pct' => $dayChangePct,
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
