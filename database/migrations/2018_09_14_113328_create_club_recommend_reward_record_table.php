<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateClubRecommendRewardRecordTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('club_recommend_reward_record', function (Blueprint $table) {
            $table->increments('id')->comment('自增ID');
            $table->integer('recommend_id')->comment('推荐ID，club_recommend_reserve_record的ID');
            $table->string('user_mobile',45)->comment('推荐人手机号');
            $table->integer('stu_id')->default(0)->comment('推荐人对应绑定的学员ID');
            $table->string('stu_name',45)->comment('推荐人对应绑定的学员姓名');
            $table->string('new_mobile',45)->comment('被推荐的手机号');
            $table->integer('new_stu_id')->default(0)->comment('被推荐的手机号对应的学员ID');
            $table->string('new_stu_name',45)->comment('被推荐的手机号对应的学员姓名');
            $table->tinyInteger('event_type')->comment('事件类型，1:体验,2:买单');
            $table->integer('reward_course_num')->comment('赠送课时数');
            $table->tinyInteger('settle_status')->default(1)->comment('结算状态,1:未结算,2:已结算');
            $table->dateTime('created_at')->nullable()->comment('创建时间');
            $table->dateTime('updated_at')->nullable()->comment('更新时间');
            $table->tinyInteger('is_delete')->default(0)->comment('是否删除，0：否，1：是');
        });

        DB::statement("ALTER TABLE `club_recommend_reward_record` comment '推广奖励记录表，用于记录推广用户奖励的记录'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('club_recommend_reward_record');
    }
}
