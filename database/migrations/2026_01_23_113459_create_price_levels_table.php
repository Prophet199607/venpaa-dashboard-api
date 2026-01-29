<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePriceLevelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('price_levels', function (Blueprint $table) {
            $table->id();
            $table->string('prod_code')->index();
            $table->decimal('purchase_price', 15, 2);
            $table->decimal('selling_price', 15, 2);
            $table->decimal('wholesale_price', 15, 2);
            $table->boolean('has_expiry')->default(false);
            $table->date('expiry_date')->nullable();
            $table->timestamps();
            
            // Index on expiry_date for efficient expired records queries
            $table->index('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('price_levels');
    }
}
