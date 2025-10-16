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

            $table->string('book_type')->index('book_type');
            $table->string('department')->index('department');
            $table->string('category')->index('category');
            $table->string('sub_category')->index('sub_category');
            $table->string('publisher')->index('publisher');
            $table->string('supplier')->index('supplier');
            $table->string('author')->index('author');

            $table->string('pack_size')->nullable();
            $table->integer('alert_qty')->nullable();

            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->decimal('depth', 8, 2)->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->integer('pages')->nullable();
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