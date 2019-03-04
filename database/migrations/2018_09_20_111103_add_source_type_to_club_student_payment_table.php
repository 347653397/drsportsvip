<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSourceTypeToClubStudentPaymentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('club_student_payment', function (Blueprint $table) {
            $table->tinyInteger('source_type')->default(0)->after('contract_no')
                ->comment('预约来源（体验缴费来源）,0:默认（非预约）,1:app预约，2:二维码推广预约，3:后台预约');
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
