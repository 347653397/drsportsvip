<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddVenueIdToUsersClubSalesExamine extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('club_sales_examine', function (Blueprint $table) {
            $table->integer('venue_id')->default(0)->after('student_id')->comment('场馆ID');
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
