<?php

namespace KernelBridge\LicensingClient\Models;

use Illuminate\Database\Eloquent\Model;

final class DeploymentProfile extends Model
{
    protected $table = 'kernelbridge_deployment_profiles';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
