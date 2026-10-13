<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API runtime data, error reports, settings and the admin activity log.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One row per license per day; incremented with an upsert.
        Schema::create('license_daily_stats', function (Blueprint $table) {
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('verifications')->default(0);
            $table->unsignedInteger('rejections')->default(0);
            $table->primary(['license_id', 'day']);
            $table->index('day');
        });

        // Replay protection for billable and write requests.
        Schema::create('api_nonces', function (Blueprint $table) {
            $table->unsignedBigInteger('license_id');
            $table->char('nonce_hash', 64);
            $table->unsignedInteger('expires_at')->index();
            $table->primary(['license_id', 'nonce_hash']);
        });

        // Errors reported by connected applications, grouped by fingerprint.
        Schema::create('app_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->char('fingerprint', 64);
            $table->string('level', 16);
            $table->text('message');
            $table->string('exception_class')->nullable();
            $table->string('file', 500)->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->mediumText('trace')->nullable();
            $table->json('context')->nullable();
            $table->string('url', 1000)->nullable();
            $table->string('environment', 32)->nullable();
            $table->string('app_version', 64)->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->string('status', 16)->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['license_id', 'fingerprint']);
            $table->index(['status', 'last_seen_at']);
            $table->index(['product_id', 'last_seen_at']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64);
            $table->nullableMorphs('subject');
            $table->string('description', 1000);
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('app_errors');
        Schema::dropIfExists('api_nonces');
        Schema::dropIfExists('license_daily_stats');
    }
};
