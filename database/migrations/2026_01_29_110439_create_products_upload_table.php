<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsUploadTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('Product_Upload', function (Blueprint $table) {
            $table->bigIncrements('Id_No');

            $table->string('Prod_Code', 50);
            $table->string('Prod_Name', 100);
            $table->string('Barcode', 30);

            $table->string('Department_Id', 15);
            $table->string('Category_Id', 50);
            $table->string('Supplier_Id', 15);

            $table->decimal('Purchase_price', 19, 4);
            $table->decimal('Selling_Price', 19, 4);
            $table->decimal('Disc_Price', 19, 4);

            $table->integer('Reorder_Level');

            $table->dateTime('Purchased_Date');

            $table->string('Unit', 10);

            $table->string('Created_User', 50)->nullable();
            $table->string('Modified_User', 50)->nullable();

            $table->dateTime('Created_Date')->nullable();
            $table->dateTime('Modified_Date')->nullable();

            $table->string('LockedItem', 1);

            $table->string('Short_Description', 50);

            $table->decimal('Whole_Price', 19, 4);
            $table->decimal('Disc_Str', 18, 2);

            $table->string('Cost_Code', 20)->nullable();
            $table->string('Margine', 10);
            $table->string('Brand_Code', 15);

            $table->integer('Pack_Size');

            $table->char('U_ID', 36);
            $table->string('M_id', 50);

            $table->primary(['Id_No', 'M_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('Product_Upload');
    }
}
