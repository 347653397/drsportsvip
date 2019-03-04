<?php

namespace App\Api\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Model\Common\ClubCity;
use App\Model\Common\ClubDistrict;
use App\Model\Common\ClubProvince;
use Carbon\Carbon;
use App\Services\Common\CommonService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommonController extends Controller
{
    //俱乐部所在的省市
    public function province(Request $request)
    {
        $province = ClubProvince::get(['code', 'name']);
        return returnMessage('200', '',$province);
    }

    //俱乐部所在城市
    public function city(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'code' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.param.1001'));
        }
        $code = $input['code'];
        $city = ClubCity::where('provincecode',$code)->get(['code', 'name']);
        return returnMessage('200', '',$city);
    }

    //俱乐部所在的区域
    public function district(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'code' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.param.1001'));
        }
        $code = $input['code'];
        $district = ClubDistrict::where('citycode',$code)->get(['code', 'name']);
        return returnMessage('200', '',$district);
    }

    /**
     * 获取七牛token
     * @return array
     */
    public function getQiNiuToken(){
        $Niu = new CommonService();
        $NiuToken = $Niu->getQiniuToken();
        return returnMessage('200','请求成功',$NiuToken);
    }

    /**
     * 根据俱乐部ID获取俱乐部名称
     * @param Request $request
     * @return array
     */
    public function getClubName(Request $request)
    {
        $club = Club::find($request->input('clubId'));

        if (empty($club)) {
            return returnMessage('1004', config('error.common.1004'));
        }

        return returnMessage('200','',['clubName' => $club->name]);
    }

    /**
     * 根据地址获取经纬度
     * @param Request $request
     * @return array
     */
    public function getLatitudeLongitude(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'address' => 'required|string'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $header[] = 'Referer: http://lbs.qq.com/webservice_v1/guide-suggestion.html';
        $header[] = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.139 Safari/537.36';
        $url ="http://apis.map.qq.com/ws/place/v1/suggestion/?&region=&key=SJRBZ-4TI34-KCJUV-D53O4-H3G7Q-7HFLN&keyword=".$input['address'];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_HTTPHEADER,$header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        $output = curl_exec($ch);

        curl_close($ch);

        $result = json_decode($output,true);
        if (empty($result['data'])) {
            return returnMessage('1010', config('error.common.1010'));
        }

        $list['address'] = $result['data'][0]['address'];
        $list['latitude'] = $result['data'][0]['location']['lat'];
        $list['longitude'] = $result['data'][0]['location']['lng'];
        return returnMessage('200','', $list);
    }
}