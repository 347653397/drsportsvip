<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateClubRecommendUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('club_recommend_user', function (Blueprint $table) {
            $table->increments('id')->comment('自增ID');
            $table->integer('student_id')->comment('学员ID');
            $table->string('user_mobile',32)->comment('学员对应的app用户绑定手机号');
            $table->dateTime('created_at')->nullable()->comment('创建时间');
            $table->dateTime('updated_at')->nullable()->comment('更新时间');
            $table->tinyInteger('is_delete')->default(0)->comment('是否删除，0：否，1：是');
        });

        DB::statement("ALTER TABLE `club_recommend_user` comment '推广用户表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('club_recommend_user');
    }
}
