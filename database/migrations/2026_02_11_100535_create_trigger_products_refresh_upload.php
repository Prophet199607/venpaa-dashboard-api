<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateTriggerProductsRefreshUpload extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::unprepared("DROP TRIGGER IF EXISTS trg_products_after_update");

        DB::unprepared("
            CREATE TRIGGER trg_products_after_update
            AFTER UPDATE ON products
            FOR EACH ROW
            BEGIN
                CALL RefreshProductUpload(NEW.id, NEW.prod_code);
            END
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared("DROP TRIGGER IF EXISTS trg_products_after_update");
    }
}
