<?php

namespace KernelBridge\LicensingClient\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use KernelBridge\LicensingClient\Services\LicenseCacheService;
use KernelBridge\LicensingClient\Services\LicenseVerificationService;

final class VerifyLicenseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct()
    {
        $this->onQueue((string) config('kernelbridge-licensing.verification_queue', 'default'));
    }

    public function handle(LicenseVerificationService $verification, LicenseCacheService $cache): void
    {
        if (! $cache->state()->encrypted_license_key) {
            return;
        }

        $verification->verify(force: true);
    }
}
