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
        Schema::create('product_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->text('instructions')->nullable(); // sanitized HTML
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'name']);
        });

        Schema::create('product_set_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_set_id')->constrained()->onDelete('cascade');
            $table->string('name'); // shared name of the linked products
            $table->unsignedInteger('quantity')->default(1); // units per set
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // One set item links to every instance of the product (one per location)
        Schema::create('product_set_item_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_set_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');

            $table->unique(['product_set_item_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_set_item_product');
        Schema::dropIfExists('product_set_items');
        Schema::dropIfExists('product_sets');
    }
};
