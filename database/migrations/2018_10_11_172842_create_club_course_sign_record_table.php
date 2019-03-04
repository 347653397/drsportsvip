<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClubCourseSignRecordTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('club_course_sign_record', function (Blueprint $table) {
            $table->increments('id')->comment('自增ID');
            $table->integer('club_id')->comment('俱乐部ID');
            $table->integer('student_id')->comment('学员ID');
            $table->tinyInteger('sign_status')->comment('签到状态');
            $table->dateTime('sign_date')->nullable()->comment('签到时间');
            $table->integer('operate_user_id')->comment('操作员用户ID');
            $table->dateTime('created_at')->nullable()->comment('创建时间');
            $table->dateTime('updated_at')->nullable()->comment('更新时间');
            $table->tinyInteger('is_delete')->default(0)->comment('是否删除，0：否，1：是');
        });

        DB::statement("ALTER TABLE `club_course_sign_record` comment '学员签到的更改记录表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('club_course_sign_record');
    }
}
