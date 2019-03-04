<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIsDefaultToClubPaymentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('club_payment', function (Blueprint $table) {
            $table->tinyInteger('is_default')->default(0)->after('is_delete')->comment('是否默认,0:否，1:是；主要针对体验缴费和活动缴费是否系统创建做一个区分：1表示系统自动创建');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('club_payment', function (Blueprint $table) {
            //
        });
    }
}
