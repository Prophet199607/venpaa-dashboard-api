<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUidAndModifiedUserToPriceLevelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('price_levels', function (Blueprint $table) {
            $table->uuid('u_id')->unique()->after('id');
            $table->unsignedBigInteger('modified_user')->nullable()->after('wholesale_price');
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
            $table->dropColumn(['u_id', 'modified_user']);
        });
    }
}
