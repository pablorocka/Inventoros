<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('amount_paid', 10, 2)->default(0)->after('total');
            $table->string('payment_status')->default('pending')->after('amount_paid'); // pending | partial | paid
            $table->index(['organization_id', 'payment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'payment_status']);
            $table->dropColumn(['amount_paid', 'payment_status']);
        });
    }
};
