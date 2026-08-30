<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\PortfolioService;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public const TYPES = ['individual', 'retirement', 'savings', 'brokerage', 'crypto', 'business'];

    public function index(Request $request, PortfolioService $portfolio)
    {
        $overview = $portfolio->overview($request->user());
        $valueByAccount = collect($overview['accounts'])->keyBy(fn ($row) => $row['account']->id);

        return view('accounts.index', [
            'accounts' => $request->user()->accounts()->withCount('holdings')->get(),
            'valueByAccount' => $valueByAccount,
            'currency' => $overview['currency'],
            'types' => self::TYPES,
            'currencyOptions' => ['EUR', 'USD', 'GBP', 'CHF', 'JPY', 'CAD', 'AUD', 'SEK', 'NOK', 'DKK', 'PLN'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'type' => ['nullable', 'string', 'max:30'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $request->user()->accounts()->create([
            'name' => $data['name'],
            'type' => $data['type'] ?? 'individual',
            'currency' => strtoupper($data['currency'] ?? $request->user()->currency()),
            'sort' => ($request->user()->accounts()->max('sort') ?? 0) + 1,
        ]);

        return redirect()->route('accounts.index')->with('status', 'Account created.');
    }

    public function update(Request $request, Account $account)
    {
        abort_unless($account->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'type' => ['nullable', 'string', 'max:30'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $account->update([
            'name' => $data['name'],
            'type' => $data['type'] ?? $account->type,
            'currency' => strtoupper($data['currency'] ?? $account->currency),
        ]);

        return redirect()->route('accounts.index')->with('status', 'Account updated.');
    }

    public function destroy(Request $request, Account $account, PortfolioService $portfolio)
    {
        abort_unless($account->user_id === $request->user()->id, 403);

        if ($request->user()->accounts()->count() <= 1) {
            return redirect()->route('accounts.index')->withErrors(['account' => 'You need at least one account.']);
        }

        $name = $account->name;
        $positions = $account->holdings()->count();

        // Holdings (and this account's snapshots) cascade at the database level.
        $account->delete();

        $portfolio->snapshot($request->user());

        $note = $positions > 0
            ? "\"{$name}\" and its {$positions} ".str('position')->plural($positions).' were deleted.'
            : "\"{$name}\" was deleted.";

        return redirect()->route('accounts.index')->with('status', $note);
    }
}
