<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;


class CreateSpRefreshProductUpload extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $procedure = "
        CREATE PROCEDURE `RefreshProductUpload`(
            IN p_iid VARCHAR(255),
            IN p_prod_code VARCHAR(255)
        )
        BEGIN
            -- If updates are happening,
            -- first delete the existing record for this product code from Product_Upload
            DELETE FROM venpaa_new.Product_Upload WHERE Prod_Code = p_prod_code;

            -- Then Insert the new/updated record into Product_Upload
            INSERT INTO venpaa_new.Product_Upload
            (
                Prod_Code,
                Prod_Name,
                Barcode,
                Department_Id,
                Category_Id,
                Supplier_Id,
                Purchase_price,
                Selling_Price,
                Disc_Price,
                Reorder_Level,
                Purchased_Date,
                Unit,
                Created_User,
                Modified_User,
                Created_Date,
                Modified_Date,
                LockedItem,
                Short_Description,
                Whole_Price,
                Disc_Str,
                Cost_Code,
                Margine,
                Brand_Code,
                Pack_Size,
                U_ID,
                M_id,
                TamilDesc
            )
            SELECT
                prod_code,                 -- Prod_Code
                ifnull(title_in_other_language,left(replace(prod_name,'''',' '),100)), -- Prod_Name
                ifnull(barcode,''),        -- Barcode
                department,                -- Department_Id
                '' AS Category_Id,         -- Category_Id
                '' AS Supplier_Id,         -- Supplier_Id
                purchase_price,            -- Purchase_price
                selling_price,             -- Selling_Price
                0 AS Disc_Price,           -- Disc_Price
                0 AS Reorder_Level,        -- Reorder_Level
                updated_at,                -- Purchased_Date
                unit_name,                 -- Unit
                created_by,                -- Created_User
                updated_by,                -- Modified_User
                created_at,                -- Created_Date
                updated_at,                -- Modified_Date
                CASE status WHEN 1 THEN 'F' ELSE 'T' END AS LockedItem, -- LockedItem
                ifnull(replace(title_in_other_language,'''',' '),left(replace(prod_name,'''',' '),50)) AS Short_Description, -- Short_Description
                wholesale_price,           -- Whole_Price
                0 AS Disc_Str,             -- Disc_Str
                '' AS Cost_Code,           -- Cost_Code
                0 AS Margine,              -- Margine
                '' AS Brand_Code,          -- Brand_Code
                1 AS Pack_Size,            -- Pack_Size
                UUID() AS U_ID,            -- U_ID
                'VENPAA' AS M_id,          -- M_id
                ifnull(tamil_description,left(replace(prod_name,'''',' '),100)) -- TamilDesc
            FROM products
            WHERE prod_code = p_prod_code;
        END
        ";

        DB::unprepared("DROP PROCEDURE IF EXISTS RefreshProductUpload");
        DB::unprepared($procedure);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS RefreshProductUpload");
    }
}
