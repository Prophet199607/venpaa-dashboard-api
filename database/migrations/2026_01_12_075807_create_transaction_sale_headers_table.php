<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionSaleHeadersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_sale_headers', function (Blueprint $table) {
            $table->id();
            $table->string('location');
            $table->string('temp_doc_no');
            $table->string('doc_no');
            $table->date('document_date')->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('customer_code')->nullable();
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
            $table->string('type')->default('N/A');
            $table->string('comments')->nullable()->default('N/A');
            $table->string('invoice_no')->default('N/A');
            $table->string('approved_by')->default('N/A');
            $table->decimal('subtotal', 8, 3)->default(0);
            $table->decimal('net_total', 8, 3)->default(0);
            $table->decimal('discount', 8, 3)->default(0);
            $table->decimal('dis_per')->default(0);
            $table->decimal('tax_per')->default(0);
            $table->decimal('delivery_charges', 8, 3)->default(0);
            $table->decimal('tax', 8, 3)->default(0);
            $table->decimal('invoice_amount', 8, 3)->default(0);
            $table->decimal('balance_amount', 8, 3)->default(0);
            $table->boolean('is_approved')->default(false);
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
        Schema::dropIfExists('transaction_sale_headers');
    }
}
