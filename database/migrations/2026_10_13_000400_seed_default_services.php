<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Billable services are referenced by code in the API, so they ship with the schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('services')->insertOrIgnore([
            'code' => 'shahkar',
            'name' => 'استعلام شاهکار',
            'description' => 'تطبیق کد ملی و شماره همراه از طریق سیتاد',
            'unit_price' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('services')->where('code', 'shahkar')->delete();
    }
};
