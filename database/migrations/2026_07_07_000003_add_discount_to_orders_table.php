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
            $table->string('discount_type')->nullable()->after('subtotal');          // percent | fixed
            $table->decimal('discount_value', 10, 2)->default(0)->after('discount_type');  // user input
            $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_value'); // computed money amount
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'discount_amount']);
        });
    }
};
