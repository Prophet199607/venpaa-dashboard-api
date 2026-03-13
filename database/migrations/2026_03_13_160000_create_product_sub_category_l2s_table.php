<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductSubCategoryL2sTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('product_sub_category_l2s')) {
            Schema::create('product_sub_category_l2s', function (Blueprint $table) {
                $table->id();
                $table->string('prod_code');
                $table->unsignedBigInteger('sub_category_l2_id');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index('prod_code');
                $table->foreign('sub_category_l2_id')->references('id')->on('sub_category_l2s')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_sub_category_l2s');
    }
}
