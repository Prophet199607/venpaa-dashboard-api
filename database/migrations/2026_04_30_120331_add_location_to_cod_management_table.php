<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLocationToCodManagementTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cod_management', function (Blueprint $table) {
            $table->string('location')->after('customer');
            $table->dateTime('transaction_date')->after('location');
            $table->decimal('transaction_amount', 19, 4)->after('transaction_date');
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cod_management', function (Blueprint $table) {
            $table->dropColumn('location');
            $table->dropColumn('transaction_date');
            $table->dropColumn('transaction_amount');
        });
    }
}
