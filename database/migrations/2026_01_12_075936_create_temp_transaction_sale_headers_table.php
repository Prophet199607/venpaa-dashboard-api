<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTempTransactionSaleHeadersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('temp_transaction_sale_headers', function (Blueprint $table) {
            $table->id();
            $table->string('location');
            $table->string('doc_no');
            $table->date('document_date')->nullable();
            $table->string('customer_code');
            $table->string('iid')->nullable();
            $table->string('recall_type')->nullable();
            $table->string('recall_doc_no')->nullable();
            $table->text('address')->nullable();
            $table->string('p_order_no')->nullable();
            $table->string('manual_no')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('sales_assistant_code')->nullable();
            $table->string('sale_type')->nullable();
            $table->string('payment_Method')->nullable();
            $table->string('type')->nullable();
            $table->decimal('subtotal')->default(0);
            $table->decimal('net_total')->default(0);
            $table->decimal('discount')->default(0);
            $table->decimal('dis_per')->default(0);
            $table->decimal('tax_per')->default(0);
            $table->decimal('delivery_charges')->default(0);
            $table->decimal('tax')->default(0);
            $table->string('comments')->nullable();
            $table->string('payment_mode')->nullable();
            $table->string('invoice_no')->nullable();
            $table->date('invoice_date')->nullable();
            $table->decimal('invoice_amount')->nullable();
            $table->decimal('balance_amount')->default(0);
            $table->boolean('is_approved')->default(0);
            $table->string('approved_by')->nullable();
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
        Schema::dropIfExists('temp_transaction_sale_headers');
    }
}
