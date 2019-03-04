<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTagIdToClubCourseTickets extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('club_course_tickets', function (Blueprint $table) {
            $table->integer('tag_id')->default(0)->after('payment_id')->comment('缴费类型标签ID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('club_course_tickets', function (Blueprint $table) {
            //
        });
    }
}
