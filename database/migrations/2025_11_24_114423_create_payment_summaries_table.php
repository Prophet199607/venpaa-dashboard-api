<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentSummariesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('acc_code');
            $table->string('acc_type');
            $table->string('iid');
            $table->string('doc_no');
            $table->decimal('transaction_amount', 15, 2)->default(0);
            $table->decimal('balance_amount', 15, 2)->default(0);
            $table->string('document_date')->nullable();
            $table->string('transaction_date')->nullable();
            $table->string('location')->nullable();
            $table->boolean('month_end');
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
        Schema::dropIfExists('payment_summaries');
    }
}
