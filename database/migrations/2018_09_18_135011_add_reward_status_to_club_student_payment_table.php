<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRewardStatusToClubStudentPaymentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('club_student_payment', function (Blueprint $table) {
            $table->tinyInteger('reward_status')->default(0)
                ->after('reserve_record_id')
                ->comment('奖励状态，默认0，1：未发放，2：已发放，3：已失效（未达到指定签到课时数学员退款），主要针对自动脚本给此学员的推荐人发放买单课时奖励做个过滤');
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
