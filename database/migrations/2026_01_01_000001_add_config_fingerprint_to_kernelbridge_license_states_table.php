<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kernelbridge_license_states', function (Blueprint $table): void {
            if (! Schema::hasColumn('kernelbridge_license_states', 'config_fingerprint')) {
                $table->char('config_fingerprint', 64)->nullable()->after('entitlement_signature');
            }
            if (! Schema::hasColumn('kernelbridge_license_states', 'config_fingerprinted_at')) {
                $table->timestamp('config_fingerprinted_at')->nullable()->after('config_fingerprint');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kernelbridge_license_states', function (Blueprint $table): void {
            if (Schema::hasColumn('kernelbridge_license_states', 'config_fingerprinted_at')) {
                $table->dropColumn('config_fingerprinted_at');
            }
            if (Schema::hasColumn('kernelbridge_license_states', 'config_fingerprint')) {
                $table->dropColumn('config_fingerprint');
            }
        });
    }
};
