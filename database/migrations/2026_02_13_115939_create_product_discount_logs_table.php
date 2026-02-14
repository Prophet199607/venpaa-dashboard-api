<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductDiscountLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_discount_logs', function (Blueprint $table) {
            $table->id();
            $table->string('prod_code', 50);
            $table->decimal('old_discount', 10, 2)->default(0);
            $table->decimal('new_discount', 10, 2)->default(0);
            $table->decimal('old_dis_per', 10, 2)->default(0);
            $table->decimal('new_dis_per', 10, 2)->default(0);
            $table->string('old_dis_start_date', 20)->nullable();
            $table->string('new_dis_start_date', 20)->nullable();
            $table->string('old_dis_end_date', 20)->nullable();
            $table->string('new_dis_end_date', 20)->nullable();
            $table->string('action', 50);
            $table->string('updated_by', 100)->nullable();
            $table->index('prod_code');
            $table->index('created_at');
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
        Schema::dropIfExists('product_discount_logs');
    }
}
