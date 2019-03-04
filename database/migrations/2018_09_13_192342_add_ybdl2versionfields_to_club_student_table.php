<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddYbdl2versionfieldsToClubStudentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('club_student', function (Blueprint $table) {
            $table->integer('from_stu_id')->default(0)->after('attendance_amount')->comment('推荐学员ID:谁推荐了该学员');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('club_student', function (Blueprint $table) {
            //
        });
    }
}
