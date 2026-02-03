<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCreditToCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('address')->nullable()->after('dob');
            $table->boolean('is_credit')->nullable()->after('address');
            $table->decimal('credit_limit', 15, 2)->nullable()->after('is_credit');
            $table->integer('credit_period')->nullable()->after('credit_limit');
            $table->boolean('is_vat')->default(false)->after('credit_period');
            $table->string('vat_number')->nullable()->after('is_vat');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('is_credit');
            $table->dropColumn('credit_limit');
            $table->dropColumn('credit_period');
            $table->dropColumn('address');
            $table->dropColumn('is_vat');
            $table->dropColumn('vat_number');
        });
    }
}
