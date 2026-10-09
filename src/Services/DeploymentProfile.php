<?php

namespace KernelBridge\LicensingClient\Services;

use KernelBridge\LicensingClient\Models\DeploymentProfile as DeploymentProfileRecord;

final class DeploymentProfile
{
    public function mode(): string
    {
        $value = strtolower(trim((string) config('kernelbridge-licensing.deployment.mode', env('APP_MODE', 'client'))));

        return in_array($value, ['client', 'central', 'standalone'], true) ? $value : 'client';
    }

    public function isClientMode(): bool
    {
        return in_array($this->mode(), ['client', 'central'], true);
    }

    public function productCode(): string
    {
        return strtoupper((string) config('kernelbridge-licensing.product_code'));
    }

    public function apiUrl(): string
    {
        return trim((string) config('kernelbridge-licensing.api_url', ''));
    }

    public function apiToken(): string
    {
        return (string) config('kernelbridge-licensing.api_token', '');
    }

    public function signatureKey(): string
    {
        return (string) config('kernelbridge-licensing.signature_key', '');
    }

    public function fingerprint(): string
    {
        $payload = [
            'mode' => $this->mode(),
            'api_url' => $this->apiUrl(),
            'product_code' => $this->productCode(),
            'api_token_hash' => hash('sha256', $this->apiToken()),
            'signature_key_hash' => hash('sha256', $this->signatureKey()),
        ];

        return hash('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public function persist(): DeploymentProfileRecord
    {
        $payload = [
            'product_code' => $this->productCode(),
            'mode' => $this->mode(),
            'api_url' => $this->apiUrl(),
            'api_token_hash' => hash('sha256', $this->apiToken()),
            'signature_key_hash' => hash('sha256', $this->signatureKey()),
            'fingerprint' => $this->fingerprint(),
            'metadata' => [
                'api_url' => $this->apiUrl(),
                'mode' => $this->mode(),
                'product_code' => $this->productCode(),
                'strict_profile' => (bool) config('kernelbridge-licensing.deployment.strict_profile', true),
            ],
        ];

        return DeploymentProfileRecord::query()->updateOrCreate(
            ['product_code' => $this->productCode()],
            $payload,
        );
    }

    public function assertReady(): void
    {
        if ($this->mode() === 'standalone') {
            return;
        }

        $missing = array_filter([
            'api_url' => $this->apiUrl(),
            'api_token' => $this->apiToken(),
            'product_code' => $this->productCode(),
            'signature_key' => $this->signatureKey(),
        ], static fn ($value): bool => $value === '' || $value === null);

        if ($missing !== []) {
            throw new \LogicException('KernelBridge licensing deployment profile is incomplete; configure a valid client deployment profile before activation.');
        }

        $profile = DeploymentProfileRecord::query()->where('product_code', $this->productCode())->latest()->first();
        if (! $profile) {
            $this->persist();
            return;
        }

        if (! (bool) config('kernelbridge-licensing.deployment.strict_profile', true)) {
            return;
        }

        if ($profile->fingerprint !== $this->fingerprint()) {
            throw new \LogicException('KernelBridge licensing deployment profile mismatch. The deployment record does not match the active configuration. Re-provision or restore the signed client profile.');
        }
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
