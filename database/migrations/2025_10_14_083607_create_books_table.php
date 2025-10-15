<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBooksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();

            $table->string('book_code');
            $table->string('title');
            $table->string('isbn')->nullable();
            $table->year('publish_year')->nullable();

            $table->unsignedBigInteger('book_type')->index('book_type');
            $table->unsignedBigInteger('department')->index('department');
            $table->unsignedBigInteger('category')->index('category');
            $table->unsignedBigInteger('sub_category')->index('sub_category');
            $table->unsignedBigInteger('publisher')->index('publisher');
            $table->unsignedBigInteger('supplier')->index('supplier');
            $table->unsignedBigInteger('author')->index('author');

            $table->string('pack_size')->nullable();
            $table->string('alert_qty')->nullable();

            $table->string('width')->nullable();
            $table->string('height')->nullable();
            $table->string('depth')->nullable();
            $table->string('weight')->nullable();
            $table->string('pages')->nullable();
            $table->string('barcode')->nullable();
            $table->string('language')->nullable();
            $table->string('cover_image')->nullable();
            $table->text('description')->nullable();
            $table->integer('status')->default(1);

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
        Schema::dropIfExists('books');
    }
}