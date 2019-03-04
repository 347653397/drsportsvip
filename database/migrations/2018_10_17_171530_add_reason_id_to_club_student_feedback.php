<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddReasonIdToClubStudentFeedback extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('club_student_feedback', function (Blueprint $table) {
            $table->integer('reason_id')->default(0)->after('intenting_type')->comment('原因ID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('club_student_feedback', function (Blueprint $table) {
            //
        });
    }
}
