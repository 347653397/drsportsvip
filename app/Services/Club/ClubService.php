<?php
namespace App\Services\Club;

use App\Facades\Util\Common;
use Exception;

class ClubService implements IClubService
{
    /**
     * 增加一条积分兑换记录（app后台）
     * @param $clubId
     * @param $clubName
     * @throws Exception
     */
    public function addCourseExchangeRecord($clubId,$clubName)
    {
        $res = Common::addOneScoreCourseForClub($clubId,$clubName);

        if ($res['code'] != '200') {
            throw new Exception($res['msg'],$res['code']);
        }
    }
}