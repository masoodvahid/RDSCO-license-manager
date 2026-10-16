<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Natural person (کد ملی) or legal entity (شناسه ملی).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('type', 16)->default('individual')->after('id');
        });

        // Existing customers with a company name are most likely legal entities.
        DB::table('customers')->whereNotNull('company_name')->where('company_name', '!=', '')->update(['type' => 'legal']);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
