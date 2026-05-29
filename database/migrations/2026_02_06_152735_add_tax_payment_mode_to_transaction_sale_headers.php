<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTaxPaymentModeToTransactionSaleHeaders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_sale_headers', function (Blueprint $table) {
             $table->string('payment_mode')->nullable()->after('comments');
             $table->string('vat_percent')->nullable()->after('payment_mode')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transaction_sale_headers', function (Blueprint $table) {
            //
        });
    }
}
