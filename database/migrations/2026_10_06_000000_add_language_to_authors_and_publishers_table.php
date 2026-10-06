<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLanguageToAuthorsAndPublishersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('authors', 'language')) {
            Schema::table('authors', function (Blueprint $table) {
                $table->string('language')->nullable()->after('auth_image');
            });
        }

        if (!Schema::hasColumn('publishers', 'language')) {
            Schema::table('publishers', function (Blueprint $table) {
                $table->string('language')->nullable()->after('pub_image');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('authors', 'language')) {
            Schema::table('authors', function (Blueprint $table) {
                $table->dropColumn('language');
            });
        }

        if (Schema::hasColumn('publishers', 'language')) {
            Schema::table('publishers', function (Blueprint $table) {
                $table->dropColumn('language');
            });
        }
    }
}
