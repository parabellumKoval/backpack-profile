# Improvement Backlog

Identified weaknesses and incomplete areas of the `Backpack\Profile` package. Items are grouped by urgency. File paths refer to the package root (`packages/backpack/profile`).

---

## Critical Fixes

- **Profile schema mismatch**
  - **Impact**: The `Profile` model (`src/app/Models/Profile.php`) expects columns such as `firstname`, `lastname`, `photo`, `addresses`, and `extras`, while the published migration `src/database/migrations/2025_09_07_000000_create_ak_profiles_table.php` creates `first_name`, `last_name`, `avatar_url`, and no `addresses` column. This breaks mass-assignment, casts, and API resources (`ProfileFullResource`).
  - **Action**: Align the migration with the model (add missing columns and correct names) or refactor the model/resources to match the new schema. Update factories accordingly.

- **Guard misuse in profile API**
  - **Impact**: `ProfileController::update` (`src/app/Http/Controllers/Api/ProfileController.php:44`) authenticates the route with `auth:sanctum` but retrieves the user via `Auth::guard('profile')`, which returns `null` unless a profile guard is set and the request is authenticated through it. This makes the update endpoint unusable.
  - **Action**: Replace guard usage with `$request->user()` or ensure the route consistently uses the `profile` guard. Document the expected guard configuration.

- **Currency converter fallback signature**
  - **Impact**: In `ServiceProvider::register()` (`src/ServiceProvider.php:88`), the fallback implementation of `Backpack\Profile\app\Contracts\CurrencyConverter` omits the `$fixTo` argument defined in the interface. Calling `convert()` without the parameter causes a fatal error.
  - **Action**: Update the anonymous class to match the interface signature (`convert(float $amount, string $from, string $to, int $fixTo)`).

- **Settings controller references missing classes**
  - **Impact**: `SettingsCrudController::store` (`src/app/Http/Controllers/Admin/SettingsCrudController.php:33`) references `TranslatorSettings`, `translator::settings` translations, and other translator-specific artifacts that are not part of this package. Submitting the form will crash.
  - **Action**: Implement a dedicated settings persistence layer or remove the controller until a working implementation is provided.

---

## High Priority

- **Contract/implementation drift**
  - `Wallet` contract (`src/app/Contracts/Wallet.php`) expects `balance(int $userId)` without a currency parameter, but `WalletService` (`src/app/Services/WalletService.php`) requires currency arguments and performs multi-currency operations.
  - **Action**: Update the contract or provide an adapter that satisfies both use cases.

- **Legacy controllers in `Auth copy` namespace**
  - `src/app/Http/Controllers/Auth copy/*` and `routes/web/auth copy.php` contain outdated logic. Their mere presence confuses contributors.
  - **Action**: Either delete the copies or move the relevant logic into tests/backups outside of production code.

- **Admin settings view lacks content**
  - `resources/views/settings.blade.php` renders an empty `<form>` while referencing scripts for non-existent fields.
  - **Action**: Either populate the form with actual settings fields or remove the view until ready.

- **Tests reference legacy namespace**
  - `tests/accountTest.php` points to `aimix\account` namespaces and offers no real coverage.
  - **Action**: Replace with meaningful Pest/PHPUnit tests targeting current package functionality.

- **Service provider publishes migrations into `resources/database`**
  - Publishing migrations to `resource_path('database/migrations')` (`src/ServiceProvider.php:69`) is non-standard and breaks artisan expectations.
  - **Action**: Switch to `database_path('migrations')`.

---

## Medium Priority

- **Profile request validation**
  - `ProfileRequest` (`src/app/Http/Requests/ProfileRequest.php`) validates `email` against `ak_profiles`, yet emails are stored on the user model. This leads to duplicate validation messages or inconsistent uniqueness checks.
  - **Action**: Validate against the user table or allow configurable table names.

- **Referral middleware logging assumes missing tables**
  - `CaptureReferral` middleware (`src/app/Http/Middleware/CaptureReferral.php`) inserts into `ak_referral_clicks`, which is not created by the package migrations.
  - **Action**: Add a migration for referral clicks or make logging optional/conditional.

- **Outdated observers and notifications**
  - `ProfileObserver`, `TransactionObserver`, and multiple notifications reference legacy models (`Aimix\Account`). They are disabled but should be modernized or removed.

- **API surface cleanup**
  - `routes/api/profile.php` retains a commented `test` route and unused password change endpoint.
  - **Action**: Remove dead code and document supported endpoints.

- **Social account table missing**
  - `SocialAccount` model expects `ak_social_accounts`, but no migration is shipped.
  - **Action**: Provide the migration or remove the model.

---

## Housekeeping

- **Documentation debt**
  - The new README references `AUTH_SYSTEM.md` and this document; ensure published packages include all necessary docs.

- **PSR-4 capitalization**
  - `ProfileRequest` resides in `app/http/Requests`, but the namespace uses a lowercase `http`. Align folder casing to avoid autoload issues on case-sensitive filesystems.

- **Static analysis & coding standards**
  - Introduce PHPStan/Pint configs (already referenced in `composer.json` scripts) and ensure the codebase passes them.

- **Translation keys**
  - Multiple views/controllers refer to translation namespaces such as `translator::settings` that are unrelated to this package. Audit translation usage.

- **Configuration defaults**
  - `config/profile.php` sets `private_middlewares` to `auth.api:sanctum`, which is not a Laravel default middleware. Provide guidance or change the default to `auth:sanctum`.

---

## Suggested Next Steps

1. Resolve critical schema and guard issues, then add regression tests for profile updates and registration/login.
2. Clean legacy artifacts (unused controllers, observers, tests) to reduce confusion.
3. Bring migrations, models, and factories into alignment and document the expected database structure.
4. Harden the admin settings flow or temporarily disable it to avoid broken UI actions.
5. Plan future enhancements (e.g., trigger management UI, queue integration) once the foundation is stable.
