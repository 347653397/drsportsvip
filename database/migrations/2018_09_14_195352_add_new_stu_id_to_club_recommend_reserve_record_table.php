<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNewStuIdToClubRecommendReserveRecordTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('club_recommend_reserve_record', function (Blueprint $table) {
            $table->integer('new_stu_id')->after('id')->comment('新推荐的学员姓名ID');
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
