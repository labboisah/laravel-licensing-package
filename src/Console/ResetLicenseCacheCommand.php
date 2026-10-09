<?php

namespace KernelBridge\LicensingClient\Console;

use Illuminate\Console\Command;
use KernelBridge\LicensingClient\Services\LicenseCacheService;

final class ResetLicenseCacheCommand extends Command
{
    protected $signature = 'kernelbridge:reset-license-cache {--product= : Product code to reset (defaults to current config)}';

    protected $description = 'Clear the cached KernelBridge license state so activation can be retried cleanly.';

    public function handle(LicenseCacheService $cache): int
    {
        $product = (string) $this->option('product');
        if ($product !== '') {
            config()->set('kernelbridge-licensing.product_code', $product);
        }

        $state = $cache->state();
        $cache->reset();

        $this->info('KernelBridge local license cache reset for '.$state->product_code.'.');

        return self::SUCCESS;
    }
}
