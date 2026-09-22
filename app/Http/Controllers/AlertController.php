<?php

namespace App\Http\Controllers;

use App\Models\PriceAlert;
use App\Services\Notifications\TelegramNotifier;
use App\Services\PriceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlertController extends Controller
{
    public function __construct(protected PriceService $prices) {}

    public function index(Request $request, TelegramNotifier $telegram)
    {
        $alerts = $request->user()->priceAlerts()
            ->with('asset')
            ->orderByDesc('active')
            ->orderByDesc('created_at')
            ->get();

        return view('alerts.index', [
            'alerts' => $alerts,
            'telegramConfigured' => $telegram->isConfigured(),
        ]);
    }

    public function create()
    {
        return view('alerts.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['crypto', 'stock', 'etf', 'index', 'fund', 'commodity'])],
            'symbol' => ['required', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:120'],
            'currency' => ['nullable', 'string', 'size:3'],
            'provider_id' => ['nullable', 'string', 'max:120'],
            'logo_url' => ['nullable', 'string', 'max:255'],
            'condition' => ['required', Rule::in(PriceAlert::CONDITIONS)],
            'target_price' => ['required', 'numeric', 'gt:0'],
        ]);

        $asset = $this->prices->resolveAsset([
            'type' => $data['type'],
            'symbol' => $data['symbol'],
            'name' => $data['name'] ?? $data['symbol'],
            'currency' => $data['currency'] ?? null,
            'provider_id' => $data['provider_id'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
        ]);

        $request->user()->priceAlerts()->create([
            'asset_id' => $asset->id,
            'condition' => $data['condition'],
            'target_price' => $data['target_price'],
        ]);

        return redirect()->route('alerts.index')->with('status', "Alert set for {$asset->symbol}.");
    }

    public function rearm(Request $request, PriceAlert $alert)
    {
        $this->authorizeAlert($request, $alert);

        $alert->update(['active' => true, 'triggered_at' => null]);

        return back()->with('status', 'Alert re-armed.');
    }

    public function destroy(Request $request, PriceAlert $alert)
    {
        $this->authorizeAlert($request, $alert);

        $alert->delete();

        return back()->with('status', 'Alert removed.');
    }

    protected function authorizeAlert(Request $request, PriceAlert $alert): void
    {
        abort_unless($alert->user_id === $request->user()->id, 403);
    }
}
