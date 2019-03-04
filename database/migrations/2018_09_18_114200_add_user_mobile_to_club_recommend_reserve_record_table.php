<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUserMobileToClubRecommendReserveRecordTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('club_recommend_reserve_record', function (Blueprint $table) {
            $table->string('user_mobile',45)->after('stu_id')->comment('推广学员绑定的app用户手机号');
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
