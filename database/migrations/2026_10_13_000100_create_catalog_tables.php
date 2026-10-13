<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers, products, licenses and the billable services they may use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('national_id', 20)->nullable();
            $table->string('economic_code', 20)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 64)->unique();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('label')->nullable();
            $table->string('key_id', 32)->unique();
            $table->text('secret');
            $table->json('allowed_domains')->nullable();
            $table->json('allowed_ips')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->json('features')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('status', 16)->default('active');
            $table->text('notes')->nullable();
            $table->timestamp('secret_rotated_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_seen_ip', 45)->nullable();
            $table->string('last_seen_version', 64)->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });

        Schema::create('license_service', function (Blueprint $table) {
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->primary(['license_id', 'service_id']);
        });

        Schema::create('customer_service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('unit_price');
            $table->timestamps();

            $table->unique(['customer_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_service_prices');
        Schema::dropIfExists('license_service');
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('services');
        Schema::dropIfExists('products');
        Schema::dropIfExists('customers');
    }
};
