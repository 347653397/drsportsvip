<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddClassIdToUsersClubSalesExamine extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('club_sales_examine', function (Blueprint $table) {
            $table->integer('class_id')->default(0)->after('venue_id')->comment('班级ID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('club_sales_examine', function (Blueprint $table) {
            //
        });
    }
}
