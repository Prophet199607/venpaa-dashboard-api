<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateStockSummarySp extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $procedure = "
            DROP PROCEDURE IF EXISTS GetStockSummary;
            CREATE PROCEDURE GetStockSummary(
                IN p_location_code VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            )
            BEGIN
                SELECT 
                    sm.prod_code,
                    p.prod_name,
                    p.unit_name,
                    SUM(sm.qty) as total_qty,
                    p.selling_price,
                    (SUM(sm.qty) * p.selling_price) as stock_value
                FROM stock_masters sm
                JOIN products p ON sm.prod_code = p.prod_code
                WHERE (p_location_code = '' OR sm.location = p_location_code)
                GROUP BY sm.prod_code, p.prod_name, p.unit_name, p.selling_price;
            END
        ";

        DB::unprepared($procedure);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS GetStockSummary;");
    }
}
