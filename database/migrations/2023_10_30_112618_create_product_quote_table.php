<?php

use App\Models\Product;
use App\Models\Quote;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_quote', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Quote::class)->constrained();
            $table->foreignIdFor(Product::class)->constrained();
            $table->unsignedInteger('quantity');
            $table->integer('price');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_quote');
    }
};
