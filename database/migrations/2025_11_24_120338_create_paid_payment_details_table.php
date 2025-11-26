<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaidPaymentDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('paid_payment_details', function (Blueprint $table) {
            $table->id();
            $table->string('industry_code');
            $table->string('org_doc_no');
            $table->string('doc_no');
            $table->decimal('transaction_amount', 20, 2)->default(0);
            $table->string('transaction_date')->nullable();
            $table->decimal('balance_amount', 20, 2)->default(0);
            $table->decimal('paid_amount', 20, 2)->default(0);
            $table->string('temp_doc_no');
            $table->string('location')->default(0);
            $table->string('iid');
            $table->string('acc_code');
            $table->string('document_date')->nullable();
            $table->string('setoff_sr_doc');
            $table->string('remarks')->nullable();
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
        Schema::dropIfExists('paid_payment_details');
    }
}
