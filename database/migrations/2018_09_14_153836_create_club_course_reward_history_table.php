<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateClubCourseRewardHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('club_course_reward_history', function (Blueprint $table) {
            $table->increments('id')->comment('自增ID');
            $table->integer('course_reward_id')->comment('课时奖励ID');
            $table->integer('club_id')->comment('俱乐部ID');
            $table->integer('num_for_try')->default(0)->comment('体验赠送课时数');
            $table->integer('num_for_buy')->default(0)->comment('买单赠送课时数');
            $table->integer('operator_id')->comment('操作员id');
            $table->dateTime('created_at')->nullable()->comment('创建时间');
            $table->dateTime('updated_at')->nullable()->comment('更新时间');
        });

        DB::statement("ALTER TABLE `club_course_reward_history` comment '课时奖励历史表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('club_course_reward_history');
    }
}
