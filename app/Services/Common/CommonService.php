<?php
namespace App\Services\Common;
use App\Facades\Util\Common;
use App\Model\ClubOperationLog\ClubOperationLog;
use App\Model\ClubUser\ClubUser;
use App\Model\Permission\Department;
use App\Model\ClubSales\ClubSales;
use Qiniu\Auth;
use App\Model\ClubClassStudent\ClubClassStudent;
use App\Model\ClubStudent\ClubStudent;
use Carbon\Carbon;
use App\Model\ClubClass\ClubClassTime;
use App\Model\ClubVenue\ClubVenue;
use Illuminate\Support\Facades\DB;
use App\Model\ClubClass\ClubClass;
use Qiniu\Storage\UploadManager;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Model\Club\Club;
use App\Model\ClubClassType\ClubClassType;
use App\Model\ClubStudentBindApp\ClubStudentBindApp;
use App\Model\ClubCourse\ClubCourseSign;
use App\Model\ClubPayment\ClubPayment;
use App\Model\ClubClassImage\ClubClassImage;
use App\Model\ClubCourse\ClubCourse;
use App\Facades\ClubStudent\Subscribe;
use Exception;
use App\Services\Payment\PaymentService;
use App\Facades\ClubStudent\Student;
use App\Model\ClubStudentPayment\ClubStudentPayment;
use App\Model\ClubSystem\ClubExamsStudent;
use App\Model\ClubStudentSubscribe\ClubStudentSubscribe;
use App\Services\Student\StudentService;
use App\Model\ClubSystem\ClubExams;
use Illuminate\Support\Facades\Redis;
use App\Model\Common\ClubCity;
use App\Model\Common\ClubProvince;
use App\Model\Common\ClubDistrict;
use App\Model\ClubSystem\ClubMessageApp;
use GuzzleHttp\Client;
use App\Model\ClubErrorLog\ClubErrorLog;
use App\Facades\Util\Log;
use App\Model\Recommend\ClubRecommendUser;
use App\Model\Recommend\ClubRecommendReserveRecord;
use App\Model\ClubCourseTickets\ClubCourseTickets;
use App\Model\Recommend\ClubRecommendRewardRecord;
use App\Model\Recommend\ClubCourseReward;
use App\Model\Recommend\ClubCourseRewardHistory;
use App\Model\ClubCourseCoach\ClubCourseCoach;
use App\Model\ClubCourseSign\ClubCourseSign as CourseSign;
use App\Facades\Util\Sms;
use App\Model\ClubSystem\ClubMessage;
use App\Model\ClubChannel\ClubChannel;
use App\Facades\Classes\Classes;
use App\Model\ClubCourseSign\ClubCourseSignRecord;

class CommonService
{
    /**
     * 根据负责人userId获取所在所有销售员ID,非部门负责人返回空
     * @param $userId
     * @return array
     */
    public function getAllSalesIdsByUserId($userId)
    {
        if (intval($userId) <= 0) return [];

        //获取用户的部门id
        $clubUser = ClubUser::where('user_status', 1)
            ->where('is_delete', 0)
            ->where('id', $userId)
            ->first();

        if (empty($clubUser)) return [];    //用户不存在

        $dept_id = $clubUser->dept_id;

        //是否是销售部
        $department = Department::where('type', 2)->where('id', $dept_id)->first();

        if (empty($department)) {//非销售部
            return [];
        }

        $principal_ids = explode(',', $department->principal_id);

        //非部门负责人，直接返回该用户的销售ID
        if (!in_array($userId, $principal_ids)) {
            $salesIds = ClubSales::where('status', 1)
                ->where('user_id', $userId)
                ->pluck('id');

            return $salesIds;
        }

        //是部门负责人则递归获取该用户所在部门及其子部门所有销售员的ID
        $deptIds = $this->getChildDeptIds($dept_id);

        //合并所有的部门ID
        $deptIds = array_merge([$dept_id], $deptIds);

        //获取所有销售员ID
        $salesIds = ClubSales::where('status', 1)
            ->whereIn('sales_dept_id', $deptIds)
            ->pluck('id');

        return $salesIds;
    }

    /**
     * 递归获取当前部门下所有子部门id
     * @param int $dept_id
     * @return array
     */
    private function getChildDeptIds($dept_id = 0)
    {
        static $deptIds = array();
        $departments = Department::where('type', 2)->where('parent_id', $dept_id)->get();

        if ($departments->count() > 0) {
            foreach ($departments as $key => $department) {
                $deptIds[] = $department->id;
                $this->getChildDeptIds($department->id);
            }
        }

        return $deptIds;
    }

    /**
     * 根据俱乐部获取俱乐部
     * @param $clubId
     * @return mixed
     */
    public function getClubById($clubId)
    {
        return Club::valid()->find($clubId);
    }

    /**
     * 获取七牛token
     * @return string
     */
    public function getQiniuToken()
    {
        $auth = new Auth(config('qiniu.accessKey'), config('qiniu.secretKey'));
        return $auth->uploadToken(config('qiniu.bucket'));
    }

    /**
     * 实名认证
     * @param $idCard 身份证号
     * @param $name 姓名
     * @return array
     */
    public function idCardCheck($idCard, $name)
    {
        $host = 'https://idcert.market.alicloudapi.com';
        $path = '/idcard';
        $method = 'GET';
        $appcode = env('ALIYUN_IDCARD_APPCODE');
        $headers = array();
        array_push($headers, 'Authorization:APPCODE ' . $appcode);
        $querys = "idCard=$idCard&name=$name";
        $url = $host . $path . "?" . $querys;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        if (1 == strpos("$" . $host, "https://")) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        $response = curl_exec($curl);
        $result = json_decode($response, true);

        if ($result['status'] != '01') {
            return ["code" => $result['status'], "msg" => $result['msg']];
        }

        return ["code" => '200', "msg" => ""];
    }

    /**
     * 获取班级学员年龄阶段
     * @param $classId
     * @return array
     */
    public function getClassStudentMinAndMaxAge($classId)
    {
        $returnData = [
            'min' => 0,
            'max' => 0,
            'age_stage' => '0-0',
        ];

        $studentIds = ClubClassStudent::where('class_id', $classId)
            ->pluck('student_id');

        if ($studentIds->count() == 0) return $returnData;

        $maxBirth = ClubStudent::whereIn('id', $studentIds)->officalStudents()->min('birthday');
        $minBirth = ClubStudent::whereIn('id', $studentIds)->officalStudents()->max('birthday');

        $minAge = !empty(Carbon::parse($minBirth)->diffInYears()) ? Carbon::parse($minBirth)->diffInYears() : 0;
        $maxAge = !empty(Carbon::parse($maxBirth)->diffInYears()) ? Carbon::parse($maxBirth)->diffInYears() : 0;

        $returnData = [
            'min' => $minAge,
            'max' => $maxAge,
            'age_stage' => $minAge . '-' . $maxAge
        ];

        return $returnData;
    }

    /**
     * 记录操作日志
     * @param $operationUserId int 操作员id
     * @param $operationType int 操作类型，1新增，2修改，3删除，4失效，5生效
     * @param $operationObject int 操作对象，1权限，2学员，3班级，4场馆，5销售，6教练
     * @param $originValue array 初始数据，新增为空
     * @param $updateValue array 修改数据，删除为空
     * @param $operationDesc string 操作描述
     */
    public function addOperationLog($operationUserId, $operationType, $operationObject, $originValue, $updateValue, $operationDesc)
    {
        $originStr = '';
        $updateStr = '';
        if (!empty($originValue)) {
            foreach ($originValue as $key => $value) {
                $originStr .= $key . '=>' . $value . ',';
            }
        }
        if (!empty($updateValue)) {
            foreach ($updateValue as $key => $value) {
                $updateStr .= $key . '=>' . $value . ',';
            }
        }
        $operationUser = ClubUser::where('id', $operationUserId)->first();
        $operation = new ClubOperationLog();
        $operation->operation_user_id = $operationUserId;
        $operation->operation_user_name = $operationUser->username;
        $operation->operation_type = $operationType;
        $operation->operation_object = $operationObject;
        $operation->operation_datetime = date('Y-m-d H:i:s', time());
        $operation->origin_value = $originStr;
        $operation->update_value = $updateStr;
        $operation->operation_desc = $operationDesc;
        $operation->save();
    }

    /**
     * 获取班级课程时间（拼接成字符串）
     * @param $classId
     * @return string
     */
    public function getClassTimeString($classId)
    {
        $timeArr = $this->getClassTimes($classId);

        if (empty($timeArr)) return '';

        return implode(',', $timeArr);
    }

    /**
     * 获取班级课程时间
     * @param $classId
     * @return array
     */
    public function getClassTimes($classId)
    {
        $classTime = ClubClassTime::where('class_id', $classId)->get();

        if ($classTime->isEmpty()) return [];

        $timeArr = [];

        $carbon = new Carbon();

        $classTime->each(function ($item) use (&$timeArr, $carbon) {
            $startTime = $carbon->copy()->parse($item->start_time)->format('H:i');
            $endTime = $carbon->copy()->parse($item->end_time)->format('H:i');

            $timeArr[] = $this->getWeekName($item->day) . ' ' . $startTime . '-' . $endTime;
        });

        return $timeArr;
    }

    /**
     * 获取班级第一个课程时间
     * @param $classId
     * @return array
     */
    public function getFirstClassTime($classId)
    {
        $classTime = ClubClassTime::where('class_id', $classId)->orderBy('created_at', 'asc')->first();

        if (empty($classTime)) return [];

        $carbon = new Carbon();

        $startTime = $carbon->copy()->parse($classTime->start_time)->format('H:i');
        $endTime = $carbon->copy()->parse($classTime->end_time)->format('H:i');

        $weekName = $this->getWeekName($classTime->day);

        return [
            'week' => $classTime->day,
            'weekName' => $weekName,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'timeString' => $weekName . ' ' . $startTime . '-' . $endTime
        ];
    }

    /**
     * 获取第一个班级
     * @param $classId
     * @return array
     */
    public function getFirstClass($classId)
    {
        $classTime = ClubClassTime::with(['class'])->where('class_id', $classId)->orderBy('created_at', 'asc')->first();

        $classImg = ClubClassImage::notDelete()->show()->where('class_id', $classId)->orderBy('created_at', 'DESC')->first();

        if (empty($classTime)) return [];

        $carbon = new Carbon();

        $startTime = $carbon->copy()->parse($classTime->start_time)->format('H:i');
        $endTime = $carbon->copy()->parse($classTime->end_time)->format('H:i');

        $weekName = $this->getWeekName($classTime->day);

        return [
            'className' => $classTime->class->name,
            'week' => $classTime->day,
            'weekName' => $weekName,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'timeString' => $weekName . ' ' . $startTime . '-' . $endTime,
            'classImg' => $classImg ? env('IMG_DOMAIN') . $classImg->file_path : config('public.DEFAULT_CLASS_IMG')
        ];
    }

    /**
     * 获取班级的一张照片
     * @param $classId
     * @return \Illuminate\Config\Repository|mixed|string
     */
    public function getFirstClassImg($classId)
    {
        $classImg = ClubClassImage::notDelete()->show()->where('class_id', $classId)->orderBy('created_at', 'DESC')->value('file_path');

        return $classImg ? env('IMG_DOMAIN') . $classImg : config('public.DEFAULT_CLASS_IMG');
    }

    /**
     * 获取单节课的时长（单位：小时）
     * @param $classId
     * @return array|string
     */
    public function getEveryCourseTimeLong($classId)
    {
        $classTime = ClubClassTime::where('class_id', $classId)->orderBy('created_at', 'asc')->first();

        if (empty($classTime)) return [];

        $carbon = new Carbon();

        $startTime = $carbon->copy()->parse($classTime->start_time)->timestamp;
        $endTime = $carbon->copy()->parse($classTime->end_time)->timestamp;

        return $this->numberFormatFloat(($endTime - $startTime) / 3600, 1);
    }

    /**
     * 获取星期名称
     * @param $week 1～7
     * @return mixed
     */
    public function getWeekName($week)
    {
        $weekData = [
            '1' => '周一',
            '2' => '周二',
            '3' => '周三',
            '4' => '周四',
            '5' => '周五',
            '6' => '周六',
            '7' => '周日',
        ];

        return $weekData[$week];
    }

    /**
     * 计算距离
     * @param $lat1
     * @param $lng1
     * @param $lat2
     * @param $lng2
     * @author 孙龙
     * @return string
     */
    public static function getDistance($lat1, $lng1, $lat2, $lng2)
    {
        if (empty($lat1) || empty($lng1) || empty($lat2) || empty($lng2)) return '';

        $PI = 3.141592;
        $radius = 6378.137;
        $radLat1 = $lat1 * $PI / 180.0;
        $radLat2 = $lat2 * $PI / 180.0;
        $a = $radLat1 - $radLat2;
        $b = ($lng1 * $PI / 180.0) - ($lng2 * $PI / 180.0);
        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2)));
        $s = $s * $radius;
        $s = round($s * 1000);
        $s /= 1000;
        return round($s, 1) . 'km';
    }

    /**
     * 生成二维码
     * @param $str  二维码显示的内容
     * @param string $dis 生成二维码的后缀 PNG、SVG 和 RPS
     * @param $size  二维码的尺寸
     * @param $filePath 存放二维码路径
     * @param string $encoding 字符编码
     */
    public function getQrCode($str, $dis = 'png', $size, $filePath, $encoding = 'UTF-8')
    {
        QrCode::format($dis)->size($size)->encoding($encoding)->generate($str, $filePath);
    }

    /**
     * 生成文字图片
     * @param $str
     */
    public function getFontImg($str, $studentId)
    {
        $img = imagecreate(210, 98);
        $color = imagecolorallocate($img, 255, 255, 255);
        imagecolortransparent($img, $color);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagettftext($img, 50, -7, 5, 60, $white, public_path('msyh.ttc'), $str); //字体设置部分linux和windows的路径可能不同
        imagepng($img, public_path('qrcodes/font' . $studentId . '.png'));
    }

    /**
     * 海报图片合成
     * @param $data
     * @return array
     * @throws Exception
     */
    public function compoundPoster($data)
    {
        $arr = [];
        foreach ($data as $key => $val) {
            //判断该学员是否有海报
            if (!$val['isPoster']) {
                $this->getFontImg($val['studentName'], $val['studentId']);

                $prefix = env('REVERSE_DOMAIN') . "?fromUserId=" . $val['studentId'] . "&clubId=" . $val['clubId'] . "&salesId=" . $val['salesId'] . "&appUserMobile=" . $val['appUserMobile'];

                $this->getQrCode($prefix, 'png', '340', public_path('qrcodes/qrcode' . $val['studentId'] . '.png'));
                //俱乐部海报
                $posterBg = imagecreatefromjpeg(storage_path('poster/poster1.jpg'));
                //学员二维码
                $qrCode = imagecreatefrompng(public_path('qrcodes/qrcode' . $val['studentId'] . '.png'));
                //学员姓名图片
                $font = imagecreatefrompng(public_path('qrcodes/font' . $val['studentId'] . '.png'));
                //图片合并
                imagecopyresampled($posterBg, $qrCode, 70, 1502, 0, 0, 340, 340, imagesx($qrCode), imagesy($qrCode));
                //将最后图片生成保存
                imagecopyresampled($posterBg, $font, 458, 555, 0, 0, 210, 98, imagesx($font), imagesy($font));

                $filePath = 'qrcodes/' . time() . mt_rand() . '.png';
                imagepng($posterBg, public_path($filePath));

                // 上传到七牛
                $upload = $this->qiNiuUpload($filePath);
                if ($upload === false) {
                    continue;
                }
                $arr[$key]['studentId'] = $val['studentId'];
                $arr[$key]['imgKey'] = $upload;
                unlink(public_path('qrcodes/qrcode' . $val['studentId'] . '.png'));
                unlink(public_path('qrcodes/font' . $val['studentId'] . '.png'));
            }
        }
        return $arr;
    }

    /**
     * 生成单个学员二维码
     * @param $studentName
     * @param $clubId
     * @param $salesId
     * @param $studentId
     * @param $mobile
     * @return string
     * @throws Exception
     */
    public function compoundOnePoster($studentName, $clubId, $salesId, $studentId, $mobile)
    {
        $this->getFontImg($studentName, $studentId);

        $prefix = env('REVERSE_DOMAIN') . '?fromUserId=' . $studentId . '&clubId=' . $clubId . '&salesId=' . $salesId . "&appUserMobile=" . $mobile;

        $this->getQrCode($prefix, 'png', '340', public_path('qrcodes/qrcode' . $studentId . '.png'));
        //俱乐部海报
        $posterBg = imagecreatefromjpeg(storage_path('poster/poster1.jpg'));
        //学员二维码
        $qrCode = imagecreatefrompng(public_path('qrcodes/qrcode' . $studentId . '.png'));
        //学员姓名图片
        $font = imagecreatefrompng(public_path('qrcodes/font' . $studentId . '.png'));
        //图片合并
        imagecopyresampled($posterBg, $qrCode, 70, 1502, 0, 0, 340, 340, imagesx($qrCode), imagesy($qrCode));
        //将最后图片生成保存
        imagecopyresampled($posterBg, $font, 458, 555, 0, 0, 210, 98, imagesx($font), imagesy($font));

        $filePath = 'qrcodes/' . time() . mt_rand() . '.png';
        imagepng($posterBg, public_path($filePath));

        // 上传到七牛
        $upload = $this->qiNiuUpload($filePath);

        unlink(public_path('qrcodes/qrcode' . $studentId . '.png'));
        unlink(public_path('qrcodes/font' . $studentId . '.png'));

        return $upload;
    }

    /**
     * 七牛上传
     * @param $filePath
     * @return string
     * @throws \Exception
     */
    public function qiNiuUpload($filePath)
    {
        $auth = new  Auth(config('qiniu.accessKey'), config('qiniu.secretKey'));
        $img = public_path($filePath);
        //获取上传token
        $upToken = $auth->uploadToken(config('qiniu.bucket'));
        $key = 'drsports/api/' . date('Ymd', time()) . '/' . date('His', time()) . mt_rand() . '.png';
        $uploadMgr = new UploadManager();
        list($ret, $err) = $uploadMgr->putFile($upToken, $key, $img);
        if ($err !== null) {
            return false;
        } else {
            unlink(public_path($filePath));
            return $key;
        }
    }

    /**
     * 获取运动名称
     * @param $sportsId
     * @return mixed|string
     */
    public function getSportsName($sportsId)
    {
        //1=篮球;2=足球;3=棒球;4=跆拳道;5=拓展;6=航模
        $sportsData = [
            '1' => '篮球',
            '2' => '足球',
            '3' => '棒球',
            '4' => '跆拳道',
            '5' => '拓展',
            '6' => '航模'
        ];

        if (!array_key_exists($sportsId, $sportsData)) return '';

        return $sportsData[$sportsId];
    }

    /**
     * 获取课程类型名称
     * @param $classTypeId
     * @return string
     */
    public function getClassTypeName($classTypeId)
    {
        $classType = ClubClassType::find($classTypeId);

        if (empty($classType)) return '';

        return $classType->name;
    }

    /**
     * 获取签到状态 (for app)
     * @param $dutyStatus
     * @return mixed|string
     */
    public function transformDutyStatusCodeForApp($dutyStatus)
    {
        //0 待出勤 1 已出勤 2未出勤 3 已取消
        //app 1:已出勤,2:待出勤,3:未出勤,4:已取消,
        $dutyData = [
            '0' => 2,
            '1' => 1,
            '2' => 3,
            '3' => 4
        ];

        if (!array_key_exists($dutyStatus, $dutyData)) return '';

        return $dutyData[$dutyStatus];
    }

    /**
     * 获取课程状态（for app）
     * @param $courseStatus
     * @param $courseDay
     * @param $courseStartTime
     * @param $courseEndTime
     * @return int
     */
    public function getCourseStatusForApp($courseStatus, $courseDay, $courseStartTime, $courseEndTime)
    {
        //课程状态,0:课程未开始,1:正在上课,2:课程已结束,3:课程已停课
        if ($courseStatus == 0) return 3;

        $now = Carbon::now()->timestamp;
        $courseStartStamp = Carbon::parse($courseDay . ' ' . $courseStartTime)->timestamp;
        $courseEndStamp = Carbon::parse($courseDay . ' ' . $courseEndTime)->timestamp;

        if ($now > $courseEndStamp) return 2;
        if ($now < $courseStartStamp) return 0;
        if ($now >= $courseStartStamp && $now <= $courseEndStamp) return 1;
    }

    /**
     * 处理发布时间
     * @param $timeStr string YYYY-mm-dd
     * @return string
     */
    public function fomatTimeStampToString($timeStr)
    {
        $parseTimeStr = Carbon::parse($timeStr);
        if ($parseTimeStr->format('Y-m-d') == Carbon::now()->format('Y-m-d')) {//今天
            return $parseTimeStr->format('H:i');
        }

        if ($parseTimeStr->format('Y-m-d') == Carbon::yesterday()->format('Y-m-d')) {//昨天
            return '昨天';
        }

        if ($parseTimeStr->format('Y') == Carbon::now()->format('Y')) {//今年       5月3日
            return $parseTimeStr->format('m月d日');
        }

        if ($parseTimeStr->format('Y') < Carbon::now()->format('Y')) {//以前      2017.02.13
            return $parseTimeStr->format('Y.m.d');
        }

        return '';
    }

    /**
     * 获取出勤状态
     * @param $dutyStatus
     * @return mixed|string
     */
    public function getDutyStatusForApp($dutyStatus)
    {
        //0=待出勤;1=出勤;2=缺勤;3=事假;4=病假;5=冻结;
        $dutyData = [
            '0' => 0,
            '1' => 1,
            '2' => 4,
            '3' => 3,
            '4' => 2,
            '5' => 6
        ];

        if (!in_array($dutyStatus, $dutyData)) return '';

        return $dutyData[$dutyStatus];
    }

    /**
     * 格式话浮点数
     * @param $float
     * @param int $defaultLen
     * @return string
     */
    public function numberFormatFloat($float, $defaultLen = 6)
    {
        return number_format($float, $defaultLen);
    }

    /**
     * 根据出生年月日获取年龄
     * @param $birthday string 2018-05-06
     * @return int
     */
    public function getAgeByBirthday($birthday)
    {
        if (empty($birthday)) return '';
        return Carbon::parse($birthday)->diffInYears();
    }

    /**
     * 根据年龄计算生日
     * @param $age
     * @return string
     */
    public function getBirthdayByAge($age)
    {
        $year = Carbon::now()->year;
        $year = $year - $age;

        $month = Carbon::now()->month;
        if ($month < 10) {
            $month = '0' . $month;
        }

        $day = Carbon::now()->day;
        if ($day < 10) {
            $day = '0' . $day;
        }

        return $year . '-' . $month . '-' . $day;
    }

    /**
     * 生成学员序列号
     * @return string
     */
    public function buildStudentSerialNo()
    {
        $serialNo = rand_char() . dec62(msectime());
        $student = ClubStudent::where('serial_no', $serialNo)->exists();

        if ($student === true) {
            $serialNo = $this->buildStudentSerialNo();
        }

        return $serialNo;
    }

    /**
     * 处理图片（主要针对老数据）
     * @param $imgUrl
     * @return string
     */
    public function handleImg($imgUrl)
    {
        if (empty($imgUrl)) return '';

        $arr = [
            'https://cdn.drsports.cn/',
            'http://cdn.drsports.cn/'
        ];

        return env('IMG_DOMAIN') . str_replace($arr, '', $imgUrl);
    }

    /**
     * 获取性别名称
     * @param $sex
     * @return mixed|string
     */
    public function getSexName($sex)
    {
        $arr = [
            '0' => '未知',
            '1' => '男',
            '2' => '女'
        ];

        return isset($arr[$sex]) ? $arr[$sex] : '';
    }

    /**
     * 获取监护人角色名称
     * @param $guardRole
     * @return mixed|string
     */
    public function getGuardRoleName($guardRole)
    {
        $arr = [
            '1' => '父亲',
            '2' => '母亲',
            '3' => '其他'
        ];

        return isset($arr[$guardRole]) ? $arr[$guardRole] : '';
    }

    /**
     * 获取缴费方案类型名称
     * @param $type
     * @return string
     */
    public function getPaymentTypeName($type)
    {
        $typeArr = [
            '1' => '常规班',
            '2' => '走训班',
            '3' => '封闭营',
            '4' => '活动',
        ];

        return isset($typeArr[$type]) ? $typeArr[$type] : '';
    }

    /**
     * 获取缴费方案标签名称
     * @param $tagId
     * @return string
     */
    public function getPaymentTagName($tagId)
    {
        $tagArr = [
            '1' => '体验缴费',
            '2' => '正常缴费',
            '3' => '活动缴费',
        ];

        return isset($tagArr[$tagId]) ? $tagArr[$tagId] : '';
    }

    /**
     * 根据班级ID获取班级下的一个教练
     * @param $classId
     * @return mixed
     */
    public function getOneCoachNameByClassId($classId)
    {
        return ClubCourseCoach::notDelete()->where('class_id',$classId)->orderBy('id')->value('coach_name');
    }

    /**
     * 根据学员ID获取绑定的app用户手机号
     * @param $stuId
     * @return array
     */
    public function getStudentBindAppUserMobiles($stuId)
    {
        $bindAppUser = ClubStudentBindApp::notDelete()
            ->where('student_id',$stuId)
            ->pluck('app_account');

        if ($bindAppUser->isEmpty()) return [];

        return collect($bindAppUser)->unique()->toArray();
    }

    /**
     * 推送课程评价通知
     * @param $paramData
     * @return mixed
     */
    public function addClubCourseCommentNotice($paramData)
    {
        $url = env('HTTPS_PREFIX').env('APP_INNER_DOMAIN').'annoucement/acceptClubCourseCommentNotice';

        return json_decode($this->curlPost($url,1,$paramData),true);
    }

    /**
     * 推送课程上课通知
     * @param $paramData
     * @return mixed
     */
    public function addClubCourseOnNotice($paramData)
    {
        $url = env('HTTPS_PREFIX').env('APP_INNER_DOMAIN').'annoucement/acceptClubCourseOnNotice';

        return json_decode($this->curlPost($url,1,$paramData),true);
    }

    /**
     * 向动博士推送短信提交记录
     * @param $paramData
     * @return mixed
     */
    public function addClubSmsPush($paramData)
    {
        $url = env('HTTPS_PREFIX').env('APP_ADMIN_INNER_DOMAIN').'club/acceptClubSmsPush';

        return json_decode($this->curlPost($url,1,$paramData),true);
    }

    /**
     * 增加一条积分兑换记录（app后台）
     * @param $clubId
     * @param $clubName
     * @return mixed
     */
    public function addOneScoreCourseForClub($clubId,$clubName)
    {
        $paramData = [
            'clubId' => $clubId,
            'clubName' => $clubName,
        ];

        $url = env('HTTPS_PREFIX').env('APP_ADMIN_INNER_DOMAIN').'score/addOneScoreCourseForClub';

        return json_decode($this->curlPost($url,1,$paramData),true);
    }

    /**
     * curl
     * @param $url
     * @param int $post
     * @param null $data
     * @return mixed
     */
    public function curlPost($url, $post = 0, $data = null)
    {
        //初始化curl
        $ch = curl_init();
        //参数设置
        $res = curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, $post);

        if ($post) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);

        curl_close($ch);
        return $result;
    }


    //测试用的
    public function test($classId)
    {
        $data = [
            'clubId' => 8
        ];

        DB::enableQueryLog();
        $model = ClubStudent::select(
            ['id', 'name', 'sales_id', 'main_class_name', 'channel_id', 'left_course_count']
        )->where(function ($query) use ($data) {
            foreach ($data as $key => $val) {
                if ($val) {
                    switch ($key) {
                        case "searchWord":
                            $query->where("id", $val)->orWhere("name", "like", "%" . $val . "%");
                            break;
                        case "classId":
                            $query->where("main_class_id", $val);
                            break;
                        case "channelId":
                            $query->where('channel_id', $val);
                            break;
                        case "salesId":
                            $query->where('sales_id', $val);
                            break;
                    }
                }
            }
        })->where(['status' => 2, 'club_id' => 8, 'is_delete' => 0])
            ->where('left_course_count','>',0)
            ->whereIn('sales_id', [25])
            /*->whereHas('studentSubscribe', function ($query) {
                $query->where('ex_status', 0);
            })*/
            ->orderBy('created_at', 'desc')
            ->paginate($data['pageSize'] ?? 20);

        dd(DB::getQueryLog());

        $result['total'] = $model->total();
        $result['data'] = $model->transform(function ($item) {
            $result = [
                'id' => $item->id,
                'name' => $item->name,
                'salesName' => ClubSales::whereKey($item->sales_id)->value('sales_name'),
                'mainClassName' => $item->main_class_name,
                'channelName' => ClubChannel::whereKey($item->channel_id)->value('channel_name'),
                'leftCourseCount' => $item->left_course_count
            ];
            return $result;
        });

        dd($result);

        DB::enableQueryLog();
        $class = ClubClass::valid()->showInApp()
            ->with([
                'club' => function ($query) {
                    return $query->notDelete()->valid();
                },
                'venue' => function ($query) {
                    return $query->notDelete()->valid()->showInApp();
                }
            ])
            ->find(1);
        dd(DB::getQueryLog());

        dd($class->toArray());

        $input = [
            'messageId' => 21,
            'status' => 2,
        ];

        $exams = ClubMessage::with(['club:id,name'])->find($input['messageId']);

        //dd($exams->toArray());
        if (empty($exams)) {
            return returnMessage('2801',config('error.sms.2801'));
        }

        $exams->status = $input['status'];
        try {
            $exams->saveOrFail();
        } catch (Exception $e) {
            return returnMessage('2802',config('error.sms.2802'));
        }

        //向动博士推送短信提交记录
        $paramData = [
            'clubId' => $exams->club_id,
            'clubMessageId' => $exams->id,
            'content' => $exams->content,
            'venueIds' => $exams->venue_ids,
            'classIds' => $exams->class_ids,
            'studentIds' => $exams->send_student_ids,
            'clubName' => $exams->club ? $exams->club->name : '',
            'templateCode' => $exams->code
        ];

        //dd($paramData);

        Log::setGroup('SmsError')->error('俱乐部推送短信-参数',[$paramData]);
        $res = Common::addClubSmsPush($paramData);

        dd($res);

        if ($res['code'] != '200') {
            $arr = [
                'code' => $res['code'],
                'msg' => $res['msg'],
                'paramData' => $paramData
            ];
            Log::setGroup('SmsError')->error('俱乐部推送短信-返回信息',[$arr]);
            return returnMessage('2803',config('error.sms.2803'));
        }

        return returnMessage('200', '推送成功');




        /***************测试***************/
        Log::setGroup('SmsError')->error('自动上课通知短信-脚本start');

        //首先找到今天需要上课的课程ID
        //$today = Carbon::now();
        $today = Carbon::parse('2018-09-29 17:00:00');
        $clubCourse = ClubCourse::where('status',1)
            ->where('day',$today->format('Y-m-d'))
            ->select('id','day','start_time')
            ->get();

        //dd($clubCourse->toArray());

        if ($clubCourse->isEmpty()) {
            Log::setGroup('SmsError')->error('自动上课通知短信-暂无今天要发送短信通知的课程');
            return;
        }

        $courseIds = [];
        $clubCourse->each(function ($item) use (&$courseIds,$today) {
            $courseStartDate = Carbon::createFromFormat('Y-m-d H:i:s',Carbon::now()
                    ->format('Y-m-d').' '.$item->start_time);

            if ($today->lt($courseStartDate)) {
                if ($today->gte($courseStartDate->subHours(4))) {
                    $courseIds[] = $item->id;
                }
            }
        });

        //dd($courseIds);

        if (empty($courseIds)) {
            Log::setGroup('SmsError')->error('自动上课通知短信-暂无符合条件发送短信通知的课程');
            return;
        }

        //判断课程上课短信是否发送过了
        $sendCacheKey = 'SendGoToClassSms';
        $existsCourseIds = Redis::lrange($sendCacheKey,0,-1);

        //Redis::del($sendCacheKey);

        //dd($existsCourseIds);
        if (! empty($existsCourseIds)) {
            Log::setGroup('SmsError')->error('自动上课通知短信-已经发送过短信的课程IDs',[$existsCourseIds]);

            $courseIds = array_diff($courseIds,$existsCourseIds);

            dd($courseIds);

            if (empty($courseIds)) {
                Log::setGroup('SmsError')->error('自动上课通知短信-今天要发送的课程已经没有了',[$existsCourseIds]);
                return;
            }
        }

        //dd(1);

        //根据要上课的courseId找出需要发送短信的
        $courseStudents = CourseSign::with(['club','student','student.binduser','course','course.venue','class','class.teachers'])
            ->whereIn('course_id',$courseIds)
            ->whereNull('sign_status')
            ->get();

        //dd($courseStudents);

        if ($courseStudents->isEmpty()) {
            Log::setGroup('SmsError')->error('自动上课通知短信-没有要上课的学员签到记录');
            return;
        }

        $students = collect($courseStudents)->pluck('student');

        if ($students->isEmpty()) {
            Log::setGroup('SmsError')->error('自动上课通知短信-没有学员数据');
            return;
        }

        //dd($students);
        $courseStudents->each(function ($item) {
            $courseTimeStr = $item->course->day.' ';
            $courseTimeStr .= Carbon::createFromFormat('Y-m-d H:i:s',$item->course->day. ' '.$item->course->start_time)
                    ->format('H:i').'-';
            $courseTimeStr .= Carbon::createFromFormat('Y-m-d H:i:s',$item->course->day. ' '.$item->course->end_time)
                ->format('H:i');
            if (!empty($item->student->guarder_mobile)) {
                $stuStr = $item->student->name;
                $clubStr = $item->club->name;

                $venueStr = $item->course->venue->address;
                $teacherId = !empty($item->class->teachers) ? $item->class->teachers[0]->teacher_id : 0;
                $teacher = $this->getTeacherContract($teacherId);

                $contactStr = !empty($teacher) ? $teacher['mobile'].' '.$teacher['name'] : '';
                $data = [
                    $stuStr,
                    $clubStr,
                    $courseTimeStr,
                    $venueStr,
                    $contactStr
                ];

                //dd($data);
                //Sms::sendSms($item->student->guarder_mobile,$data,'270690');
                Sms::sendSms('18901798661',$data,'270690');
                $arr = [
                    'stuId' => $item->student_id,
                    'guardMobile' => $item->student->guarder_mobile,
                    'courseId' => $item->course_id,
                    'push_date' => Carbon::now()->toDateTimeString()
                ];
                Log::setGroup('SmsError')->error('自动上课通知短信-发送的用户记录',[$arr]);
            }

            //dd(2);

            if ($item->student->binduser->isNotEmpty()) {
                $appUserMobiles = $item->student->binduser->pluck('app_account')->implode(',');
                $paramData = [
                    'courseDateTime' => $courseTimeStr,
                    'courseId' => $item->course->id,
                    'venueName' => $item->course->venue->name,
                    'venueAddress' => $item->course->venue->address,
                    'className' => $item->class->name,
                    //'appUserMobiles' => $appUserMobiles
                    'appUserMobiles' => '18901798661'
                ];

                //dd($paramData);

                Log::setGroup('SmsError')->error('自动上课通知短信-推送课程通知的用户记录',[$paramData]);
                $res = Common::addClubCourseOnNotice($paramData);

                if ($res['code'] != '200') {
                    $arr = [
                        'code' => $res['code'],
                        'msg' => $res['msg'],
                        'paramData' => $paramData
                    ];
                    Log::setGroup('SmsError')->error('自动上课通知短信-课程上课通知异常',[$arr]);
                }
            }

            //dd(3);
        });

        //短信发送成功以后将已经发送过的课程给记录一下，标示已发送
        Redis::rpush($sendCacheKey,$courseIds);
        Redis::expire($sendCacheKey,$this->getLeftSecondsForToday());

        $arr = [
            'courseIds' => $courseIds,
            'totalCourseIds' => Redis::lrange($sendCacheKey,0,-1),
            'keyExpireLeftSecends' => Redis::ttl($sendCacheKey)
        ];

        Log::setGroup('SmsError')->error('自动上课通知短信-运行的数据',[$arr]);

        unset($clubCourse,$courseStudents);

        Log::setGroup('SmsError')->error('自动上课通知短信-脚本end');

    }

    /**
     * 获取今天到00:00:00剩下的秒数
     */
    public function getLeftSecondsForToday()
    {
        $nextDay = date('Y-m-d',strtotime('+1 day'));

        return strtotime($nextDay) - time();
    }

    /**
     * 获取班主任联系方式(班主任通常也是销售)
     * @param $teacherId
     * @return array|string
     */
    public function getTeacherContract($teacherId)
    {
        if ($teacherId <= 0) return [];

        $teacher = ClubSales::find($teacherId);

        if (empty($teacher)) return [];

        return [
            'mobile' => $teacher->mobile,
            'name' => $teacher->sales_name
        ];
    }

    /**********************测试方法分界线************************/

    public function goTest()
    {
        /*$stuId = '276';
        $planId = $request->input('planId');
        $orderSn = $request->input('orderSn');
        $classId = $request->input('classId');
        $contractSn = $request->input('contractSn');*/

        $studentService = new StudentService();

        try {
            $returnData = $studentService->changeStuStatus($stuId,$planId,$orderSn,$classId,$contractSn);
        } catch (Exception $e) {
            return returnMessage($e->getCode(),$e->getMessage());
        }



        $input = [
            'courseId' => 345,
            'signId' => 919,
            'status' => 1
        ];

        // 签到课程
        $course = ClubCourse::notDelete()->courseOn()->find($input['courseId']);
        if (empty($course)) {
            return returnMessage('1415', config('error.class.1415'));
        }

        // 签到班级
        $class = ClubClass::valid()->with(['venue:id,name'])->find($course->class_id);
        if (empty($class)) {
            return returnMessage('1406', config('error.class.1406'));
        }

        $clubId = 8;
        $signId = $input['signId'];
        $status = $input['status'];
        $courseId = $input['courseId'];
        $classType = $class->type;
        $operateUserId = 40;

        // 签到记录
        $courseSign = ClubCourseSign::notDelete()->find($input['signId']);
        if (empty($courseSign)) {
            return returnMessage('1412', config('error.class.1412'));
        }

        // 签到学员
        $student = ClubStudent::notDelete()->find($courseSign->student_id);
        if (empty($student)) {
            return returnMessage('1610', config('error.Student.1610'));
        }

        // 冻结学员只能签出勤、冻结
        if ($student->is_freeze == 1 && !in_array($input['status'], [1, 5])) {
            return returnMessage('1413', config('error.class.1413'));
        }

        // 学员对应班级类型缴费记录
        $stuPayIdArr = ClubStudentPayment::where('club_id', $clubId)
            ->where('student_id', $courseSign->student_id)
            ->where('payment_class_type_id', $classType)
            ->pluck('id')
            ->toArray();

        // 没有与班级类型匹配的缴费
        if (count($stuPayIdArr) <= 0) {
            return returnMessage('2404', config('error.sign.2404'));
        }

        // todo 外勤签到
        if ($courseSign->is_outside == 1) {
            $subscribe = ClubStudentSubscribe::where('student_id', $student->id)
                ->where('is_delete', 0)
                ->first();

            if (!empty($subscribe)) {//预约
                if ($courseSign->is_used == 0) {// 首次签到
                    // 签到使用的课程券
                    $ticket = Classes::getSubscribeExTicket($clubId, $student->id);

                    // 课程券不足
                    if (empty($ticket)) {
                        return returnMessage('1423', config('error.class.1423'));
                    }

                    try {
                        $this->addOrCancelTryRewardToRecommendStudent($student,$signId);
                        $this->firstSignForSubscribeWithOutDuty($signId, $status, $class, $courseId, $ticket, $courseSign, $student, $clubId, $operateUserId, $course);
                        Classes::recordStuCourseSign($clubId, $signId, $student->id, $status, $operateUserId);
                    } catch (Exception $e) {
                        return returnMessage($e->getCode(),$e->getMessage());
                    }
                } else {//非首次签到
                    try {
                        $this->addOrCancelTryRewardToRecommendStudent($student,$signId);
                        $this->notFirstSignForSubscribeWithOutDuty($courseSign, $student, $clubId, $status, $operateUserId, $course);
                        Classes::recordStuCourseSign($clubId, $signId, $student->id, $status, $operateUserId);
                    } catch (Exception $e) {
                        return returnMessage($e->getCode(),$e->getMessage());
                    }
                }
            } else {//非预约
                if ($courseSign->is_used == 0) {// 首次签到
                    // 签到使用的课程券
                    $ticket = Classes::getStudentSignTicket($clubId, $student->id, $classType);

                    // 课程券不足
                    if (empty($ticket)) {
                        return returnMessage('1423', config('error.class.1423'));
                    }

                    $salesId = ClubStudentPayment::where('student_id', $student->id)->where('id', $ticket->payment_id)->value('sales_id');

                    try {
                        DB::transaction(function () use ($signId,$status,$class,$course,$ticket,$courseSign,$student,$clubId,$salesId,$operateUserId) {
                            $this->addOrCancelTryRewardToRecommendStudent($student,$signId);
                            $this->firstSignWithOutDuty($signId,$status,$class,$course,$ticket,$courseSign,$student,$clubId,$salesId,$operateUserId);
                            Classes::recordStuCourseSign($clubId, $signId, $student->id, $status, $operateUserId);
                        });
                    } catch (Exception $e) {
                        return returnMessage($e->getCode(),$e->getMessage());
                    }
                } else {//非首次签到
                    try {
                        DB::transaction(function () use ($signId,$status,$class,$course,$courseSign,$student,$clubId,$operateUserId) {
                            $this->addOrCancelTryRewardToRecommendStudent($student,$signId);
                            $this->notFirstSignWithOutDuty($student,$courseSign, $course, $status, $signId, $class, $clubId, $operateUserId);
                            Classes::recordStuCourseSign($clubId, $signId, $student->id, $status, $operateUserId);
                        });
                    } catch (Exception $e) {
                        return returnMessage($e->getCode(),$e->getMessage());
                    }
                }
            }

            return returnMessage('200', '');
        } elseif ($courseSign->is_subscribe == 1) {  // todo 预约签到
            $subscribe = ClubStudentSubscribe::where('student_id', $student->id)
                ->where('sign_id',$courseSign->id)
                ->where('is_delete', 0)
                ->first();

            if (!in_array($status, [1, 2])) {//预约体验签到只能签出勤、缺勤
                return returnMessage('1419', config('error.class.1419'));
            }

            if ($courseSign->is_used == 0) {// 首次签到
                // 签到使用的课程券
                $ticket = Classes::getSubscribeExTicket($clubId, $student->id);

                // 课程券不足
                if (empty($ticket)) {
                    return returnMessage('1423', config('error.class.1423'));
                }

                try {
                    DB::transaction(function () use ($student,$signId,$status,$class,$courseId,$ticket,$courseSign,$clubId,$operateUserId,$course) {
                        $this->addOrCancelTryRewardToRecommendStudent($student,$signId);
                        $this->firstSignForSubscribeWithNotOutDuty($signId, $status, $class, $courseId, $ticket, $courseSign, $student, $clubId, $operateUserId, $course);
                        Classes::recordStuCourseSign($clubId, $signId, $student->id, $status, $operateUserId);
                    });
                } catch (Exception $e) {
                    return returnMessage($e->getCode(),$e->getMessage());
                }
            } else {
                try {
                    DB::transaction(function () use ($student,$signId,$courseSign,$clubId,$status,$operateUserId,$course) {
                        $this->addOrCancelTryRewardToRecommendStudent($student,$signId);
                        $this->notFirstSignForSubscribeWithNotOutDuty($courseSign, $student, $clubId, $status, $operateUserId, $course);
                        Classes::recordStuCourseSign($clubId, $signId, $student->id, $status, $operateUserId);
                    });
                } catch (Exception $e) {
                    return returnMessage($e->getCode(),$e->getMessage());
                }
            }

            return returnMessage('200', '');
        } else {  // todo 正式签到
            if ($courseSign->is_used == 0) {// 首次签到
                // 签到使用的课程券
                $ticket = Classes::getStudentSignTicket($clubId, $student->id, $classType);

                // 课程券不足
                if (empty($ticket)) {
                    return returnMessage('1423', config('error.class.1423'));
                }

                $salesId = ClubStudentPayment::where('student_id', $student->id)->where('id', $ticket->payment_id)->value('sales_id');

                try {
                    $this->addOrCancelTryRewardToRecommendStudent($student,$signId);
                    $this->firstSignWithNotOutDuty($signId, $status, $class, $course, $ticket, $courseSign, $student, $clubId, $salesId,$operateUserId);
                    Classes::recordStuCourseSign($clubId, $signId, $student->id, $status, $operateUserId);
                } catch (Exception $e) {
                    return returnMessage($e->getCode(),$e->getMessage());
                }
            } else {//非首次签到
                try {
                    $this->addOrCancelTryRewardToRecommendStudent($student,$signId);
                    $this->notFirstSignWithNotOutDuty($student, $courseSign, $course, $status, $signId, $class, $clubId, $stuPayIdArr,$operateUserId);
                    Classes::recordStuCourseSign($clubId, $signId, $student->id, $status, $operateUserId);
                } catch (Exception $e) {
                    return returnMessage($e->getCode(),$e->getMessage());
                }
            }

            //签到成功，发送一个课程评价推送
            $appUserMobiles = Common::getStudentBindAppUserMobiles($student->id);
            $paramData = [
                'classId' => $class->id,
                'className' => $class->name,
                'venueName' => $class->venue ? $class->venue->name : '',
                'courseDate' => $course->day,
                'appUserMobiles' => $appUserMobiles ? implode(',',$appUserMobiles) : ''
            ];

            $res = Common::addClubCourseCommentNotice($paramData);

            if ($res['code'] != '200') {
                $arr = [
                    'code' => $res['code'],
                    'msg' => $res['msg'],
                    'paramData' => $paramData
                ];
                Log::setGroup('StuSignError')->error('推送课程通知有误',[$arr]);
            }

            return returnMessage('200', '');
        }
    }

    /**
     * 给推荐学员发送获取取消体验奖励
     * @param ClubStudent $student
     * @param $signId
     * @throws Exception
     * @throws \Throwable
     */
    public function addOrCancelTryRewardToRecommendStudent(ClubStudent $student,$signId)
    {
        Log::setGroup('RecommendError')->error('发送奖励开始');

        //不是推荐的学员，不走奖励逻辑
        if ($student->from_stu_id == 0) {
            Log::setGroup('RecommendError')->error('推广奖励记录异常-没有推荐学员');
            return;
        }

        //判断是否首次签到
        $courseSign = ClubCourseSign::notDelete()
            ->where('student_id',$student->id)
            ->where('is_subscribe',1)
            ->first();

        //该学员没有进行首次体验出勤签到，不走奖励逻辑
        if (empty($courseSign) || $courseSign->id != $signId) {
            Log::setGroup('RecommendError')->error('推广奖励记录异常-签到不存在或者签到id不匹配');
            return;
        }

        $reserveRecords = ClubRecommendReserveRecord::notDelete()
            ->where('new_stu_id',$student->id)
            ->where('stu_id',$student->from_stu_id)
            ->first();

        if (empty($reserveRecords)) {
            Log::setGroup('RecommendError')->error('推广奖励记录异常-二维码预约没有生成奖励记录',['stuId' => $student->id,'newStuId' => $student->from_stu_id]);
            return;
        }

        //体验奖励记录
        $rewardRecords = ClubRecommendRewardRecord::where('recommend_id',$reserveRecords->id)
            ->where('event_type',1)
            ->first();

        if (empty($rewardRecords)) {
            Log::setGroup('RecommendError')->error('推广奖励记录异常-体验奖励记录不存在',['recommendId' => $reserveRecords->id]);
            return;
        }

        $tryRewardNum = $rewardRecords->reward_course_num;  //体验奖励课时数

        //首次出勤签到
        if ($courseSign->is_used == 0) {
            if ($courseSign->sign_status == 1) {//出勤发奖励
                if ($tryRewardNum > 0) {
                    try {
                        $this->addPaymentRecordsAndTickets($tryRewardNum,$student,$rewardRecords->club_id,$rewardRecords->id);

                        $this->addCourseCountToStudent($student->from_stu_id,$tryRewardNum);
                        $rewardRecords->settle_status = 2;
                        $rewardRecords->saveOrFail();

                        $reserveRecords->recommend_status = 2;
                        $reserveRecords->saveOrFail();
                    } catch (Exception $e) {
                        Log::setGroup('RecommendError')->error('推广奖励记录异常-数据操作有异常',['msg' => $e->getMessage()]);
                        throw new Exception($e->getMessage(),$e->getCode());
                    }

                    Log::setGroup('RecommendError')->error('推广奖励记录（首次出勤签到）-发放奖励成功');
                }
            }

            return;
        }

        //更改出勤签到
        if ($courseSign->sign_status == 1) {//出勤
            //防止更改签到两次都为出勤
            if ($reserveRecords->recommend_status == 2 && $rewardRecords->settle_status == 2) return; //状态为2表示第一次的签到状态为出勤了，奖励已经发放，不能再次发放

            if ($reserveRecords->recommend_status == 1 || $reserveRecords->recommend_status == 3) {
                if ($rewardRecords->settle_status == 2) {
                    Log::setGroup('RecommendError')->error('推广奖励记录异常-奖励已经结算了',['stuId' => $student->id,'newStuId' => $student->from_stu_id]);
                    return;
                }

                //给推荐学员发放奖励
                if ($tryRewardNum > 0) {
                    try {
                        $this->addPaymentRecordsAndTickets($tryRewardNum,$student,$rewardRecords->club_id,$reserveRecords->id);
                        $student->left_course_count = $student->left_course_count + $tryRewardNum;
                        $student->saveOrFail();

                        $rewardRecords->settle_status = 2;
                        $rewardRecords->saveOrFail();
                    } catch (Exception $e) {
                        Log::setGroup('RecommendError')->error('推广奖励记录异常（更改出勤签到）-发送奖励操作异常',['msg' => $e->getMessage()]);
                        throw new Exception($e->getMessage(),$e->getCode());
                    }

                    Log::setGroup('RecommendError')->error('推广奖励记录（更改出勤签到）-发放奖励成功');
                }

                if ($reserveRecords->recommend_status == 1) {
                    try {
                        $reserveRecords->recommend_status = 2;
                        $reserveRecords->saveOrFail();
                    } catch (Exception $e) {
                        Log::setGroup('RecommendError')->error('推广奖励记录异常（更改出勤签到）-更改结算状态操作异常',['msg' => $e->getMessage()]);
                        throw new Exception($e->getMessage(),$e->getCode());
                    }
                    return;
                }
            }
            return;
        }

        //第一次出勤，改为其他状态
        if ($courseSign->sign_status != 1 && $reserveRecords->recommend_status == 2 && $rewardRecords->settle_status == 2) {
            //追回奖励
            try {
                $this->cancelTryRewardToRecommendStudent($student,$reserveRecords,$rewardRecords);
            } catch (Exception $e) {
                throw new Exception($e->getMessage(),$e->getCode());
            }

            Log::setGroup('RecommendError')->error('推广奖励记录异常（更改出勤签到）-追回奖励成功');
        }
    }

    public function addPaymentRecordsAndTickets($tryRewardNum,$student,$clubId,$rewardRecordId)
    {
        if ($tryRewardNum <= 0) return;

        //查找是否有活动缴费方案
        $freePayment = ClubPayment::valid()->where('club_id',$clubId)
            ->where('tag',3)
            ->where('is_default', 1)
            ->first();

        if (empty($freePayment)) {
            Log::setGroup('RecommendError')->error('推广奖励记录异常-找不到二维码活动缴费方案',['clubId' => $clubId]);
            throw new Exception(config('error.Payment.2105'),'2105');
        }

        if ($student->sales_id > 0) {
            $sales = ClubSales::notDelete()->find($student->sales_id);
        } else {
            $sales = Student::getDefaultStuData($clubId, 3);
        }

        if (empty($sales)) {
            Log::setGroup('RecommendError')->error('推广奖励记录异常-销售员不存在',['salesId' => $student->sales_id]);
            throw new Exception(config('error.Student.1683'),'1683');
        }

        try {
            for ($i=0;$i<$tryRewardNum;$i++) {
                $stuPayment = $this->addPaymentsForStudent($student->from_stu_id,$clubId,$freePayment,$sales,$rewardRecordId);
                $this->addTicketsForStudent($stuPayment,$student->from_stu_id,$clubId,$freePayment);
            }
        } catch (Exception $e) {
            Log::setGroup('RecommendError')->error('推广奖励记录异常-给推荐学员增加缴费方案和券操作异常',['msg' => $e->getMessage()]);
            throw new Exception($e->getMessage(),$e->getCode());
        }
    }

    /**
     * 给学员增加体验缴费方案
     * @param $stuId
     * @param $clubId
     * @param ClubPayment $freePayment
     * @param ClubSales $sales
     * @param $rewardRecordId
     * @return ClubStudentPayment
     * @throws \Throwable
     */
    public function addPaymentsForStudent($stuId,$clubId,ClubPayment $freePayment,ClubSales $sales,$rewardRecordId)
    {
        $stuPayment = new ClubStudentPayment();
        $stuPayment->student_id = $stuId;
        $stuPayment->club_id = $clubId;
        $stuPayment->payment_id = $freePayment->id;
        $stuPayment->payment_name = $freePayment->name;
        $stuPayment->payment_tag_id = $freePayment->tag;
        $stuPayment->payment_class_type_id = $freePayment->type;
        $stuPayment->course_count = $freePayment->course_count;
        $stuPayment->pay_fee = $freePayment->price;
        $stuPayment->equipment_issend = 0;
        $stuPayment->payment_date = Carbon::now()->toDateString();
        $stuPayment->channel_type = 4;
        $stuPayment->expire_date = Carbon::now()->addCentury(1)->toDateString();
        $stuPayment->sales_id = $sales->id;
        $stuPayment->sales_dept_id = $sales->sales_dept_id;
        $stuPayment->reserve_record_id = $rewardRecordId;
        $stuPayment->saveOrFail();

        return $stuPayment;
    }

    /**
     * 给学员添加课程券
     * @param ClubStudentPayment $stuPayment
     * @param $stuId
     * @param $clubId
     * @param $freePayment
     * @throws \Throwable
     */
    public function addTicketsForStudent($stuPayment,$stuId,$clubId,$freePayment)
    {
        $tickets = new ClubCourseTickets();
        $tickets->payment_id = $stuPayment->id;
        $tickets->club_id = $clubId;
        $tickets->student_id = $stuId;
        $tickets->expired_date = Carbon::now()->addMonth($freePayment->use_to_date)->toDateString();
        $tickets->status = 2;
        $tickets->reward_type = 1;
        $tickets->saveOrFail();
    }

    /**
     * 追回赠送的体验奖励课时
     * @param ClubStudent $student
     * @param ClubRecommendReserveRecord $reserveRecords
     * @param ClubRecommendRewardRecord $rewardRecords
     * @throws Exception
     * @throws \Throwable
     */
    public function cancelTryRewardToRecommendStudent(ClubStudent $student,ClubRecommendReserveRecord $reserveRecords,ClubRecommendRewardRecord $rewardRecords)
    {
        //奖励处于已发放，执行追回逻辑
        if ($reserveRecords->recommend_status == 2 && $rewardRecords->settle_status == 2) {
            try {
                $reserveRecords->recommend_status = 1;
                $reserveRecords->saveOrFail();

                $rewardRecords->settle_status = 1;
                $rewardRecords->saveOrFail();

                $stuPaymentIds = ClubStudentPayment::notDelete()
                    ->where('reserve_record_id',$rewardRecords->id)
                    ->pluck('payment_id');

                if ($stuPaymentIds->isEmpty()) return;

                $stuTickets = ClubCourseTickets::notDelete()
                    ->whereIn('payment_id',$stuPaymentIds)
                    ->where('status',2)
                    ->get();

                $giveBackCount = collect($stuTickets)->count();

                if ($giveBackCount > 0) {
                    $student->left_course_count = $student->left_course_count - $giveBackCount;
                    $student->saveOrFail();

                    ClubCourseTickets::notDelete()
                        ->whereIn('payment_id',$stuPaymentIds)
                        ->where('status',2)
                        ->update(['is_delete' => 1]);

                    ClubStudentPayment::notDelete()
                        ->where('reserve_record_id',$rewardRecords->id)
                        ->update(['is_delete' => 1]);
                }
            } catch (Exception $e) {
                Log::setGroup('RecommendError')->error('推广奖励记录异常（更改出勤签到）-追回奖励操作失败',['msg' => $e->getMessage()]);
                throw new Exception($e->getMessage(),$e->getCode());
            }
        }
    }

    /**
     * 预约非外勤第一次签到
     * @param $signId
     * @param $status
     * @param $class
     * @param $courseId
     * @param $ticket
     * @param $courseSign
     * @param $student
     * @param $clubId
     * @param $operateUserId
     * @param ClubCourse $course
     * @return array
     */
    public function firstSignForSubscribeWithNotOutDuty($signId, $status, $class, $courseId, $ticket, $courseSign, $student, $clubId, $operateUserId, ClubCourse $course)
    {
        try {
            DB::transaction(function () use ($signId,$status,$class,$courseId,$ticket,$courseSign,$student,$clubId,$operateUserId,$course) {
                $this->changeCourseSign($signId,$status,$class,$courseId,$ticket,$courseSign,$student,$operateUserId,$course);
            });
        } catch (Exception $e) {
            return returnMessage($e->getCode(),$e->getMessage());
        }
    }

    /**
     * 预约非外勤非再次签到（更改签到）
     * @param ClubCourseSign $courseSign
     * @param ClubStudent $student
     * @param $clubId
     * @param $status
     * @param $operateUserId
     * @param ClubCourse $course
     * @throws Exception
     */
    public function notFirstSignForSubscribeWithNotOutDuty(ClubCourseSign $courseSign,ClubStudent $student,$clubId,$status,$operateUserId, ClubCourse $course)
    {
        try {
            DB::transaction(function () use ($courseSign,$student,$clubId,$status,$operateUserId,$course) {
                // 更新签到数据
                $courseSign->sign_status = $status;
                $courseSign->sign_date = $course->day;
                $courseSign->operate_user_id = $operateUserId;
                $courseSign->saveOrFail();

                // 缺勤算未体验
                if ($courseSign->sign_status == 1 && $status == 2) {
                    $student->ex_status = 1;
                    $student->saveOrFail();
                }
            });
        } catch (Exception $e) {
            throw new Exception($e->getCode(),$e->getMessage());
        }
    }

    /**
     * 非预约非外勤首次签到
     * @param $signId
     * @param $status
     * @param ClubClass $class
     * @param ClubCourse $course
     * @param ClubCourseTickets $ticket
     * @param ClubCourseSign $courseSign
     * @param ClubStudent $student
     * @param $clubId
     * @param $salesId
     * @param $operateUserId
     * @throws Exception
     */
    public function firstSignWithNotOutDuty($signId, $status, ClubClass $class, ClubCourse $course, ClubCourseTickets $ticket, ClubCourseSign $courseSign, ClubStudent $student, $clubId, $salesId, $operateUserId)
    {
        try {
            // 出勤、缺勤、事假扣课程券、课时、销课
            DB::transaction(function () use ($ticket, $clubId, $student, $courseSign,$status, $signId, $class, $course,$salesId,$operateUserId) {
                if (in_array($status, [1, 2, 3])) {
                    // 更新课程券
                    $ticket->course_id = $course->id;
                    $ticket->class_id = $class->id;
                    $ticket->sign_id = $signId;
                    $ticket->class_type_id = $class->type;
                    $ticket->status = 1;
                    $ticket->saveOrFail();

                    // 减少课时数
                    $student->left_course_count = $student->left_course_count - 1;
                    if ($status == 1) {
                        $student->is_freeze == 0;
                    }
                    $student->saveOrFail();

                    // 添加销课收入
                    $income = new ClubIncomeSnapshot();
                    $income->ticket_id = $ticket->id;
                    $income->student_id = $student->id;
                    $income->sign_id = $signId;
                    $income->course_id = $course->id;
                    $income->class_id = $class->id;
                    $income->class_type_id = $class->type;
                    $income->venue_id = $class->venue_id;
                    $income->club_id = $clubId;
                    $income->course_date = $course->day;
                    $income->payment_id = $ticket->payment_id;
                    $income->money = $ticket->unit_price;
                    $income->sales_id = $salesId ? $salesId : 0;
                    $income->saveOrFail();
                }

                // 更新签到状态
                $courseSign->class_id = $class->id;
                $courseSign->course_id = $course->id;
                $courseSign->sign_status = $status;
                $courseSign->sign_date = $course->day;
                $courseSign->is_used = 1;
                $courseSign->class_type_id = $class->type;
                $courseSign->operate_user_id = $operateUserId;
                $courseSign->saveOrFail();
            });
        } catch (Exception $e) {
            throw new Exception(config('error.sign.2402'),'2402');
        }
    }

    /**
     * 非预约非外勤再次签到
     * @param $student
     * @param ClubCourseSign $courseSign
     * @param ClubCourse $course
     * @param $status
     * @param $signId
     * @param $class
     * @param $clubId
     * @param $stuPayIdArr
     * @param $operateUserId
     * @throws Exception
     */
    public function notFirstSignWithNotOutDuty($student, ClubCourseSign $courseSign, ClubCourse $course, $status, $signId, $class, $clubId, $stuPayIdArr, $operateUserId)
    {
        //1:出勤、2:缺勤、3:事假扣课程券、课时、销课
        //4:病假、5:Pass、6:AutoPass、7冻结时，返还课时数、课程券、销课

        try {
            DB::transaction(function () use ($courseSign, $course, $status, $signId, $student, $class, $stuPayIdArr, $clubId, $operateUserId) {
                // 更改状态为病假、Pass、AutoPass、冻结时，返还课时数、课程券、销课
                if (in_array($courseSign->sign_status,[4, 5, 6, 7]) && in_array($status, [1,2,3])) {
                    $ticket = Classes::getStudentSignTicket($clubId, $student->id, $class->type);
                    $salesId = ClubStudentPayment::where('id', $ticket->payment_id)->value('sales_id');

                    // 更新课程券
                    $ticket->course_id = $course->id;
                    $ticket->class_id = $class->id;
                    $ticket->sign_id = $signId;
                    $ticket->class_type_id = $class->type;
                    $ticket->status = 1;
                    $ticket->saveOrFail();

                    // 减少课时数
                    $student->left_course_count = $student->left_course_count - 1;
                    if ($status == 1) {
                        $student->is_freeze == 0;
                    }
                    $student->saveOrFail();

                    // 添加销课收入
                    $income = new ClubIncomeSnapshot();
                    $income->ticket_id = $ticket->id;
                    $income->student_id = $student->id;
                    $income->sign_id = $signId;
                    $income->course_id = $course->id;
                    $income->class_id = $class->id;
                    $income->class_type_id = $class->type;
                    $income->venue_id = $class->venue_id;
                    $income->club_id = $clubId;
                    $income->course_date = $course->day;
                    $income->payment_id = $ticket->payment_id;
                    $income->money = $ticket->unit_price;
                    $income->sales_id = $salesId ? $salesId : 0;
                    $income->saveOrFail();
                } elseif (in_array($courseSign->sign_status,[1,2,3]) && in_array($status, [4,5,6,7])) {
                    $ticket = ClubCourseTickets::where('sign_id', $signId)->first();
                    $salesId = ClubStudentPayment::where('id', $ticket->payment_id)->value('sales_id');

                    // 去除销课收入
                    ClubIncomeSnapshot::where('sign_id', $signId)
                        ->where('student_id', $student->id)
                        ->where('is_delete', 0)
                        ->update(['is_delete' => 1]);

                    // 返还课程券
                    ClubCourseTickets::where('sign_id', $signId)
                        ->where('student_id', $student->id)
                        ->where('course_id', $course->id)
                        ->where('class_id', $class->id)
                        ->update([
                            'course_id' => 0,
                            'class_id' => 0,
                            'sign_id' => 0,
                            'status' => 2
                        ]);

                    // 增加课时数
                    ClubStudent::where('id', $student->id)->increment('left_course_count');
                } else {
                    //暂时没有其他的，如果有在加逻辑
                }

                // 更新签到状态
                $courseSign->sign_status = $status;
                $courseSign->sign_date = $course->day;
                $courseSign->operate_user_id = $operateUserId;
                $courseSign->saveOrFail();
            });
        } catch (Exception $e) {
            throw new Exception(config('error.sign.2402'),'2402');
        }
    }

    /**
     * 更改课程签到
     * @param $signId
     * @param $status
     * @param ClubClass $class
     * @param $courseId
     * @param ClubCourseTickets $ticket
     * @param ClubCourseSign $courseSign
     * @param ClubStudent $student
     * @param $operateUserId
     * @param $course
     * @throws \Throwable
     */
    public function changeCourseSign($signId,$status,ClubClass $class,$courseId,ClubCourseTickets $ticket,ClubCourseSign $courseSign,ClubStudent $student, $operateUserId, $course)
    {
        // 更新签到状态
        $courseSign->class_id = $class->id;
        $courseSign->course_id = $courseId;
        $courseSign->sign_status = $status;
        $courseSign->sign_date = $course->day;
        $courseSign->is_used = 1;
        $courseSign->class_type_id = $class->type;
        $courseSign->operate_user_id = $operateUserId;
        $courseSign->saveOrFail();

        // 更新课程券
        $ticket->course_id = $courseId;
        $ticket->class_id = $class->id;
        $ticket->sign_id = $signId;
        $ticket->class_type_id = $class->type;
        $ticket->status = 1;
        $ticket->saveOrFail();

        // 更新课时数
        ClubStudent::where('id', $student->id)->decrement('left_course_count');
        if ($status == 1) {
            $student->ex_status = 2;
            $student->saveOrFail();
        }
    }

    /**
     * 预约外勤第一次签到
     * @param $signId
     * @param $status
     * @param $class
     * @param $courseId
     * @param $ticket
     * @param $courseSign
     * @param $student
     * @param $clubId
     * @param $operateUserId
     * @param ClubCourse $course
     * @throws Exception
     */
    public function firstSignForSubscribeWithOutDuty($signId,$status,$class,$courseId,$ticket,$courseSign,$student,$clubId,$operateUserId,ClubCourse $course)
    {
        try {
            DB::transaction(function () use ($signId,$status,$class,$courseId,$ticket,$courseSign,$student,$clubId,$operateUserId,$course) {
                $this->changeCourseSign($signId,$status,$class,$courseId,$ticket,$courseSign,$student,$operateUserId,$course);
            });
        } catch (Exception $e) {
            throw new Exception($e->getMessage(),$e->getCode());
        }
    }

    /**
     * 预约外勤再次签到
     * @param ClubCourseSign $courseSign
     * @param ClubStudent $student
     * @param $clubId
     * @param $status
     * @param $operateUserId
     * @param $course
     * @throws Exception
     */
    public function notFirstSignForSubscribeWithOutDuty(ClubCourseSign $courseSign,ClubStudent $student,$clubId,$status,$operateUserId, $course)
    {
        try {
            DB::transaction(function () use ($courseSign,$student,$clubId,$status,$operateUserId, $course) {
                // 更新签到数据
                $courseSign->sign_status = $status;
                $courseSign->sign_date = $course->day;
                $courseSign->operate_user_id = $operateUserId;
                $courseSign->saveOrFail();

                // 缺勤算未体验
                if ($courseSign->sign_status == 1 && $status == 2) {
                    $student->ex_status == 1;
                    $student->saveOrFail();
                }
            });
        } catch (Exception $e) {
            throw new Exception($e->getCode(),$e->getMessage());
        }
    }

    /**
     * 非预约外勤首次签到
     * @param $signId
     * @param $status
     * @param ClubClass $class
     * @param ClubCourse $course
     * @param ClubCourseTickets $ticket
     * @param ClubCourseSign $courseSign
     * @param ClubStudent $student
     * @param $clubId
     * @param $salesId
     * @param $operateUserId
     * @throws Exception
     */
    public function firstSignWithOutDuty($signId,$status,ClubClass $class,ClubCourse $course,ClubCourseTickets $ticket,ClubCourseSign $courseSign,ClubStudent $student,$clubId,$salesId,$operateUserId)
    {
        try {
            // 出勤、缺勤、事假扣课程券、课时、销课
            DB::transaction(function () use ($ticket, $clubId, $student, $class, $courseSign,$status, $signId, $course,$salesId,$operateUserId) {
                if (in_array($status, [1, 2, 3])) {
                    // 减少课程券
                    $ticket->course_id = $course->id;
                    $ticket->class_id = $class->id;
                    $ticket->sign_id = $signId;
                    $ticket->class_type_id = $class->type;
                    $ticket->status = 1;
                    $ticket->saveOrFail();

                    // 减少课时数
                    $student->left_course_count = $student->left_course_count - 1;
                    if ($status == 1) {
                        $student->is_freeze == 0;
                    }
                    $student->saveOrFail();

                    // 增加销课收入
                    $income = new ClubIncomeSnapshot();
                    $income->ticket_id = $ticket->id;
                    $income->student_id = $student->id;
                    $income->sign_id = $signId;
                    $income->course_id = $course->id;
                    $income->class_id = $class->id;
                    $income->class_type_id = $class->type;
                    $income->venue_id = $class->venue_id;
                    $income->club_id = $clubId;
                    $income->course_date = $course->day;
                    $income->payment_id = $ticket->payment_id;
                    $income->money = $ticket->unit_price;
                    $income->sales_id = $salesId ? $salesId : 0;
                    $income->saveOrFail();
                }

                // 更新签到状态
                $courseSign->class_id = $class->id;
                $courseSign->course_id = $course->id;
                $courseSign->sign_status = $status;
                $courseSign->sign_date = $course->day;
                $courseSign->is_used = 1;
                $courseSign->class_type_id = $class->type;
                $courseSign->operate_user_id = $operateUserId;
                $courseSign->saveOrFail();
            });
        } catch (Exception $e) {
            throw new Exception(config('error.sign.2402'),'2402');
        }
    }

    /**
     * 非预约外勤再次签到
     * @param $student
     * @param ClubCourseSign $courseSign
     * @param ClubCourse $course
     * @param $status
     * @param $signId
     * @param ClubClass $class
     * @param $clubId
     * @param $operateUserId
     * @throws Exception
     */
    public function notFirstSignWithOutDuty($student, ClubCourseSign $courseSign, ClubCourse $course, $status, $signId, ClubClass $class, $clubId,$operateUserId)
    {
        //1:出勤、2:缺勤、3:事假扣课程券、课时、销课
        //4:病假、5:Pass、6:AutoPass、7冻结时，返还课时数、课程券、销课

        try {
            DB::transaction(function () use ($courseSign, $course, $status, $signId, $student, $class,$clubId,$operateUserId) {
                // 更改状态为病假、Pass、AutoPass、冻结时，返还课时数、课程券、销课
                if (in_array($courseSign->sign_status,[4, 5, 6, 7]) && in_array($status, [1,2,3])) {
                    $ticket = Classes::getStudentSignTicket($clubId, $student->id, $class->type);
                    $salesId = ClubStudentPayment::where('id', $ticket->payment_id)->value('sales_id');

                    // 更新课程券
                    $ticket->course_id = $course->id;
                    $ticket->class_id = $class->id;
                    $ticket->sign_id = $signId;
                    $ticket->class_type_id = $class->type;
                    $ticket->status = 1;
                    $ticket->saveOrFail();

                    // 更新课时数
                    $student->left_course_count = $student->left_course_count - 1;
                    if ($status == 1) {
                        $student->is_freeze == 0;
                    }
                    $student->saveOrFail();

                    // 添加销课收入
                    $income = new ClubIncomeSnapshot();
                    $income->ticket_id = $ticket->id;
                    $income->student_id = $student->id;
                    $income->sign_id = $signId;
                    $income->course_id = $course->id;
                    $income->class_id = $class->id;
                    $income->class_type_id = $class->type;
                    $income->venue_id = $class->venue_id;
                    $income->club_id = $clubId;
                    $income->course_date = $course->day;
                    $income->payment_id = $ticket->payment_id;
                    $income->money = $ticket->unit_price;
                    $income->sales_id = $salesId ? $salesId : 0;
                    $income->saveOrFail();
                } elseif (in_array($courseSign->sign_status,[1,2,3]) && in_array($status, [4,5,6,7])) {
                    $ticket = ClubCourseTickets::where('sign_id', $signId)->first();
                    $salesId = ClubStudentPayment::where('id', $ticket->payment_id)->value('sales_id');

                    // 去除销课收入
                    ClubIncomeSnapshot::where('sign_id', $signId)
                        ->where('student_id', $student->id)
                        ->where('is_delete', 0)
                        ->update(['is_delete' => 1]);

                    // 返还课程券
                    ClubCourseTickets::where('sign_id', $signId)
                        ->where('student_id', $student->id)
                        ->where('course_id', $course->id)
                        ->where('class_id', $class->id)
                        ->update([
                            'course_id' => 0,
                            'class_id' => 0,
                            'sign_id' => 0,
                            'status' => 2
                        ]);

                    // 返还课时数
                    ClubStudent::where('id', $student->id)->increment('left_course_count');
                } else {
                    //暂时没有其他的，如果有在加逻辑
                }

                // 更新签到状态
                $courseSign->sign_status = $status;
                $courseSign->sign_date = $course->day;
                $courseSign->operate_user_id = $operateUserId;
                $courseSign->saveOrFail();
            });
        } catch (Exception $e) {
            throw new Exception(config('error.sign.2402'),'2402');
        }
    }

    /**
     * 给推荐的学员增加课时数
     * @param $stuId
     * @param $tryRewardNum
     * @throws Exception
     */
    public function addCourseCountToStudent($stuId,$tryRewardNum)
    {
        $clubStudent = ClubStudent::notDelete()->find($stuId);
        if (empty($clubStudent)) return;

        $clubStudent->left_course_count = $clubStudent->left_course_count + $tryRewardNum;

        if ($clubStudent->status == 3) {//公海库学员则需要将状态变为非正式学员
            $defaultVenue = Student::getDefaultStuData($clubStudent->club_id,1);
            $defaultClass = Student::getDefaultStuData($clubStudent->club_id,2);
            $defaultSales = Student::getDefaultStuData($clubStudent->club_id,3);

            if (empty($defaultVenue) || empty($defaultClass) || empty($defaultSales)) {
                throw new Exception(config('error.common.1013'),'1013');
            }

            $clubStudent->venue_id = $defaultVenue->id;
            $clubStudent->sales_id = $defaultSales->id;
            $clubStudent->sales_name = $defaultSales->sales_name;
            $clubStudent->main_class_id = $defaultClass->id;
            $clubStudent->main_class_name = $defaultClass->name;

            $clubStudent->status = 2;
        }

        $clubStudent->saveOrFail();
    }

}
