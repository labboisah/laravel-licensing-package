<?php

namespace KernelBridge\LicensingClient\Models;

use Illuminate\Database\Eloquent\Model;

final class LicenseState extends Model
{
    protected $table = 'kernelbridge_license_states';

    protected $guarded = [];

    protected $hidden = ['encrypted_license_key', 'entitlement_payload', 'entitlement_signature', 'config_fingerprint'];

    protected function casts(): array
    {
        return [
            'encrypted_license_key' => 'encrypted',
            'entitlement_payload' => 'encrypted:array',
            'last_successful_verification_at' => 'datetime',
            'expires_at' => 'datetime',
            'offline_grace_expires_at' => 'datetime',
            'config_fingerprinted_at' => 'datetime',
        ];
    }
}
