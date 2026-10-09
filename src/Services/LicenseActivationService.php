<?php

namespace KernelBridge\LicensingClient\Services;

use KernelBridge\LicensingClient\KernelBridgeLicenseClient;
use KernelBridge\LicensingClient\Models\LicenseState;

final readonly class LicenseActivationService
{
    public function __construct(private KernelBridgeLicenseClient $client, private LicenseCacheService $cache) {}

    public function activate(string $licenseKey, ?string $deviceName = null, ?string $platformUrl = null): LicenseState
    {
        $installation = $this->cache->state()->installation_identifier;
        $license = $this->client->activate($licenseKey, $installation, $deviceName ?? config('app.name'), $platformUrl);
        $subscription = $license['subscription'] ?? $this->client->getSubscription($license['subscription_uuid']);
        $entitlements = $license['entitlements'] ?? $this->client->getEntitlements($license['subscription_uuid']);

        return $this->cache->storeVerified($licenseKey, $license, $subscription, $entitlements);
    }
}
