<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSickLeaveImageToClubCourseSignTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('club_course_sign', function (Blueprint $table) {
            $table->string('sick_leave_image','455')->default('')->after('is_used')->comment('病假单图片');
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
