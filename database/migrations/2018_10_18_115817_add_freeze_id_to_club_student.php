<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFreezeIdToClubStudent extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('club_student', function (Blueprint $table) {
            $table->integer('freeze_id')->default(0)->after('is_freeze')->comment('冻结ID，0未冻结，其他冻结');
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
