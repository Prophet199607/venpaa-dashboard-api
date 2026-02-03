<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateResCashierTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('res_cashier', function (Blueprint $table) {
            $table->id();
            $table->string('emp_code');
            $table->string('emp_name', 50);
            $table->string('username', 50);
            $table->string('password', 50);
            $table->string('mobile_number', 20)->nullable();
            $table->string('cashier_loca');
            $table->string('last_mod_user');
            $table->dateTime('last_mod_date');
            $table->dateTime('tr_date');
            $table->integer('idx')->nullable();
            $table->tinyInteger('cancel')->default(0); 
            $table->tinyInteger('refund')->default(0); 
            $table->tinyInteger('cash_refund')->default(0); 
            $table->tinyInteger('cash_out')->default(0); 
            $table->tinyInteger('discount_allow')->default(0); 
            $table->decimal('discount', 10, 2)->default(0); 
            $table->tinyInteger('dept_allow')->default(0); 
            $table->tinyInteger('day_end_rep')->default(0); 
            $table->tinyInteger('bill_copy')->default(0); 
            $table->integer('sec_level')->nullable();
            $table->tinyInteger('disables')->default(0); 
            $table->tinyInteger('cr_note_issue')->default(0); 
            $table->tinyInteger('gift_voucher_issue')->default(0); 
            $table->tinyInteger('sale_value')->default(0); 
            $table->tinyInteger('new_price_allow')->default(0); 
            $table->decimal('refund_limit', 18, 0)->nullable(); 
            $table->decimal('discount_amount', 18, 0)->default(0);            
            $table->uuid('msrepl_tran_version');
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
        Schema::dropIfExists('res_cashier');
    }
}
