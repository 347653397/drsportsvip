<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSignIdToClubCourseSignRecordTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('club_course_sign_record', function (Blueprint $table) {
            $table->integer('sign_id')->default(0)->after('club_id')->comment('签到ID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('club_course_sign_record', function (Blueprint $table) {
            //
        });
    }
}
