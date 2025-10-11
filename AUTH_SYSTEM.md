# Authentication & Authorization Specification

Detailed contract of the authentication subsystem shipped with `Backpack\Profile`. This document is intended to serve as a backend reference for integrating a Nuxt 3 SPA that uses Auth.js (or any other token-aware client).

---

## Overview

- **Authentication style**: stateless bearer tokens issued by Laravel Sanctum (`createToken('api')`). Tokens are long-lived personal access tokens that must be stored client-side (HTTP-only cookie or secure storage).
- **Transport format**: JSON only. `ForceJsonResponse` middleware forces `Accept: application/json`, so all validation and error responses follow Laravel's JSON error structure.
- **Primary user model**: resolved from `config('auth.providers.users.model')` (default `App\Models\User`). The profile package does not replace the core user authentication guard.
- **Optional flows**: email verification (Laravel's `MustVerifyEmail`), password reset, password change, Socialite OAuth for Google/Facebook.
- **Rate limiting**: email verification endpoints use `throttle:6,1`. Configure global API throttling separately if required.

---

## Entities

### User Shape

All successful auth responses include:

```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "email_verified_at": "2025-01-01T12:34:56.000000Z"
  },
  "token": "plain-text-token"
}
```

Depending on your `User` model, additional hidden/visible attributes may appear. Ensure only safe attributes are exposed via `$hidden` or `$visible`.

### Token Semantics

- Tokens are created through `Sanctum\HasApiTokens::createToken('api')`.
- The raw plaintext token is returned once; store it immediately.
- Revoke tokens by calling `POST /api/auth/logout` (deletes current token).
- There is no refresh-token mechanism; clients should renew tokens by re-authenticating when needed.

---

## Endpoint Summary

| Method | Path | Auth Requirement | Purpose |
| ------ | ---- | ---------------- | ------- |
| `POST` | `/api/auth/register` | none | Create a new user and issue a token. |
| `POST` | `/api/auth/login` | none | Issue a token for existing user credentials. |
| `POST` | `/api/auth/logout` | Sanctum (`auth:sanctum`) | Revoke current access token. |
| `GET`  | `/api/auth/me` | Sanctum | Fetch authenticated user object. |
| `POST` | `/api/auth/password/change` | Sanctum | Change password for current user. |
| `POST` | `/api/auth/password/forgot` | none | Send password reset link email. |
| `POST` | `/api/auth/password/reset` | none | Reset password using emailed token. |
| `POST` | `/api/auth/email/verification-notification` | Sanctum + throttle | Send verification email to logged-in user. |
| `POST` | `/api/auth/email/resend` | throttle | Send verification email by raw address (does not leak existence). |
| `GET`  | `/api/auth/verify-email/{id}/{hash}` | signed URL | Confirm email address. |
| `GET`  | `/api/auth/oauth/{provider}/url` | none | Fetch Socialite redirect URL (Google/Facebook). |
| `GET`  | `/api/auth/oauth/{provider}/callback` | provider redirect | Handle Socialite callback, create/login user, issue token. |

All routes are grouped under `Route::prefix('api/auth')->middleware(['api', ForceJsonResponse::class])`.

---

## Flow Details

### Registration

```
POST /api/auth/register
Content-Type: application/json
```

Payload:

| Field | Type | Rules | Notes |
| ----- | ---- | ----- | ----- |
| `name` | string | nullable, max:255 | Stored on `User::name`. |
| `email` | string | required, email, max:255, unique on users table | Table is resolved via configured user model. |
| `password` | string | required, `Password::defaults()` | Laravel default complexity, no confirmation field expected in this endpoint. |

Behavior:

- If `Settings::get('profile.users.allow_registration', true)` is false, returns `403` with `{"message":"Registration disabled"}`.
- Creates a user record, hashes password, fires `Registered` event.
- Issues Sanctum token (`token` field).
- If `Settings::get('profile.users.require_email_verification', true)` is true, dispatches the standard email verification notification.
- Response `201 Created`: `{ "user": {...}, "token": "..." }`.

Client handling:

- Store `token` securely.
- Navigate to email verification screen if `user.email_verified_at` is null and verification is required.

### Login

```
POST /api/auth/login
```

Payload:

| Field | Rules |
| ----- | ----- |
| `email` | required, email |
| `password` | required |

Responses:

- `200 OK`: same shape as register (`user`, `token`).
- `401 Unauthorized`: `{"message":"Invalid credentials"}` for wrong email/password.
- `403 Forbidden`: `{ "message": "Email not verified", "code": "email_unverified" }` if verification required but missing.

### Logout

```
POST /api/auth/logout
Authorization: Bearer {token}
```

Deletes the current access token (`$request->user()->currentAccessToken()->delete()`).

- Success: `200 OK`, `{ "ok": true }`.
- Token already missing still returns success (`ok: true`).

### Current User

```
GET /api/auth/me
Authorization: Bearer {token}
```

Returns the authenticated `User` object as serialized by Eloquent (respecting hidden attributes). Use this call for session introspection in Auth.js (`getSession`).

### Change Password

```
POST /api/auth/password/change
Authorization: Bearer {token}
```

Payload:

| Field | Rules |
| ----- | ----- |
| `current_password` | required |
| `password` | required, `Password::defaults()`, confirmed |
| `password_confirmation` | required implicitly by `confirmed` rule |

Responses:

- `200 OK`: `{ "ok": true }`.
- `422 Unprocessable Entity`: `{"message":"Current password is incorrect"}` when old password mismatch.
- Validation errors follow `{ "errors": { "password": ["..."] } }`.

### Forgot Password

```
POST /api/auth/password/forgot
```

Payload: `{ "email": "user@example.com" }`.

Behavior:

- Invokes `Password::sendResetLink`.
- Always returns `200 OK` with `{ "ok": true }` on success, or `422` with `{ "message": "We can't find a user with that e-mail address." }` if the broker reports an error.
- No timing attacks mitigations beyond Laravel defaults.

### Reset Password

```
POST /api/auth/password/reset
```

Payload:

| Field | Rules |
| ----- | ----- |
| `token` | required |
| `email` | required, email |
| `password` | required, defaults(), confirmed |
| `password_confirmation` | required implicitly |

- On success: `{ "ok": true }`.
- On failure: `422` with localized `message` from password broker.

### Email Verification

- **Send to logged-in user**:

  ```
  POST /api/auth/email/verification-notification
  Authorization: Bearer {token}
  ```

  - Throttled (`throttle:6,1`).
  - If already verified: `200 OK`, `{ "message": "Already verified" }`.
  - Otherwise: `{ "ok": true }`.

- **Send by email**:

  ```
  POST /api/auth/email/resend
  { "email": "user@example.com" }
  ```

  - Always returns `{ "ok": true }` to avoid leaking whether the email exists.
  - Sends verification email only when the user exists and is unverified.

- **Verify**:

  ```
  GET /api/auth/verify-email/{id}/{hash}?signature=...
  ```

  - Signed route (use Laravel's `URL::temporarySignedRoute` inside notifications).
  - If `profile.email_verified_redirect` config is set, the controller redirects to that URI after success (typical use: redirect back to SPA route). Otherwise returns `{ "ok": true }`.
  - Errors: `403` for invalid hash, `200` with `"Already verified"` if repeated.

### Social Login (Google/Facebook)

Allowed providers hardcoded in `OAuthController::$allowed = ['google','facebook']`.

1. **Obtain redirect URL**:

   ```
   GET /api/auth/oauth/google/url?redirect_uri=https://yourapp.com/auth/google/callback
   ```

   - Optional query `redirect_uri` overrides `config("services.google.redirect")`.
   - Returns `{ "url": "https://accounts.google.com/o/oauth2/..." }`.
   - Frontend should redirect the browser to this URL.

2. **Callback handler**:

   ```
   GET /api/auth/oauth/google/callback?code=...&state=...
   ```

   - Accepts same `redirect_uri` parameter if you use custom values.
   - Exchanges code for user profile, finds user by email, creates one if missing (random password, marks email verified).
   - Issues Sanctum token.
   - If query `state_redirect=` or config `profile.oauth_front_redirect` is set, controller redirects there appending `?token=...`. Otherwise returns JSON: `{ "user": {...}, "token": "...", "provider": "google" }`.

Error cases:

- Unknown provider → `404`.
- Socialite exceptions bubble up (ensure try/catch if needed).
- Email-less Socialite profiles will hit `null` email; extend controller if you need alternative keys.

---

## Nuxt 3 + Auth.js Integration Blueprint

1. **Create a custom Auth.js provider** that:
   - Calls `POST /api/auth/login` inside the `signin` handler and stores `token`.
   - Uses the `token` in `Authorization` headers for subsequent requests.
   - Implements `getSession` by hitting `GET /api/auth/me`.
   - Implements `signout` by calling `POST /api/auth/logout`.

2. **Token storage**:
   - Preferred: store the bearer token in an HTTP-only secure cookie set from the server (Auth.js can set cookies via callbacks).
   - Alternative: use secure storage (localStorage with appropriate XSS protection is your responsibility).

3. **Registration flow**:
   - Call `POST /api/auth/register`.
   - If `email_verified_at` is `null` and you enforce verification, redirect the user to a "confirm your email" view.
   - Handle `403` response when registration is disabled; display a blocking message.

4. **Email verification**:
   - Provide UI to request a new email via `POST /api/auth/email/verification-notification`.
   - The SPA route that receives the verification redirect should parse success/error states based on the backend redirect URL (set `profile.email_verified_redirect` to a SPA route like `/auth/verify`).

5. **Forgot/Reset password**:
   - `forgot`: send email address; show success message regardless of outcome.
   - `reset`: build a page that accepts `token` and `email` from the email link (Laravel's default notification uses `/reset-password/{token}` pattern). Post form to `/api/auth/password/reset`.

6. **Error handling**:
   - Validation errors: expect `422` with `errors` object; map to form feedback.
   - `login`: treat `403` with `code=email_unverified` as a special state prompting verification.
   - `logout`: ignore failures and clear local session.

7. **Social login**:
   - Implement a link/button that calls `/api/auth/oauth/{provider}/url`, then redirects the browser.
   - After Socialite callback, either handle JSON response (if SSR) or configure `profile.oauth_front_redirect` to send users back to the SPA with a token query param (exchange it for a proper session on the frontend).

8. **Profile API consumption**:
   - Additional authenticated data lives under `/api/profile` (profile guard). Ensure guards are aligned (see `IMPROVEMENTS.md`) before relying on these endpoints from the SPA.

---

## Operational Considerations

- **Email delivery**: Laravel must be configured with a mail driver. Password reset and verification rely on queued/standard notifications.
- **Sanctum personal access tokens table**: run `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"` if not already done; ensure `personal_access_tokens` migration has been executed.
- **CORS**: expose `/api/auth/*` endpoints to your SPA domain; include `Authorization` header in allowed headers.
- **HTTPS**: tokens should never travel over insecure transport. Enforce HTTPS in production.
- **Throttling**: apply additional rate limiting if necessary using Laravel's `Route::middleware('throttle:X,Y')`.

---

## Backend Extensibility Hooks

- **Toggles**: `Settings::get('profile.users.allow_registration')` and `Settings::get('profile.users.require_email_verification')` influence registration/login outcomes. Surface these states in your frontend copy.
- **Events**: Listen to `Registered`, `Verified`, or package-specific events (e.g., `ProfileCreating`) if you need to push extra data to the SPA after login.
- **Guards**: If you prefer cookie-based Sanctum SPA authentication, replace the personal token logic with cookie issuance inside `AuthController` (out of scope for current implementation).

---

## Known Issues Affecting Frontend

Refer to `IMPROVEMENTS.md` for backend gaps. The following items specifically impact frontend integration:

- `/api/profile/update` currently authenticates with `auth:sanctum` but fetches the user via `Auth::guard('profile')`, which will null out unless a profile guard is active. Adjust to use `$request->user()` for consistency.
- Email verification redirect is configurable but not documented in the default README; set `profile.email_verified_redirect` to a SPA route to avoid raw JSON responses for end-users.
- Socialite providers must be enabled in `config/services.php`; missing configuration yields runtime exceptions. Validate environment variables before exposing buttons in the UI.

Address these issues before building the production frontend workflow.
