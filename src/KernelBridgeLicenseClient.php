<?php

namespace KernelBridge\LicensingClient;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use KernelBridge\LicensingClient\Exceptions\KernelBridgeApiException;
use KernelBridge\LicensingClient\Exceptions\KernelBridgeUnavailableException;
use KernelBridge\LicensingClient\Services\DeviceIdentity;

final class KernelBridgeLicenseClient
{
    public function activate(string $licenseKey, string $installationIdentifier, ?string $deviceName = null, ?string $platformUrl = null): array
    {
        $deviceInfo = app(DeviceIdentity::class)->information();
        if ($platformUrl !== null) {
            $deviceInfo['platform_url'] = $platformUrl;
        }

        return $this->request('post', '/licenses/activate', array_filter(['license_key' => $licenseKey, 'device_id' => $installationIdentifier, 'device_name' => $deviceName, 'machine_fingerprint' => app(DeviceIdentity::class)->fingerprint(), 'device_info' => $deviceInfo]));
    }

    public function verify(string $licenseKey, string $installationIdentifier): array
    {
        return $this->request('post', '/licenses/verify', ['license_key' => $licenseKey, 'device_id' => $installationIdentifier, 'machine_fingerprint' => app(DeviceIdentity::class)->fingerprint()]);
    }

    public function deactivate(string $licenseKey, string $installationIdentifier): array
    {
        return $this->request('post', '/licenses/deactivate', ['license_key' => $licenseKey, 'device_id' => $installationIdentifier, 'machine_fingerprint' => app(DeviceIdentity::class)->fingerprint()]);
    }

    public function getEntitlements(string $subscriptionIdentifier): array
    {
        return $this->request('get', '/entitlements', ['subscription_uuid' => $subscriptionIdentifier]);
    }

    public function getSubscription(string $subscriptionIdentifier): array
    {
        return $this->request('get', '/subscriptions/status', ['subscription_uuid' => $subscriptionIdentifier]);
    }

    private function request(string $method, string $path, array $data): array
    {
        try {
            $response = $method === 'get'
                ? $this->http()->get($path, $data)
                : $this->http()->send(strtoupper($method), $path, ['json' => $data]);
        } catch (ConnectionException $exception) {
            Log::warning('KernelBridge licensing connection failed', [
                'host' => parse_url((string) config('kernelbridge-licensing.api_url'), PHP_URL_HOST),
                'path' => $path,
                'exception' => $exception->getMessage(),
            ]);
            throw new KernelBridgeUnavailableException('KernelBridge licensing is temporarily unavailable.', previous: $exception);
        }

        return $this->data($response);
    }

    private function http(): PendingRequest
    {
        $url = rtrim((string) config('kernelbridge-licensing.api_url'), '/');
        $token = (string) config('kernelbridge-licensing.api_token');
        $code = (string) config('kernelbridge-licensing.product_code');
        if ($url === '' || $token === '' || $code === '') {
            throw new \LogicException('KernelBridge licensing configuration is incomplete.');
        }

        return Http::baseUrl($url)->withoutRedirecting()->withToken($token)->acceptJson()->asJson()
            ->withHeaders(['X-KernelBridge-Product' => strtoupper($code)])
            ->connectTimeout((int) config('kernelbridge-licensing.connect_timeout_seconds', 3))
            ->timeout((int) config('kernelbridge-licensing.timeout_seconds', 10));
    }

    private function data(Response $response): array
    {
        
        if ($response->serverError()) {
            Log::warning('KernelBridge licensing endpoint returned a server error', [
                'host' => parse_url((string) config('kernelbridge-licensing.api_url'), PHP_URL_HOST),
                'path' => $response->effectiveUri()->getPath(),
                'status' => $response->status(),
                'request_id' => $response->header('X-Request-ID'),
            ]);
            throw new KernelBridgeUnavailableException('KernelBridge licensing is temporarily unavailable.');
        }
        if (! $response->successful() || $response->json('success') !== true || ! is_array($response->json('data'))) {
            $message = (string) ($response->json('error.message') ?: 'KernelBridge rejected the licensing request.');
            if ($response->status() === 422 && is_array($response->json('error.details'))) {
                $details = collect($response->json('error.details'))->flatten()->filter(fn ($value) => is_string($value) && $value !== '')->unique()->values();
                if ($details->isNotEmpty()) {
                    $message = $details->implode(' ');
                }
            }
            throw new KernelBridgeApiException(
                $message,
                (string) ($response->json('error.code') ?: 'license_rejected'),
                $response->status(),
            );
        }

        return $response->json('data');
    }
}
