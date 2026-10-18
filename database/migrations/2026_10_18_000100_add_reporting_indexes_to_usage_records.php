<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the dashboard as usage_records grows to millions of rows:
 * - uninvoiced total (invoice_id IS NULL AND billable) is answered from the
 *   index alone instead of a full table scan;
 * - daily charts and monthly counts read (created_at, status) from the index.
 *   It replaces the single-column created_at index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_records', function (Blueprint $table) {
            $table->index(['invoice_id', 'billable', 'unit_price'], 'usage_unbilled_total_index');
            $table->index(['created_at', 'status'], 'usage_created_status_index');
        });

        Schema::table('usage_records', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('usage_records', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('usage_records', function (Blueprint $table) {
            $table->dropIndex('usage_unbilled_total_index');
            $table->dropIndex('usage_created_status_index');
        });
    }
};
