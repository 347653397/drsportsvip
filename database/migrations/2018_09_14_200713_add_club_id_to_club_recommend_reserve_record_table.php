<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddClubIdToClubRecommendReserveRecordTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('club_recommend_reserve_record', function (Blueprint $table) {
            $table->integer('club_id')->after('id')->comment('俱乐部ID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('club_recommend_reserve_record', function (Blueprint $table) {
            //
        });
    }
}
