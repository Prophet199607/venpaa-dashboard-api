<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class MakeCategoryAndSubCategoryNullableOnProductsTable extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('category')->nullable()->change();
            $table->string('sub_category')->nullable()->change();
        });
    }

    public function down()
    {
        DB::table('products')
            ->whereNull('category')
            ->update(['category' => '']);

        DB::table('products')
            ->whereNull('sub_category')
            ->update(['sub_category' => '']);

        Schema::table('products', function (Blueprint $table) {
            $table->string('category')->nullable(false)->change();
            $table->string('sub_category')->nullable(false)->change();
        });
    }
}
