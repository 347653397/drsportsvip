<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOperateUserIdToUsersClubCourseSign extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('club_course_sign', function (Blueprint $table) {
            $table->integer('operate_user_id')->default(0)->after('is_used')->comment('操作人id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('club_course_sign', function (Blueprint $table) {
            //
        });
    }
}
