<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductSaleSummariesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_sale_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no');
            $table->string('product_code');
            $table->string('product_name');
            $table->string('iid');
            $table->string('location')->nullable();
            $table->decimal('selling_price')->default(0);
            $table->decimal('purchase_price')->default(0);
            $table->decimal('amount')->default(0);
            $table->decimal('pack_qty', 8, 3)->default(0);
            $table->decimal('unit_qty', 8, 3)->default(0);
            $table->decimal('free_qty', 8, 3)->default(0);
            $table->decimal('cost')->default(0);
            $table->decimal('discount')->default(0);
            $table->date('sale_date');
            $table->string('unit')->default(0);
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
        Schema::dropIfExists('product_sale_summaries');
    }
}
