<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The national code and mobile a service call checked. Stored encrypted
 * (APP_KEY); the *_hash columns are keyed HMACs so the panel can find all
 * calls for one person without decrypting the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_records', function (Blueprint $table) {
            $table->text('national_code')->nullable()->after('client_reference');
            $table->text('mobile')->nullable()->after('national_code');
            $table->char('national_code_hash', 64)->nullable()->after('mobile')->index();
            $table->char('mobile_hash', 64)->nullable()->after('national_code_hash')->index();
        });
    }

    public function down(): void
    {
        Schema::table('usage_records', function (Blueprint $table) {
            $table->dropIndex(['national_code_hash']);
            $table->dropIndex(['mobile_hash']);
            $table->dropColumn(['national_code', 'mobile', 'national_code_hash', 'mobile_hash']);
        });
    }
};
