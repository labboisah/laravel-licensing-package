# KernelBridge Laravel Licensing Client

This package is the reusable licensing boundary for AlignEx, EduNexa, MediFlow, MarketLens, StayControl, and future Laravel products. Product applications retain only a signed, encrypted local license snapshot; KernelBridge remains the source of truth.

## Installation

This package is hosted in the public GitHub repository `labboisah/laravel-licensing-package`. It is not listed on Packagist, so configure the VCS repository in each client application before requiring it.

### Install from GitHub

Add the repository and require the stable package release from the client application's directory:

```bash
composer config repositories.kernelbridge vcs https://github.com/labboisah/laravel-licensing-package.git
composer require kernelbridge/licensing-client-laravel:^1.2
```

The repository root contains this package's `composer.json`; no sibling KernelBridge checkout is needed. Commit both `composer.json` and `composer.lock` in the client app so other machines install the same release with `composer install`.

### Local sibling application

If the applications use this layout:

```text
C:\laragon\www\kernelbridge
C:\laragon\www\staycontrol
```

run these commands from the client application, for example `C:\laragon\www\staycontrol`:

```bash
composer config repositories.kernelbridge path ../kernelbridge/packages/kernelbridge/licensing-client-laravel
composer require kernelbridge/licensing-client-laravel:^1.0
```

Composer records the path repository in the client application's `composer.json`. The equivalent manual configuration is:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../kernelbridge/packages/kernelbridge/licensing-client-laravel",
            "options": { "symlink": true }
        }
    ]
}
```

The repository URL is resolved relative to the client application's `composer.json`, not relative to the KernelBridge package. If the package is copied into the client project at `packages/kernelbridge/licensing-client-laravel`, use that path instead.

Then finish the Laravel installation:

```bash
php artisan vendor:publish --tag=kernelbridge-licensing-config
php artisan vendor:publish --tag=kernelbridge-licensing-migrations
php artisan vendor:publish --tag=kernelbridge-licensing-assets
php artisan migrate
```

The package auto-registers a minimal activation controller, routes, middleware aliases, Blade UI, and opt-in global request protection. Visit `/license` in the product app to activate, verify, or deactivate the local installation. Add KERNELBRIDGE_PROTECT_WEB_ROUTES=true to protect client app pages until activation succeeds.

### Composer release tags

Tag releases in this repository using the package version, for example `1.2.0`. Client applications can then require compatible stable updates with:

```bash
composer update kernelbridge/licensing-client-laravel
```

Do not use `minimum-stability: dev` as a workaround. Stable release tags are available from GitHub VCS.

### Updating an existing client

After publishing a new package version, update the client application from its project directory:

```bash
composer update kernelbridge/licensing-client-laravel
php artisan optimize:clear
```

If the client has a customized or previously published view at `resources/views/vendor/kernelbridge-licensing/activation.blade.php`, merge the updated activation form fields into that view. Published views take precedence over the package view, so updating Composer alone will not replace them. Also check that `KERNELBRIDGE_LICENSE_VIEW` does not point to another custom view.

Laravel package discovery registers the provider, migrations, default `/license` routes, the built-in activation controller, middleware aliases, publishable Blade UI, and scheduled verification job.

## Environment

```dotenv
KERNELBRIDGE_API_URL=https://kernelbridge.com/api/v1
KERNELBRIDGE_PRODUCT_CODE=ALIGNEX
KERNELBRIDGE_API_TOKEN=replace-with-the-product-machine-token
KERNELBRIDGE_API_CONNECT_TIMEOUT=3
KERNELBRIDGE_API_TIMEOUT=10
KERNELBRIDGE_VERIFICATION_INTERVAL=15
KERNELBRIDGE_OFFLINE_GRACE_HOURS=72
KERNELBRIDGE_CACHE_SIGNING_KEY=replace-with-an-independent-long-random-secret
KERNELBRIDGE_PROTECT_WEB_ROUTES=true
KERNELBRIDGE_PROTECT_EXCEPT=login,register,password/*
KERNELBRIDGE_RENEWAL_NOTICE_ENABLED=true
```

Give every product and environment independent, least-privilege API credentials. Never commit tokens or the signing key. Rotate and revoke product tokens from KernelBridge when required.

## Minimal built-in setup

After publishing the config and running migrations, the default package routes are available at `/license`:

```text
GET /license          Show activation/status UI
POST /license         Activate a license key
POST /license/verify  Force verification
DELETE /license       Deactivate this installation
```

Route and redirect settings can be changed in `config/kernelbridge-licensing.php`:

```php
'routes' => [
    'enabled' => true,
    'prefix' => 'license',
    'name' => 'kernelbridge.license.',
    'middleware' => ['web'],
],

'protection' => [
    'web' => true,
    'except' => ['login', 'register', 'password/*'],
],

'redirects' => [
    'activation_route' => null,
    'after_activation' => '/',
],
```

The built-in activation view supports `KERNELBRIDGE_LICENSE_BRAND_NAME`, `KERNELBRIDGE_LICENSE_BRAND_SUBTITLE`, `KERNELBRIDGE_LICENSE_HEADING`, `KERNELBRIDGE_LICENSE_DESCRIPTION`, and `KERNELBRIDGE_LICENSE_SHOW_LOGO` for product-specific branding. `KERNELBRIDGE_LICENSE_LOGO_PATH`, `KERNELBRIDGE_LICENSE_LOGO_ALT`, and `KERNELBRIDGE_LICENSE_CSS_PATH` can point to app-provided assets.

To customize the default Blade screen or own the route definitions, publish them:

```bash
php artisan vendor:publish --tag=kernelbridge-licensing-views
php artisan vendor:publish --tag=kernelbridge-licensing-routes
php artisan vendor:publish --tag=kernelbridge-licensing-assets
```

Vue or React applications can keep the package routes as form endpoints, or replace the Blade view with their own page and call `LicenseActivationService`, `LicenseVerificationService`, and `LicenseCacheService` from app-owned controllers.

## Activation and verification

```php
use KernelBridge\LicensingClient\Services\LicenseActivationService;
use KernelBridge\LicensingClient\Services\LicenseVerificationService;

$state = app(LicenseActivationService::class)->activate($licenseKey, 'AlignEx production');
$state = app(LicenseVerificationService::class)->verify();
$features = app(LicenseVerificationService::class)->getEntitlements();
app(LicenseVerificationService::class)->deactivate();
```

`KernelBridgeLicenseClient` exposes `activate()`, `verify()`, `deactivate()`, and `getEntitlements()` for lower-level integrations. Prefer the services because they atomically maintain local state.

## Re-activation and tamper handling

The package fails closed and requires re-activation when any of these checks fail:

- No local license has been activated.
- The local license was deactivated, rejected, revoked, expired, or exceeded offline grace.
- The signed entitlement payload was changed or cannot be decrypted.
- Sensitive local configuration changed after activation.

During activation and verification, the package stores a signed configuration fingerprint for `KERNELBRIDGE_API_URL`, `KERNELBRIDGE_PRODUCT_CODE`, `KERNELBRIDGE_API_TOKEN`, `APP_KEY`, and `KERNELBRIDGE_CACHE_SIGNING_KEY`. If those values are changed later, protected web routes redirect to `/license` and API requests fail with `402` until the installation is re-activated.

Device activation limits are enforced by the central KernelBridge API. The product app sends its stable `installation_identifier`, product code, computer name, platform URL, machine fingerprint, and license key during activation. The built-in activation form defaults the computer name from the host and the platform URL from `APP_URL`. The platform URL and computer name are recorded as activation details; the machine fingerprint is what binds the activation to the physical or virtual host. If the license has reached its allowed device count, KernelBridge rejects the request and the package shows an activation-limit message.

## Route protection

There are two supported ways to protect the Laravel app that installs this package.

### Protect the whole client app

Set `KERNELBRIDGE_PROTECT_WEB_ROUTES=true` to make normal client app pages redirect to `/license` until the installation is activated or re-activated. KernelBridge's customer service page generates this value for each registered service. The package automatically allows its own activation routes to pass through.

Use `KERNELBRIDGE_PROTECT_EXCEPT` for public pages that must remain reachable before activation:

```dotenv
KERNELBRIDGE_PROTECT_WEB_ROUTES=true
KERNELBRIDGE_PROTECT_EXCEPT=login,register,password/*
KERNELBRIDGE_RENEWAL_NOTICE_ENABLED=true
```

### Protect selected routes manually

Set `KERNELBRIDGE_PROTECT_WEB_ROUTES=false` or leave it unset if the client app should choose protected routes itself. Then add `kernelbridge.subscription.active` directly to the routes or route groups that require an activated license:

```php
Route::middleware('kernelbridge.subscription.active')->group(function () {
    Route::get('/dashboard', DashboardController::class);
    Route::get('/reports', ReportsController::class);
});
```

### Using both together

It is safe but redundant to enable `KERNELBRIDGE_PROTECT_WEB_ROUTES=true` and also add `kernelbridge.subscription.active` to individual routes. The request may be checked twice, but the result is the same: unactivated browser requests redirect to `/license`, JSON/API requests return `402`, and activated requests continue normally.

Use manual middleware with global protection mainly for feature or limit checks:

```php
Route::middleware('kernelbridge.feature:finance')->group(function () {
    Route::resource('invoices', InvoiceController::class);
});

Route::post('/users', StoreUserController::class)
    ->middleware('kernelbridge.limit:max_users,25');
```

Use the limit middleware only when a static required quantity is appropriate. For live usage counts, resolve `LicenseCacheService`, read `limit('max_users')`, and compare it with an authoritative backend count inside the business transaction.


## Renewal notices

The package can warn the client app when an active license is due within about 3, 2, or 1 month. Access is not blocked by these warnings; they are for banners, dashboard alerts, emails, or support prompts inside the product app.

```php
$notice = app(\KernelBridge\LicensingClient\Services\LicenseCacheService::class)->renewalNotice();

if ($notice) {
    // ['level' => '2_months', 'message' => '...', 'expires_at' => '2026-12-31']
}
```

Protected Laravel responses also include these headers when a notice is active:

```text
X-KernelBridge-Renewal-Notice-Level: 1_month
X-KernelBridge-Renewal-Notice-Message: Your KernelBridge license expires...
X-KernelBridge-Renewal-Expires-At: 2026-12-31
```

Set `KERNELBRIDGE_RENEWAL_NOTICE_ENABLED=false` to disable these helpers.

## Local state and offline operation

The package stores one row per product containing public license, activation, subscription, and installation identifiers; encrypted license key and entitlement payload; verification/expiry timestamps; and an HMAC signature. It never stores central database IDs.

Normal requests read the local snapshot and do not call KernelBridge. The queued scheduled job refreshes the snapshot at `KERNELBRIDGE_VERIFICATION_INTERVAL`. Run both infrastructure processes:

```bash
php artisan queue:work --tries=3
php artisan schedule:work
```

If KernelBridge returns a temporary network/5xx failure, a correctly signed snapshot remains usable only until the earlier of license expiry and offline grace expiry. Explicit rejection, revocation, expiry, deactivation, a changed payload, or an invalid signature fails closed. Configuration errors are not treated as outages.

## Operational guidance

- Back up the local application database, but treat KernelBridge as the licensing source of truth.
- Protect `APP_KEY` and `KERNELBRIDGE_CACHE_SIGNING_KEY`; losing either can make the encrypted snapshot unreadable.
- Monitor failed `VerifyLicenseJob` jobs and API connectivity.
- Do not expose the local state model or entitlement payload through public controllers.
- Check entitlements again inside sensitive service methods; route middleware is a convenience boundary, not the only authorization control.

Run the package coverage from this repository with:

```bash
php artisan test --filter=LicensingClientTest
```












## Machine binding

The client now sends machine_fingerprint with activation, verification and deactivation. It derives a SHA-256 value from the OS machine identity (Windows MachineGuid, Linux machine-id or macOS platform UUID). Activation also sends device_info containing hostname, OS, architecture, client version, and platform URL. If an OS identity cannot be read, licensing fails closed; there is no copyable random-ID fallback. The encrypted, signed version-2 cache includes this fingerprint and cannot be used unchanged on another machine. Existing version-1 caches require online refresh.

The platform persists a keyed hash rather than the raw machine identifier. Device-bound MediFlow purchases retain their installation binding after deactivation. Other integrations keep their configured activation capacity, with the machine check enforced for activations that supplied a fingerprint. Browser-only clients cannot reliably supply OS identity; use the server-side Laravel client on the actual licensed host. Containers and cloned operating systems must have independently provisioned machine identities; this mechanism is not remote hardware attestation.
