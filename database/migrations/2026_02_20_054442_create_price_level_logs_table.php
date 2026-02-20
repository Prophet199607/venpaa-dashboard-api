<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePriceLevelLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('price_level_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            $table->unsignedBigInteger('price_level_id')->nullable();
            $table->uuid('u_id')->nullable();
            $table->string('prod_code');
            $table->decimal('purchase_price', 15, 2);
            $table->decimal('selling_price', 15, 2);
            $table->decimal('wholesale_price', 15, 2);
            $table->boolean('has_expiry')->default(false);
            $table->date('expiry_date')->nullable();
            $table->unsignedBigInteger('modified_user')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('price_level_logs');
    }
}
