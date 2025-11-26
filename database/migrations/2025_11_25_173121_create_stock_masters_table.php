<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockMastersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stock_masters', function (Blueprint $table) {
        $table->id();
        $table->string('industry_code');
        $table->string('location');
        $table->string('transaction_date')->nullable();
        $table->string('doc_no');
        $table->string('prod_code');
        $table->string('iid');

        // Quantity with up to 8 digits total and 3 decimal places
        $table->decimal('qty', 8, 3)->default(0.000);

        // Prices and amounts with large decimal capacity
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
        Schema::dropIfExists('stock_masters');
    }
}
