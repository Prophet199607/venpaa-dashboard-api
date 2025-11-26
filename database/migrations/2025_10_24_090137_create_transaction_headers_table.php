<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionHeadersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_headers', function (Blueprint $table) {
            $table->id();
            $table->string('location');
            $table->string('doc_no');
            $table->string('temp_doc_no')->nullable();
            $table->date('document_date')->nullable();
            $table->date('expected_date')->nullable();
            $table->string('transaction_date')->nullable();
            $table->string('grn_date')->nullable();
            $table->string('iid');
            $table->string('supplier_code')->default(0);
            $table->text('delivery_address')->nullable();
            $table->string('delivery_location')->nullable();
            $table->string('ref_number')->nullable()->default(0);
            $table->string('remarks_ref')->nullable();
            $table->string('grn_remarks')->nullable();
            $table->string('srn_remarks')->nullable();
            $table->decimal('subtotal')->default(0);
            $table->decimal('net_total')->default(0);
            $table->decimal('discount')->default(0);
            $table->decimal('dis_per')->default(0);
            $table->decimal('tax_per')->default(0);
            $table->decimal('tax')->default(0);
            $table->string('recall_doc_no')->nullable();
            $table->string('payment_mode')->nullable();
            $table->string('invoice_no')->nullable();
            $table->date('invoice_date')->nullable();
            $table->decimal('invoice_amount')->default(0);
            $table->string('approval_status')->default('pending');
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
        Schema::dropIfExists('transaction_headers');
    }
}
