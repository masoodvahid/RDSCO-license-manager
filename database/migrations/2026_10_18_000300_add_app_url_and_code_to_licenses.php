<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - app_url: where the customer's installation runs.
 * - code: a unique random 6-digit number per license, short enough to type
 *   into a mobile app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->string('app_url')->nullable()->after('label');
            $table->char('code', 6)->nullable()->unique()->after('key_id');
        });

        $used = [];

        DB::table('licenses')->whereNull('code')->orderBy('id')->pluck('id')->each(function (int $id) use (&$used): void {
            do {
                $code = (string) random_int(100000, 999999);
            } while (isset($used[$code]));

            $used[$code] = true;
            DB::table('licenses')->where('id', $id)->update(['code' => $code]);
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['app_url', 'code']);
        });
    }
};
