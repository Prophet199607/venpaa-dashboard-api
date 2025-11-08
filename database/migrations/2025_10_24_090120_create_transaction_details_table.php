<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_details', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('transaction_header_id');
            $table->string('doc_no');
            $table->integer('line_no');
            $table->string('iid');
            $table->string('prod_code');
            $table->string('prod_name');
            $table->decimal('purchase_price')->default(0);
            $table->decimal('marked_price')->default(0);
            $table->decimal('selling_price')->default(0);
            $table->decimal('whole_sale')->default(0);
            $table->decimal('pack_qty',8,3)->default(0)->nullable();
            $table->decimal('unit_qty',8,3)->default(0)->nullable();
            $table->decimal('free_qty',8,3)->default(0)->nullable();
            $table->decimal('physical_pack_qty',8,3)->default(0)->nullable();
            $table->decimal('physical_unit_qty',8,3)->default(0)->nullable();
            $table->decimal('total_qty',8,3)->default(0);
            $table->decimal('physical_total_qty',8,3)->default(0);
            $table->decimal('pack_size')->default(0);
            $table->decimal('discount')->default(0);
            $table->decimal('line_wise_discount_value')->default(0);
            $table->decimal('dis_per')->default(0);
            $table->decimal('amount')->default(0);
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
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
        Schema::dropIfExists('transaction_details');
    }
}
