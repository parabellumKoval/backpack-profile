# Backpack Profile

Comprehensive user profile, referral, and wallet subsystem for Laravel applications that use Backpack. The package ships ready-to-use CRUD panels, API endpoints, referral triggers, wallet management, reward distribution, withdrawal workflows, and a Sanctum-based authentication layer that can be consumed from SPA or mobile clients.

---

## Feature Overview

- **Profile lifecycle**: automatic profile creation for new users, profile CRUD for admins, REST endpoints for profile self-service.
- **Authentication APIs**: email/password registration, login, logout, password reset, email verification, and Socialite OAuth flows (Google/Facebook) with Sanctum personal access tokens.
- **Referral engine**: multi-level referral tree, reward events, trigger registry, reward ledger, and configurable payout logic.
- **Wallet subsystem**: balance table, ledger journal, hold/release/capture semantics, CurrencyConverter integration, and withdrawal processing workflow.
- **Backpack admin UI**: CRUD panels for profiles, referrals, rewards, reward events, wallet ledger, withdrawals, and an analytic dashboard with charts.
- **Settings integration**: configuration groups exposed through the Backpack Settings package (currencies, withdrawal thresholds, trigger configuration, etc.).
- **Extensibility hooks**: event listeners on user/profile creation, trigger registry, facades/services for programmatic access, publishable assets for overriding views/routes/config.

---

## Requirements

- PHP 8.2+
- Laravel 12.x
- Backpack 6.x (CRUD + Settings packages)
- Laravel Sanctum 4.2+ for API tokens
- Laravel Socialite 5.23+ for social login (optional)
- Database driver that supports JSON columns (MySQL 5.7+/MariaDB 10.2+/PostgreSQL 9.4+)

---

## Installation

1. **Install the package**

   ```bash
   composer require parabellumkoval/backpack-profile
   ```

2. **Run migrations**

   ```bash
   php artisan migrate
   ```

   This creates core tables such as `ak_profiles`, `ak_referral_partners`, `ak_reward_events`, `ak_rewards`, `ak_wallet_balances`, `ak_wallet_ledger`, and `ak_withdrawal_requests`.

3. **(Optional) Publish assets**

   ```bash
   php artisan vendor:publish --provider="Backpack\Profile\ServiceProvider" --tag=config
   php artisan vendor:publish --provider="Backpack\Profile\ServiceProvider" --tag=views
   php artisan vendor:publish --provider="Backpack\Profile\ServiceProvider" --tag=migrations
   php artisan vendor:publish --provider="Backpack\Profile\ServiceProvider" --tag=routes
   ```

   - Config is copied to `config/backpack/profile.php`.
   - Views land under `resources/views/vendor/profile` and `resources/views/vendor/backpack/crud`.
   - Migrations and route stubs are publishable if you need to customize them heavily.

4. **Configure Backpack Settings (recommended)**

   Register `Backpack\Profile\app\Settings\ProfileSettingsRegistrar` inside your Backpack Settings registrar so that referral, wallet, and withdrawal options become editable from the admin UI.

---

## Quick Start Checklist

| Task | Notes |
| ---- | ----- |
| Configure `config/auth.php` guards | Define a `profile` guard if you plan to use profile-authenticated routes. |
| Sanctum personal access tokens | Ensure `config/sanctum.php` is set up; SPA/mobile clients should store bearer tokens securely. |
| Socialite credentials | Set `GOOGLE_CLIENT_ID`, `FACEBOOK_CLIENT_ID`, etc., and update `config/services.php`. |
| Currency converter binding | Bind `Backpack\Profile\app\Contracts\CurrencyConverter` to your implementation if you need real conversion rates. |
| Settings defaults | Review `config/backpack/profile.php` (currencies, referral levels, middlewares, withdrawal minimums). |

---

## Database Schema

| Table | Purpose |
| ----- | ------- |
| `ak_profiles` | Profile metadata linked 1:1 with the `users` table (`user_id`). Stores locale, sponsor, marketing opt-in flags, referral code, etc. |
| `ak_referral_partners` | Flattened referral tree with parent relationships for fast traversal. |
| `ak_reward_events` | Event log for referral trigger executions (pending/processing/processed/failed, reversal support). |
| `ak_rewards` | Monetary/points rewards issued to beneficiaries (actor or upline) per event. |
| `ak_wallet_balances` | Snapshot of wallet balances per user and currency. |
| `ak_wallet_ledger` | Journal of wallet operations (credit/debit/hold/release/capture). |
| `ak_withdrawal_requests` | Withdrawal workflow records, including FX metadata and admin actions. |
| `ak_event_counters` | Incremental counters for idempotent trigger execution (`subject_type` + `subject_id`). |

> Note: legacy code still references columns such as `firstname`, `lastname`, and `addresses`. If you rely on these attributes, ensure your schema includes matching columns or adjust the model to align with the published migrations. See `IMPROVEMENTS.md` for known inconsistencies.

---

## Configuration Highlights

`config/backpack/profile.php` drives most behavior:

- `user_model` / `profile_model`: customize linked Eloquent classes.
- `private_middlewares`: middleware stack applied to wallet/withdrawal API routes (defaults to `['api', 'auth.api:sanctum']`).
- `referral_enabled`, `referral_levels`, `referral_commissions`: legacy defaults for simple multi-level referral payouts.
- `points` group: toggles the virtual points currency used for wallet operations (`key`, `name`, `base` currency backing the point).
- `currencies`: list of fiat currencies exposed to the UI; affects select boxes and labels.
- `currency_converter`: class implementing `Backpack\Profile\app\Contracts\CurrencyConverter` used by `WithdrawalService` and referral payouts.
- `withdrawal` group: enable/disable withdrawals and configure minimum amounts.
- `users` group: allow self-registration, require email verification, default role/locale.

Backpack Settings extender (`ProfileSettingsRegistrar`) mirrors these options in a GUI-friendly manner. Ensure the registrar is invoked by the host application so admins can manage referral triggers, currencies, and withdrawal policies.

---

## Backpack Admin Panels

Once the service provider boots, the following routes are available under the Backpack prefix (default `/admin`):

- `profile` CRUD (`ProfileCrudController`): manage user profiles, inspect balances, sponsors, and contact data.
- `referrals` list (`ReferralsCrudController`): list only users with downline referrals, with expandable detail rows.
- `rewards` list (`RewardCrudController`): read-only grid of issued rewards, filters by beneficiary type, amount, currency.
- `reward-events` list (`RewardEventCrudController`): inspect trigger executions, re-process or create reversal events.
- `wallet-ledger` list (`WalletLedgerCrudController`): journal of wallet movements.
- `withdrawals` list (`WithdrawalRequestCrudController`): approve/reject/mark paid, view FX and payout metadata.
- `profile-dashboard` (`ProfileDashboardController`): dashboard with aggregate stats, chart.js graphs for wallet flows, reward volumes, recent referrals, withdrawals.
- `referrals/settings`: placeholder for future Settings UI (requires integration with Backpack Settings).

All admin controllers rely on Backpack's CRUD operations, custom columns (e.g., `user_card`, `price`, `status`), and the translation strings shipped under `resources/lang/en|ru`.

---

## API Surface

### Authentication (`/api/auth/...`)

- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/logout` (Sanctum token required)
- `GET /api/auth/me` (Sanctum token required)
- `POST /api/auth/password/change` (Sanctum token required)
- `POST /api/auth/password/forgot`
- `POST /api/auth/password/reset`
- `POST /api/auth/email/verification-notification` (Sanctum token + throttle)
- `POST /api/auth/email/resend` (throttle)
- `GET /api/auth/verify-email/{id}/{hash}` (signed link)
- `GET /api/auth/oauth/{provider}/url`
- `GET /api/auth/oauth/{provider}/callback`

Responses are JSON-only thanks to `ForceJsonResponse` middleware. The detailed request/response contract and recommended Nuxt/Auth.js integration steps are documented in `AUTH_SYSTEM.md`.

### Profile (`/api/profile/...`)

- `GET /api/profile` (guard `auth:profile`) — returns `ProfileFullResource`.
- `POST /api/profile/update` (currently guarded by `auth:sanctum`) — updates allowed fields after validation.
- `GET /api/profile/referrals` (guard `auth:profile`) — paginated downline list using `ProfileTinyResource`.

> Important: the controller expects an authenticated `profile` guard but the update route is wired to `auth:sanctum`. Align guards in your application or adjust the controller as described in `IMPROVEMENTS.md`.

### Wallet & Withdrawals (`/api/wallet/...`)

Routes are wrapped in middleware from `Settings::get('profile.private_middlewares')` (defaults to `['api', 'auth.api:sanctum']`).

- `GET /api/wallet/withdrawals`
- `POST /api/wallet/withdrawals`
- `POST /api/wallet/withdrawals/{id}/cancel`

`WithdrawalService` enforces minimum amounts, currency conversion, wallet holds, and ledger bookkeeping. Admin actions (approve/reject/mark paid) are exposed through Backpack routes.

---

## Services & Facades

- `Backpack\Profile\app\Services\Profile`: high-level API for triggering referral events, reversing events, resolving currencies, and retrieving the configured user model.
- `Backpack\Profile\app\Services\ReferralService`: core engine that records events, resolves uplines, distributes rewards, and performs reversals.
- `Backpack\Profile\app\Services\WithdrawalService`: creates withdrawal requests, handles approval/rejection/capture, interacts with wallet balances.
- `Backpack\Profile\app\Services\WalletService`: implements `authorize`, `capture`, `void`, and balance retrieval on the wallet ledger.
- `Backpack\Profile\app\Services\TriggerRegistry`: registry for referral triggers implementing `Backpack\Profile\app\Contracts\ReferralTrigger`.
- `Backpack\Profile\app\Services\ProfileFactory`: builds `Profile` instances and resolves sponsor linkage based on referral codes.
- `Profile` facade (`Backpack\Profile\app\Facades\Profile`): shortcut to the `Profile` service.
- Middleware: `CaptureReferral`, `ForceJsonResponse`, `CheckIfAuthenticateProfile`.

All services are container-bound in `ServiceProvider`. You can override bindings in your application service provider if you need to replace behavior.

---

## Events, Notifications, and Observers

- Events: `ProfileCreating`, `UserCreating` fire during model creation; default listeners populate names and (optionally) referral codes.
- Notifications: placeholders for user onboarding (`UserRegistred`, `ReferralRegistred`, `ReferralBonus`, etc.) and withdrawal completion emails.
- Observers: `ProfileObserver` and `TransactionObserver` include legacy logic; review them before enabling in production.

---

## Extending the Referral Engine

1. Implement `Backpack\Profile\app\Contracts\ReferralTrigger`.
2. Register the trigger alias with `TriggerRegistry::register($alias, $class)`, typically from a service provider or module boot method.
3. Configure trigger settings (fixed/percent payouts, actor award, level distribution) via the Backpack Settings UI or `config/settings.php`.
4. Invoke `Profile::trigger($alias, $externalId, $payload, $actorUserId, $opts)` whenever an event occurs (e.g., order paid).

Reversals can be created via `Profile::reverse(...)` or admin UI actions.

---

## Frontend Integration Notes

- Authentication is token-based: clients must send `Authorization: Bearer {token}` with Sanctum personal access tokens.
- API responses are JSON, validation errors use standard Laravel structure (`errors` array).
- Email verification is optional and controlled by `profile.users.require_email_verification`.
- Social login flows expect the frontend to call `/oauth/{provider}/url`, redirect the browser, and handle `/callback` with optional redirect back to the SPA.
- `AUTH_SYSTEM.md` contains step-by-step guidance tailored for Nuxt 3 + Auth.js, including error handling and state transitions.

---

## Testing & Development

- The repository currently ships with placeholder tests under `packages/backpack/profile/tests`. Update them to use PHPUnit/Pest against the modern package namespace before relying on automated coverage.
- Consider adding feature tests that cover registration, login, referral triggers, withdrawal lifecycle, and admin actions to avoid regressions.

---

## Known Gaps & Roadmap

See `IMPROVEMENTS.md` for a curated backlog of technical debt and missing functionality (schema mismatches, outdated controllers, guard inconsistencies, missing tests, etc.). Addressing those items is recommended before turning the package into a reusable release.

---

## License

The package is open-sourced software licensed under the [MIT license](LICENSE).
