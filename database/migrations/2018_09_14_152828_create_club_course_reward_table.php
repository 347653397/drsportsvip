<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateClubCourseRewardTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('club_course_reward', function (Blueprint $table) {
            $table->increments('id')->comment('自增ID');
            $table->integer('club_id')->comment('俱乐部ID');
            $table->integer('num_for_try')->default(0)->comment('体验赠送课时数');
            $table->integer('num_for_buy')->default(0)->comment('买单赠送课时数');
            $table->dateTime('created_at')->nullable()->comment('创建时间');
            $table->dateTime('updated_at')->nullable()->comment('更新时间');
            $table->tinyInteger('is_delete')->default(0)->comment('是否删除，0：否，1：是');
        });

        DB::statement("ALTER TABLE `club_course_reward` comment '课时奖励表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('club_course_reward');
    }
}
