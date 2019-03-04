<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPrivateLeaveCountToClubStudentPayment extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('club_student_payment', function (Blueprint $table) {
            $table->integer('private_leave_count')->default(0)->after('course_count')->comment('事假额');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('club_student_payment', function (Blueprint $table) {
            //
        });
    }
}
