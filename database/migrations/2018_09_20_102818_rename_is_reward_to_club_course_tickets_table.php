<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameIsRewardToClubCourseTicketsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('club_course_tickets', function (Blueprint $table) {
            $table->tinyInteger('reward_type')->default(0)->after('is_delete')
                ->comment('赠送类型,0:默认（非赠送券）,1:学员推广奖励（包括推广的学员预约和买单）,2:买课赠送课时（学员自己买单，无推荐人）, 3:体验券, 4:正式券');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
