# Finfolio

A portfolio tracker built with Laravel 12. Each user records their investment
positions across one or more accounts; the app pulls **live prices** (crypto from
CoinGecko, stocks/ETFs/index funds/commodities from Yahoo Finance) and **live FX
rates**, then renders a getquin-style dark UI: net-worth area chart, allocation
gauge and a per-position breakdown.

The UI is **responsive**: the phone layout (bottom tab bar, single column) is
preserved as-is on small screens, and a desktop layout (left sidebar, wide
multi-column content) kicks in at ≥1024 px. Same Blade views, Tailwind
breakpoints — no separate codebase.

## Screens

| Route | Screen | Notes |
|-------|--------|-------|
| `/home` | Home | Total net worth, area chart with `1D/1W/1M/YTD/1Y/Max` selector, allocation & accounts |
| `/net-worth` | Net Worth | Aggregated + per-account investments, cash & liabilities placeholders |
| `/analytics` | Allocation | Allocation ring (one colour per position/type) with the largest slice in the centre, `All positions` / `Type` tabs, weighted list |
| `/wealth` | Wealth | Market value / invested / return tiles + full holdings list |
| `/discover` | Search | Category selector → live asset search; tap a result to add it |
| `/positions/create`, `/positions/{id}/edit` | Add / edit position | Category selector + manual entry for custom assets |
| `/accounts` | Accounts | Add / rename / retype / delete the accounts positions sit in |
| `/profile` | Profile | Name + base currency + password. **The email address is immutable.** |

## Asset categories

The "what are you searching for?" selector routes each category to a provider:

| Category | Provider | Notes |
|----------|----------|-------|
| Stocks | Yahoo (`EQUITY`) | |
| ETF | Yahoo (`ETF`) | |
| Index funds | Yahoo (`MUTUALFUND` / `INDEX`) | |
| Commodities | Yahoo (`FUTURE`, e.g. `GC=F`) | |
| Crypto | CoinGecko | |
| Real estate | — | Manual. Purchase price, current value and an optional **mortgage/debt**. Net worth counts `current − debt`; the debt appears under Liabilities; appreciation is `current` vs `purchase`. |
| Cash | — | Manual. Pick a currency and an amount — pure cash, converted to your base currency. Shown under Net Worth → Cash balance. |
| Other | — | Manual. Name + a current value; never auto-updates. |

## Stack

- **Laravel 12**, **MySQL / MariaDB** (`finfolio` schema)
- **Laravel Breeze** (Blade) for auth
- **Tailwind CSS + Alpine.js**, **Chart.js** for the charts
- Price providers are pluggable (`config/finfolio.php` → `providers`)

## Getting started

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Create the database (defaults assume XAMPP MySQL on `127.0.0.1:3306`, user `root`,
empty password — adjust `DB_*` in `.env` otherwise):

```bash
mysql -u root -e "CREATE DATABASE finfolio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

```bash
php artisan migrate --seed   # creates demo@finfolio.test / password
npm run build                # or: npm run dev
php artisan serve --port=8123
```

Open http://127.0.0.1:8123 and log in with:

```
demo@finfolio.test / password
```

The seeder creates two accounts (`Individual`, `Individual 2`) holding BTC and ETH,
plus ~7 months of net-worth history so every chart range is populated.

> **Note:** the `intl` PHP extension is not required — currency formatting falls
> back to `App\Support\Money`. Enable `ext-intl` for locale-aware formatting if you
> want it.

## Live data

| Concern | Provider | Key required |
|---------|----------|--------------|
| Crypto quotes & search | CoinGecko (`/coins/markets`, `/search`) | no (optional demo key via `COINGECKO_API_KEY`) |
| Equity/ETF quotes & search | Yahoo Finance (`/v8/finance/chart`, `/v1/finance/search`) | no |
| FX rates | open.er-api.com | no |

Prices and FX are cached (`PRICES_CACHE_TTL`, `FX_CACHE_TTL`).

### Commands

```bash
php artisan prices:refresh        # refresh every tracked asset (--type=crypto to scope)
php artisan portfolio:snapshot    # write a net-worth snapshot per user (--refresh to price first)
```

Both are scheduled in `routes/console.php` (`prices:refresh` every 15 min,
`portfolio:snapshot` hourly). Run the scheduler in production with:

```bash
php artisan schedule:work
```

## Data model

```
users ─┬─< accounts ─┬─< holdings >─ assets      (assets cache live price + day change)
       │             └─< transactions >─ assets
       └─< portfolio_snapshots            (account_id NULL = aggregate; drives the chart)
```

- `holdings.average_cost` and `assets.current_price` are stored in the asset's
  native currency; `App\Services\FxService` converts everything into the user's
  `base_currency` for display.
- `App\Services\PortfolioService` is the single source of truth for the overview,
  allocation and chart series used by the controllers and the `/api/*` endpoints.

## Tests

```bash
php artisan test
```

`tests/Feature/PortfolioTest.php` covers aggregation/FX math, allocation weights,
the authenticated screens, the chart endpoint and adding a position (external HTTP
is faked).
