<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockMasterUploadTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stock_master_upload', function (Blueprint $table) {
            $table->id();
            $table->string('location');
            $table->string('transaction_date')->nullable();
            $table->string('doc_no');
            $table->string('prod_code');
            $table->string('iid');
            $table->decimal('qty', 8, 3)->default(0.000);
            $table->decimal('purchase_price', 20, 2)->default(0.00);
            $table->decimal('selling_price', 20, 2)->default(0.00);
            $table->decimal('amount', 20, 2)->default(0.00);
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
        Schema::dropIfExists('stock_master_upload');
    }
}
