<?php

namespace KernelBridge\LicensingClient\Services;

use KernelBridge\LicensingClient\Exceptions\KernelBridgeApiException;
use KernelBridge\LicensingClient\Exceptions\KernelBridgeUnavailableException;
use KernelBridge\LicensingClient\KernelBridgeLicenseClient;
use KernelBridge\LicensingClient\Models\LicenseState;

final readonly class LicenseVerificationService
{
    public function __construct(private KernelBridgeLicenseClient $client, private LicenseCacheService $cache) {}

    public function verify(bool $force = false): LicenseState
    {
        $state = $this->cache->state();
        if (! $force && $state->last_successful_verification_at?->gt(now()->subMinutes((int) config('kernelbridge-licensing.verification_interval_minutes', 15))) && $this->cache->hasUsableLicense()) {
            return $state;
        }
        if (! $state->encrypted_license_key || ! $state->subscription_identifier) {
            throw new KernelBridgeApiException('No local KernelBridge license has been activated.', 'license_not_activated');
        }

        try {
            $license = $this->client->verify($state->encrypted_license_key, $state->installation_identifier);
            $subscription = $license['subscription'] ?? $this->client->getSubscription($license['subscription_uuid']);
            $entitlements = $license['entitlements'] ?? $this->client->getEntitlements($license['subscription_uuid']);

            return $this->cache->storeVerified($state->encrypted_license_key, $license, $subscription, $entitlements);
        } catch (KernelBridgeUnavailableException $exception) {
            if ($this->cache->hasUsableLicense()) {
                return $state->refresh();
            }

            throw $exception;
        } catch (KernelBridgeApiException $exception) {
            $this->cache->markRejected($exception->errorCode);

            throw $exception;
        }
    }

    public function deactivate(): void
    {
        $state = $this->cache->state();
        if ($state->encrypted_license_key) {
            $this->client->deactivate($state->encrypted_license_key, $state->installation_identifier);
        }
        $this->cache->markDeactivated();
    }

    public function getEntitlements(bool $forceVerification = false): array
    {
        $state = $this->verify($forceVerification);

        return $state->entitlement_payload['features'] ?? [];
    }
}
