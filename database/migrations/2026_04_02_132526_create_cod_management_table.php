<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCodManagementTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cod_management', function (Blueprint $table) {
            $table->id();
            $table->string('customer');
            $table->string('doc_no');
            $table->string('receipt_no');
            $table->string('report_id');
            $table->string('user');
            $table->string('received_amount')->nullable()->default(0);
            $table->string('refund_amount')->nullable()->default(0);
            $table->string('courier_charges')->nullable()->default(0);
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
        Schema::dropIfExists('cod_management');
    }
}
