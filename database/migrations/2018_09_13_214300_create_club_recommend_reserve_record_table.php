<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateClubRecommendReserveRecordTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('club_recommend_reserve_record', function (Blueprint $table) {
            $table->increments('id')->comment('自增ID');
            $table->string('new_stu_name',45)->comment('新推荐的学员姓名');
            $table->integer('new_stu_age')->comment('新推荐的学员年龄');
            $table->string('new_mobile',20)->comment('新推荐的手机号');
            $table->integer('stu_id')->comment('推广学员id');
            $table->integer('sale_id')->comment('推广学员绑定的销售ID');
            $table->tinyInteger('recommend_status')->default(1)->comment('新推广学员的状态，1：已预约，2：已体验，3：已买单');
            $table->dateTime('created_at')->nullable()->comment('创建时间');
            $table->dateTime('updated_at')->nullable()->comment('更新时间');
            $table->tinyInteger('is_delete')->default(0)->comment('是否删除，0：否，1：是');
        });

        DB::statement("ALTER TABLE `club_recommend_reserve_record` comment '用户推广预约记录表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('club_recommend_reserve_record');
    }
}
