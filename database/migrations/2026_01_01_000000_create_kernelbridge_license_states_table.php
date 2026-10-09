<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kernelbridge_license_states', function (Blueprint $table): void {
            $table->id();
            $table->string('product_code')->unique();
            $table->uuid('license_identifier')->nullable()->index();
            $table->uuid('subscription_identifier')->nullable()->index();
            $table->uuid('activation_identifier')->nullable();
            $table->uuid('installation_identifier')->unique();
            $table->text('encrypted_license_key')->nullable();
            $table->string('status')->default('unlicensed')->index();
            $table->timestamp('last_successful_verification_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('offline_grace_expires_at')->nullable();
            $table->longText('entitlement_payload')->nullable();
            $table->char('entitlement_signature', 64)->nullable();
            $table->char('config_fingerprint', 64)->nullable();
            $table->timestamp('config_fingerprinted_at')->nullable();
            $table->string('last_error_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kernelbridge_license_states');
    }
};
