<?php

namespace KernelBridge\LicensingClient\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use KernelBridge\LicensingClient\Models\LicenseState;
use Throwable;

final class LicenseCacheService
{
    public function state(): LicenseState
    {
        $profile = app(DeploymentProfile::class);
        $productCode = $profile->productCode();
        if ($productCode === '') {
            throw new \LogicException('KERNELBRIDGE_PRODUCT_CODE is required.');
        }

        return LicenseState::query()->firstOrCreate(
            ['product_code' => $productCode],
            ['installation_identifier' => (string) Str::uuid(), 'status' => 'unlicensed'],
        );
    }

    public function storeVerified(string $licenseKey, array $license, array $subscription, array $entitlements): LicenseState
    {
        return DB::transaction(function () use ($licenseKey, $license, $subscription, $entitlements): LicenseState {
            $state = LicenseState::query()->lockForUpdate()->findOrFail($this->state()->id);
            $verifiedAt = now();
            $expiresAt = $this->expiry($license, $subscription);
            $grace = $verifiedAt->copy()->addHours(max(1, (int) config('kernelbridge-licensing.offline_grace_hours', 72)));
            if ($expiresAt && $grace->gt($expiresAt)) {
                $grace = $expiresAt->copy();
            }
            $payload = [
                'version' => 2,
                'revision' => $this->revisionFor($license, $subscription, $entitlements, $verifiedAt),
                'effective_at' => $verifiedAt->toISOString(),
                'machine_fingerprint' => app(DeviceIdentity::class)->fingerprint(),
                'product_code' => $state->product_code,
                'license_identifier' => $license['license_uuid'],
                'subscription_identifier' => $license['subscription_uuid'],
                'subscription' => $subscription,
                'configuration' => $license['configuration'] ?? $subscription['configuration'] ?? [],
                'features' => collect($entitlements['entitlements'] ?? [])->mapWithKeys(fn (array $item): array => [(string) $item['key'] => ['enabled' => (bool) ($item['enabled'] ?? false), 'limit' => $item['limit'] ?? null]])->all(),
                'verified_at' => $verifiedAt->toISOString(),
                'offline_grace_expires_at' => $grace->toISOString(),
            ];
            $state->update([
                'license_identifier' => $license['license_uuid'],
                'subscription_identifier' => $license['subscription_uuid'],
                'activation_identifier' => $license['activation_uuid'] ?? $state->activation_identifier,
                'encrypted_license_key' => $licenseKey,
                'status' => $subscription['status'] ?? $license['status'] ?? 'inactive',
                'last_successful_verification_at' => $verifiedAt,
                'expires_at' => $expiresAt,
                'offline_grace_expires_at' => $grace,
                'entitlement_payload' => $payload,
                'entitlement_signature' => $this->sign($payload),
                'config_fingerprint' => $this->configFingerprint(),
                'config_fingerprinted_at' => $verifiedAt,
                'last_error_code' => null,
            ]);

            return $state->refresh();
        });
    }

    public function markRejected(string $code): void
    {
        $this->state()->update(['status' => 'invalid', 'last_error_code' => $code]);
    }

    public function markDeactivated(): void
    {
        $this->state()->update(['status' => 'deactivated', 'offline_grace_expires_at' => now(), 'last_error_code' => null]);
    }

    public function reset(): void
    {
        $state = $this->state();
        $state->delete();
    }

    public function hasUsableLicense(): bool
    {
        try {
            $state = $this->state();

            return in_array($state->status, ['active', 'trial', 'grace'], true)
                && $state->last_successful_verification_at !== null
                && ($state->expires_at === null || $state->expires_at->isFuture())
                && $state->offline_grace_expires_at?->isFuture()
                && $this->configFingerprintIsValid($state)
                && $this->signatureIsValid($state)
                && $this->machineMatches($state);
        } catch (Throwable) {
            return false;
        }
    }

    public function hasFeature(string $feature): bool
    {
        return $this->hasUsableLicense() && (bool) ($this->state()->entitlement_payload['features'][$feature]['enabled'] ?? false);
    }

    public function limit(string $feature): int|float|string|null
    {
        if (! $this->hasFeature($feature)) {
            return 0;
        }

        return $this->state()->entitlement_payload['features'][$feature]['limit'] ?? null;
    }

    public function status(): array
    {
        $state = $this->state();
        $payload = is_array($state->entitlement_payload) ? $state->entitlement_payload : [];
        $active = $this->hasUsableLicense();
        $reason = $active ? 'active' : $this->activationRequirementReason();

        return [
            'active' => $active,
            'status' => $state->status ?? 'unlicensed',
            'reason' => $reason,
            'message' => $active ? 'License is active and verified.' : $this->activationRequirementMessage(),
            'product_code' => $state->product_code,
            'package' => $payload['primary_package'] ?? data_get($payload, 'subscription.primary_package') ?? null,
            'packages' => $payload['selected_packages'] ?? data_get($payload, 'subscription.selected_packages') ?? [],
            'features' => $payload['features'] ?? [],
            'expires_at' => $state->expires_at?->toIso8601String(),
            'last_verification_at' => $state->last_successful_verification_at?->toIso8601String(),
            'offline_grace_expires_at' => $state->offline_grace_expires_at?->toIso8601String(),
            'effective_at' => $payload['effective_at'] ?? null,
            'revision' => $payload['revision'] ?? null,
            'renewal_notice' => $this->renewalNotice(),
            'last_error_code' => $state->last_error_code,
        ];
    }

    public function renewalNoticeLevel(): ?string
    {
        try {
            if (! (bool) config('kernelbridge-licensing.renewal_notice.enabled', true) || ! $this->hasUsableLicense()) {
                return null;
            }

            $state = $this->state();
            if (! $state->expires_at?->isFuture()) {
                return null;
            }

            foreach ([1 => '1_month', 2 => '2_months', 3 => '3_months'] as $months => $level) {
                if (now()->copy()->addMonthsNoOverflow($months)->gte($state->expires_at)) {
                    return $level;
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    public function renewalNoticeMessage(): ?string
    {
        $level = $this->renewalNoticeLevel();
        if ($level === null) {
            return null;
        }

        $state = $this->state();
        $months = (int) str_replace('_months', '', str_replace('_month', '', $level));
        $monthText = $months === 1 ? '1 month' : $months.' months';

        return sprintf('Your KernelBridge license expires in about %s on %s. Renew to avoid service interruption.', $monthText, $state->expires_at?->toFormattedDateString());
    }

    /** @return array{level: string, message: string, expires_at: string}|null */
    public function renewalNotice(): ?array
    {
        $level = $this->renewalNoticeLevel();
        $message = $this->renewalNoticeMessage();
        $expiresAt = $this->state()->expires_at;

        if ($level === null || $message === null || $expiresAt === null) {
            return null;
        }

        return ['level' => $level, 'message' => $message, 'expires_at' => $expiresAt->toDateString()];
    }

    public function signatureIsValid(?LicenseState $state = null): bool
    {
        try {
            $state ??= $this->state();
            if (! is_array($state->entitlement_payload) || ! $state->entitlement_signature) {
                return false;
            }

            return hash_equals($state->entitlement_signature, $this->sign($state->entitlement_payload));
        } catch (Throwable) {
            return false;
        }
    }

    public function configFingerprintIsValid(?LicenseState $state = null): bool
    {
        try {
            $state ??= $this->state();
            if (! $state->encrypted_license_key || ! $state->config_fingerprint) {
                return ! $state->encrypted_license_key;
            }

            return hash_equals($state->config_fingerprint, $this->configFingerprint());
        } catch (Throwable) {
            return false;
        }
    }

    public function activationRequirementReason(): string
    {
        try {
            $state = $this->state();

            if (! $state->encrypted_license_key) {
                return 'license_not_activated';
            }
            if (! $this->configFingerprintIsValid($state)) {
                return 'configuration_tampered';
            }
            if ($state->entitlement_payload && ! $this->machineMatches($state)) {
                return 'device_mismatch';
            }
            if (! $this->signatureIsValid($state)) {
                return 'local_license_tampered';
            }
            if ($state->status === 'deactivated') {
                return 'license_deactivated';
            }
            if ($state->status === 'invalid') {
                return $state->last_error_code ?: 'license_invalid';
            }
            if ($state->expires_at?->isPast()) {
                return 'license_expired';
            }
            if (! $state->offline_grace_expires_at?->isFuture()) {
                return 'offline_grace_expired';
            }
            if (! in_array($state->status, ['active', 'trial', 'grace'], true)) {
                return 'license_inactive';
            }

            return 'license_required';
        } catch (Throwable $exception) {
            return $exception instanceof \LogicException ? 'configuration_incomplete' : 'license_required';
        }
    }

    public function activationRequirementMessage(): string
    {
        return match ($this->activationRequirementReason()) {
            'activation_limit_reached' => 'This license has reached its device activation limit. Deactivate another installation in KernelBridge before trying again.',
            'device_mismatch' => 'This cached licence does not belong to this computer. Connect to KernelBridge and activate this installation.',
            'configuration_tampered' => 'KernelBridge licensing configuration changed. Re-activation is required.',
            'configuration_incomplete' => 'KernelBridge licensing configuration is incomplete.',
            'license_deactivated' => 'This installation has been deactivated. Re-activation is required.',
            'license_expired' => 'This license has expired. Renew or activate a valid license.',
            'license_not_activated' => 'Activate this application before continuing.',
            'license_revoked' => 'This license was revoked by KernelBridge. Activate a valid license.',
            'local_license_tampered' => 'The local license cache was changed or cannot be trusted. Re-activation is required.',
            'offline_grace_expired' => 'Offline grace has expired. Reconnect to KernelBridge and verify the license.',
            default => 'An active KernelBridge subscription is required.',
        };
    }

    public function machineMatches(?LicenseState $state = null): bool
    {
        try {
            $state ??= $this->state();
            $expected = $state->entitlement_payload['machine_fingerprint'] ?? null;

            return is_string($expected) && hash_equals($expected, app(DeviceIdentity::class)->fingerprint());
        } catch (Throwable) {
            return false;
        }
    }

    private function configFingerprint(): string
    {
        $profile = app(DeploymentProfile::class);
        $payload = [
            'mode' => $profile->mode(),
            'deployment_fingerprint' => $profile->fingerprint(),
            'api_url' => $profile->apiUrl(),
            'product_code' => $profile->productCode(),
            'api_token_hash' => hash('sha256', $profile->apiToken()),
            'app_key_hash' => hash('sha256', (string) config('app.key')),
            'signature_key_hash' => hash('sha256', $profile->signatureKey()),
        ];

        return $this->sign($payload);
    }

    private function expiry(array $license, array $subscription): ?Carbon
    {
        $value = $license['expires_at'] ?? $subscription['grace_ends_at'] ?? $subscription['ends_at'] ?? null;
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value);
    }

    private function revisionFor(array $license, array $subscription, array $entitlements, Carbon $verifiedAt): string
    {
        $seed = [
            'license_uuid' => $license['license_uuid'] ?? null,
            'subscription_uuid' => $license['subscription_uuid'] ?? null,
            'status' => $subscription['status'] ?? $license['status'] ?? 'inactive',
            'expires_at' => $this->expiry($license, $subscription)?->toISOString(),
            'verified_at' => $verifiedAt->toISOString(),
            'configuration' => $license['configuration'] ?? $subscription['configuration'] ?? [],
            'entitlements' => collect($entitlements['entitlements'] ?? [])->map(fn (array $item): array => [
                'key' => (string) ($item['key'] ?? ''),
                'enabled' => (bool) ($item['enabled'] ?? false),
                'limit' => $item['limit'] ?? null,
            ])->values()->all(),
        ];

        return hash('sha256', json_encode($this->canonical($seed), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function sign(array $payload): string
    {
        $key = (string) config('kernelbridge-licensing.signature_key');
        if ($key === '') {
            throw new \LogicException('KERNELBRIDGE_CACHE_SIGNING_KEY or APP_KEY is required.');
        }

        return hash_hmac('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), $key);
    }

    private function canonical(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonical($item);
            }
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
