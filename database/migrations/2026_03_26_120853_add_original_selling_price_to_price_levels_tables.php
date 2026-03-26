<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOriginalSellingPriceToPriceLevelsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('price_levels', function (Blueprint $table) {
            $table->decimal('original_selling_price', 15, 2)->default(0)->after('selling_price');
        });

        Schema::table('price_level_logs', function (Blueprint $table) {
            $table->decimal('original_selling_price', 15, 2)->default(0)->after('selling_price');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('price_levels', function (Blueprint $table) {
            $table->dropColumn('original_selling_price');
        });

        Schema::table('price_level_logs', function (Blueprint $table) {
            $table->dropColumn('original_selling_price');
        });
    }
}
