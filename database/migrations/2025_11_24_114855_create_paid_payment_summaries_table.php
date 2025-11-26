<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaidPaymentSummariesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('paid_payment_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('temp_doc_no');
            $table->string('org_doc_no');
            $table->string('doc_no');
            $table->string('payment_mode')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('cheque_no')->nullable();
            $table->string('cheque_date')->nullable();
            $table->string('branch')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('location')->nullable();
            $table->string('iid');
            $table->string('acc_code');
            $table->string('document_date')->nullable();
            $table->string('transaction_date')->nullable();
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
        Schema::dropIfExists('paid_payment_summaries');
    }
}
