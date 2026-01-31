<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePosSalesmanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pos_salesman', function (Blueprint $table) {
            $table->id();
            $table->string("sales_code");
            $table->string("sales_name");
            $table->string("sales_email")->nullable();
            $table->string("sales_phone")->nullable();
            $table->string("sales_address")->nullable();
            $table->string("sales_photo")->nullable();
            $table->integer("sales_status")->default(1);
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
        Schema::dropIfExists('pos_salesman');
    }
}
