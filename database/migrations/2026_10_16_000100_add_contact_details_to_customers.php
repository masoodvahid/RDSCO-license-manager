<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer contact details (registration number, postal code, website, preferred
 * messengers) and a dated log of conversations with each customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->renameColumn('economic_code', 'registration_number');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('postal_code', 10)->nullable()->after('address');
            $table->string('website')->nullable()->after('email');
            $table->json('contact_channels')->nullable()->after('website');
        });

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('contacted_on');
            $table->string('channel', 16)->nullable();
            $table->text('summary');
            $table->timestamps();

            $table->index(['customer_id', 'contacted_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_contacts');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['postal_code', 'website', 'contact_channels']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->renameColumn('registration_number', 'economic_code');
        });
    }
};
