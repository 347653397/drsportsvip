<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddClubIdToClubCourseSignSickImage extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('club_course_sign_sick_image', function (Blueprint $table) {
            $table->integer('club_id')->default(0)->after('id')->comment('俱乐部ID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('club_course_sign_sick_image', function (Blueprint $table) {
            //
        });
    }
}
