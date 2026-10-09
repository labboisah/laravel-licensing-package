# KernelBridge Laravel Licensing Client

This package is the shared Laravel licensing boundary for product applications such as AlignEx, EduNexa, MediFlow, and future client apps. It keeps a signed, encrypted local license snapshot on the product server and treats KernelBridge as the source of truth for license status, activation limits, entitlements, and feature policy.

## Why this package exists

The package is designed to prevent silent license drift and environment-based fallback.

Key rules:

- Local configuration is treated as bootstrap input, not as a trusted runtime authority.
- A persisted deployment profile stored in the product database records the expected deployment configuration.
- Sensitive system settings are fingerprinted and compared on activation and verification.
- A mismatch is fail-closed: the package blocks access until the operator intentionally re-provisions the deployment profile.

This detects configuration drift and prevents silent fallback. It is not hardware attestation or protection against a hostile administrator who controls the application files and database; the product server remains customer-controlled.

## Installation from GitHub

This package is hosted in the public GitHub repository `labboisah/laravel-licensing-package`. It is not published to Packagist, so each client app registers the VCS repository before requiring the package.

### Install from GitHub

```bash
composer config repositories.kernelbridge vcs https://github.com/labboisah/laravel-licensing-package.git
composer require kernelbridge/licensing-client-laravel:^1.2.1
```

The repository root contains the package `composer.json`, so client apps do not need a sibling KernelBridge checkout to install the package. Commit both `composer.json` and `composer.lock` to the client app so all environments install the same tag and version.

### Local sibling project

If the product app and the KernelBridge monorepo live next to each other:

```text
C:\laragon\www\kernelbridge
C:\laragon\www\staycontrol
```

Run this from the client app directory:

```bash
composer config repositories.kernelbridge path ../kernelbridge/packages/kernelbridge/licensing-client-laravel
composer require kernelbridge/licensing-client-laravel:^1.0
```

If the application is not a sibling folder, use an absolute path such as:

```bash
composer config repositories.kernelbridge path "C:/laragon/www/kernelbridge/packages/kernelbridge/licensing-client-laravel"
```

Then publish the package assets and run migrations:

```bash
php artisan vendor:publish --tag=kernelbridge-licensing-config
php artisan vendor:publish --tag=kernelbridge-licensing-migrations
php artisan vendor:publish --tag=kernelbridge-licensing-assets
php artisan migrate
```

## Default behavior

After installation the package auto-registers:

- the `/license` activation UI
- activation, verification, and deactivation routes
- middleware aliases for activation and feature/limit checks
- the scheduled verification job
- a signed local state store for license status and entitlements

Visit `/license` in the client app to activate or verify the installation.

## Environment values

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
KERNELBRIDGE_STRICT_DEPLOYMENT_PROFILE=true
```

Important notes:

- Keep product tokens separate for each environment and product.
- Never commit tokens or signing keys.
- Rotate API credentials when a deployment changes, a product is retired, or a token is suspected to be exposed.
- `KERNELBRIDGE_STRICT_DEPLOYMENT_PROFILE` should remain enabled in production.

## Route protection

Two supported patterns exist:

### Protect the whole app

```dotenv
KERNELBRIDGE_PROTECT_WEB_ROUTES=true
KERNELBRIDGE_PROTECT_EXCEPT=login,register,password/*
```

This redirects unauthenticated or unlicensed browser requests to `/license` until activation succeeds.

### Protect only selected routes

```php
Route::middleware('kernelbridge.subscription.active')->group(function () {
    Route::get('/dashboard', DashboardController::class);
    Route::get('/reports', ReportsController::class);
});
```

This is useful when the client app needs custom route-level enforcement instead of a global redirect.

## Deployment profile and fail-closed validation

The package records a deployment profile that includes the product code, deployment mode, API URL, hashes of the machine token and signing key, and a SHA-256 fingerprint of the active licensing configuration. License entitlements remain separately signed in the local license cache.

During activation and verification, the package checks:

- the current config matches the persisted deployment profile
- required values are present and not empty
- the local signed state is valid
- the runtime config still matches the persisted deployment fingerprint

If the config changes after activation, the runtime fails closed with `configuration_tampered` and asks for re-activation. The DB profile is a consistency baseline, not a cryptographic trust anchor against an operator who can modify both the application and its database.

## Intentional re-provisioning

When a deployment genuinely changes, the operator can intentionally rotate the trusted deployment record.

### Artisan command

```bash
php artisan kernelbridge:reprovision-profile
```

The command asks for confirmation before replacing an existing profile, with the prompt defaulting to no. Add `--force` only for a controlled, non-interactive deployment operation. The command first validates the required client settings and then persists the current deployment fingerprint. It is CLI-only by default so profile replacement is not exposed to unauthenticated web requests.

Client apps that require a web-based control must implement their own authenticated, administrator-authorized action and must not expose the package profile update directly to public users.

## Activation and verification

```php
use KernelBridge\LicensingClient\Services\LicenseActivationService;
use KernelBridge\LicensingClient\Services\LicenseVerificationService;

$state = app(LicenseActivationService::class)->activate($licenseKey, 'AlignEx production');
$state = app(LicenseVerificationService::class)->verify();
$features = app(LicenseVerificationService::class)->getEntitlements();
app(LicenseVerificationService::class)->deactivate();
```

The package persists:

- encrypted License key
- activation and verification metadata
- signed entitlement payload
- expiry timestamps and offline grace dates
- a hash of the effective configuration and deployment fingerprint

## License status and tamper handling

The package fails closed when any of these are true:

- no license exists locally
- the license was revoked, rejected, deactivated, expired, or exceeded offline grace
- the signed entitlement payload was changed or cannot be decrypted
- sensitive licensing configuration changed after activation
- the deployment profile does not match the active runtime config

Protected web routes redirect to `/license`, and JSON/API requests return `402 Payment Required` until activation or re-provisioning resolves the problem.

## Local state and offline operation

Normal product requests read the local signed snapshot and do not require a live KernelBridge call. The scheduled verification job refreshes the local state on a fixed interval.

If a temporary outage occurs, the last valid signed snapshot remains usable only until the earlier of:

- expiry
- offline grace expiry
- explicit rejection or deactivation
- signature failure or entitlement tampering

This is intentional: the package remains usable during short outages, but it does not silently fall back to an unsafe local policy.

## Release tags and updates

Tag package releases in GitHub using the package version, for example `1.2.0`.

Client apps can then update via:

```bash
composer update kernelbridge/licensing-client-laravel
php artisan optimize:clear
```

If a custom view exists at `resources/views/vendor/kernelbridge-licensing/activation.blade.php`, merge any desired branding changes manually. Published views override the package view and are not replaced automatically.

## Operational guidance

- Treat KernelBridge as the licensing source of truth.
- Back up the product database but do not rely on local config as a trust anchor.
- Protect `APP_KEY` and `KERNELBRIDGE_CACHE_SIGNING_KEY`.
- Monitor failing verification jobs and API connectivity.
- Do not expose the local entitlement payload or signed state to public controllers.

## Package summary

The package is intentionally strict by default:

- it validates the active deployment
- it signs and verifies local state
- it blocks silent fallback
- it requires an explicit deployment rotation for real runtime changes

That makes it safe for production products while still supporting a clear, auditable re-provision path when the deployment must intentionally change.
- Check entitlements again inside sensitive service methods; route middleware is a convenience boundary, not the only authorization control.

Run the package coverage from this repository with:

```bash
php artisan test --filter=LicensingClientTest
```












## Machine binding

The client now sends machine_fingerprint with activation, verification and deactivation. It derives a SHA-256 value from the OS machine identity (Windows MachineGuid, Linux machine-id or macOS platform UUID). Activation also sends device_info containing hostname, OS, architecture, client version, and platform URL. If an OS identity cannot be read, licensing fails closed; there is no copyable random-ID fallback. The encrypted, signed version-2 cache includes this fingerprint and cannot be used unchanged on another machine. Existing version-1 caches require online refresh.

The platform persists a keyed hash rather than the raw machine identifier. Device-bound MediFlow purchases retain their installation binding after deactivation. Other integrations keep their configured activation capacity, with the machine check enforced for activations that supplied a fingerprint. Browser-only clients cannot reliably supply OS identity; use the server-side Laravel client on the actual licensed host. Containers and cloned operating systems must have independently provisioned machine identities; this mechanism is not remote hardware attestation.
