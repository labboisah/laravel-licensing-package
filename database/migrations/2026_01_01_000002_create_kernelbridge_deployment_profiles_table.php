<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kernelbridge_deployment_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('product_code')->index();
            $table->string('mode')->default('client');
            $table->string('api_url')->nullable();
            $table->char('api_token_hash', 64)->nullable();
            $table->char('signature_key_hash', 64)->nullable();
            $table->char('fingerprint', 64)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kernelbridge_deployment_profiles');
    }
};
