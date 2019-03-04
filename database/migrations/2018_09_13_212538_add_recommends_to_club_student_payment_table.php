<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRecommendsToClubStudentPaymentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('club_student_payment', function (Blueprint $table) {
            $table->integer('reserve_record_id')->default(0)->after('is_pay_again')->comment('奖励记录ID');
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
