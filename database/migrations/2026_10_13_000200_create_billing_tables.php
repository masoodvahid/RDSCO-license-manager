<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usage ledger, invoices and payments. Amounts are integer Rials.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('issued');
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->unsignedBigInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('amount');
        });

        // Append-only ledger of billable and non-billable service calls.
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('license_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('status', 16);
            $table->boolean('billable')->default(false);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->string('reference', 64)->nullable();
            $table->string('client_reference', 64)->nullable();
            $table->smallInteger('upstream_code')->nullable();
            $table->string('upstream_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // "Not yet invoiced" lookups per customer, in id order.
            $table->index(['customer_id', 'invoice_id', 'billable', 'id'], 'usage_uninvoiced_index');
            $table->index(['service_id', 'created_at']);
            $table->index(['license_id', 'created_at']);
            $table->index(['license_id', 'client_reference']);
            $table->index('created_at');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('paid_on');
            $table->string('method', 24)->default('bank_transfer');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('usage_records');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
