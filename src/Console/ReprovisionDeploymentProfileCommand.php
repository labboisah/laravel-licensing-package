<?php

namespace KernelBridge\LicensingClient\Console;

use Illuminate\Console\Command;
use KernelBridge\LicensingClient\Models\DeploymentProfile;
use KernelBridge\LicensingClient\Services\DeploymentProfile as DeploymentProfileService;

final class ReprovisionDeploymentProfileCommand extends Command
{
    protected $signature = 'kernelbridge:reprovision-profile {--force : Replace the existing record without a confirmation prompt}';

    protected $description = 'Persist a new signed deployment profile and invalidate stale activation state when the deployment config is intentionally changed.';

    public function handle(DeploymentProfileService $profile): int
    {
        $existing = DeploymentProfile::query()->where('product_code', $profile->productCode())->first();
        if ($existing && ! $this->option('force') && ! $this->confirm('A deployment profile already exists for this product. Replace it and invalidate the old deployment state?', true)) {
            $this->info('Deployment profile re-provision cancelled.');

            return self::SUCCESS;
        }

        $record = $profile->persist();

        $this->info('Deployment profile persisted for '.$record->product_code.'.');
        $this->line('Fingerprint: '.$record->fingerprint);

        return self::SUCCESS;
    }
}
