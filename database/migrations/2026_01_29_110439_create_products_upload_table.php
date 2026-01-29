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
        Schema::create('products_upload', function (Blueprint $table) {
            $table->id();

            $table->string('prod_code');
            $table->string('prod_name');
            $table->string('short_description')->nullable();
            $table->string('department')->index('department');
            $table->string('category')->index('category');
            $table->string('sub_category')->index('sub_category');

            $table->string('pack_size')->nullable();
            $table->decimal('purchase_price')->default(0.0);
            $table->decimal('selling_price')->default(0.0);
            $table->decimal('marked_price')->default(0.0)->nullable();
            $table->decimal('wholesale_price')->default(0.0)->nullable();

            $table->string('title_in_other_language')->nullable();
            $table->string('book_type')->index('book_type')->nullable();
            $table->string('publisher')->index('publisher')->nullable();

            $table->string('isbn')->nullable();
            $table->year('publish_year')->nullable();
            $table->integer('alert_qty')->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->decimal('depth', 8, 2)->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->integer('pages')->nullable();
            $table->string('barcode')->nullable();
            $table->string('language')->nullable();
            $table->string('prod_image')->nullable();
            $table->text('description')->nullable();
            $table->integer('status')->default(1);
            $table->string('unit_name')->default("NOS");

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
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
        Schema::dropIfExists('products_upload');
    }
}
