<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePosTransactionApiTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pos_transaction_api', function (Blueprint $table) {

        $table->increments('Id_No');

        $table->string('Loca', 50)->nullable();
        $table->string('Iid', 50)->nullable();
        $table->string('Item_Code', 50)->nullable();
        $table->string('Item_Descrip', 100)->nullable();

        $table->decimal('Unit_Price', 18, 2)->nullable();
        $table->decimal('Cost_Price', 18, 2)->nullable();
        $table->decimal('Marked_Price', 18, 2)->nullable();
        $table->decimal('Qty', 18, 3)->nullable();
        $table->decimal('Amount', 15, 4)->nullable();

        $table->string('Tr_Type', 50)->nullable();
        $table->string('Receipt_No', 50)->nullable();
        $table->string('SalesMan', 50)->nullable();

        $table->decimal('Discount', 15, 4)->nullable();
        $table->string('Dis', 50)->nullable();
        $table->decimal('SBTT_Disc', 19, 2)->nullable();

        $table->string('Unit', 50)->nullable();
        $table->string('Customer', 50)->nullable();

        $table->string('BillDate', 50)->nullable();
        $table->string('BillTime', 50)->nullable();

        $table->string('ExchangeReceipt', 50)->nullable();
        $table->string('UserName', 50)->nullable();

        $table->dateTime('TransactionDate', 3)->nullable();
        $table->dateTime('InsertDate', 3)->nullable();

        $table->decimal('ProdId', 18, 0)->nullable();

        $table->string('UPD', 50)->nullable();
        $table->string('ShiftEnd', 50)->nullable();
        $table->string('DiscApp', 50)->nullable();
        $table->string('Upload_Id', 50)->nullable();

        $table->string('CrdNoteUPD', 50)->nullable();
        $table->string('GiftIss_Id', 50)->nullable();
        $table->string('GiftRece_Id', 50)->nullable();

        $table->string('Adv_Upload', 50)->nullable();
        $table->string('StaffUpload', 50)->nullable();

        $table->string('R_No', 1000)->nullable();
        $table->string('SH_Qty', 50)->nullable();

        $table->string('ExAllow', 50)->nullable();
        $table->string('DiscAllow', 50)->nullable();
        $table->string('ForeignProduct', 50)->nullable();
        $table->string('Cost_Code', 50)->nullable();
        $table->string('BatchCode', 50)->nullable();

        $table->tinyInteger('ShangrilaUpload')->nullable();

        $table->string('Adv_Customer', 100)->nullable();
        $table->string('Adv_Contact', 100)->nullable();
        $table->string('Adv_NIC', 100)->nullable();
        $table->string('Adv_Address', 250)->nullable();

        $table->string('PC_ID', 300)->nullable();
        $table->string('Merchant_ID', 200)->nullable();
        $table->string('Merchant_Name', 250)->nullable();

        $table->dateTime('InserDate')->useCurrent();
    });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pos_transaction_api');
    }
}
