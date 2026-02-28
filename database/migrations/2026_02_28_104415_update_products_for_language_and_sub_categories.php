<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateProductsForLanguageAndSubCategories extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'language')) {
                $table->string('language')->after('barcode')->nullable();
            }
        });

        Schema::create('product_sub_categories', function (Blueprint $table) {
            $table->id();
            $table->string('prod_code')->index('prod_code');
            $table->unsignedBigInteger('sub_category_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // Migrate existing sub_category data if possible
        DB::table('products')
            ->whereNotNull('sub_category')
            ->where('sub_category', '!=', '')
            ->chunkById(100, function ($products) {
                foreach ($products as $product) {
                    $subCat = DB::table('sub_categories')
                        ->where('scat_code', $product->sub_category)
                        ->first();
                    
                    if ($subCat) {
                        DB::table('product_sub_categories')->insert([
                            'prod_code' => $product->prod_code,
                            'sub_category_id' => $subCat->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('sub_category');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sub_category')->nullable()->after('category');
        });

        Schema::dropIfExists('product_sub_categories');
    }
}
