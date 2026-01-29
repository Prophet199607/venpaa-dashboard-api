<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientBarcodeSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('client_barcode_settings', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address')->unique();
            $table->string('template_path')->default('C:\\barcode\\STIC33X21.btw');
            $table->string('output_dir')->default('C:\\barcode');
            $table->string('data_file_name')->default('venpaa_barcode.txt');
            $table->boolean('is_active')->default(true);
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
        Schema::dropIfExists('client_barcode_settings');
    }
}
