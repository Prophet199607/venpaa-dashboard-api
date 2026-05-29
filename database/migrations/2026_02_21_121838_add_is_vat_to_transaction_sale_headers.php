<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsVatToTransactionSaleHeaders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_sale_headers', function (Blueprint $table) {
            $table->boolean('is_vat')->default(false)->after('is_approved');
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
            $table->dropColumn('is_vat');
        });
    }
}
