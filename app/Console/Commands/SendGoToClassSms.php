<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Model\ClubCourseSign\ClubCourseSign;
use App\Model\ClubCourse\ClubCourse;
use App\Model\ClubStudent\ClubStudent;
use Carbon\Carbon;
use App\Facades\Util\Sms;
use Illuminate\Support\Facades\Redis;
use App\Model\ClubSales\ClubSales;
use App\Facades\Util\Log;
use App\Facades\Util\Common;

class SendGoToClassSms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SendGoToClassSms';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'send Sms to Student for class';

    /**
     * @var ClubCourseSign
     */
    private $clubCourseSign;

    /**
     * @var ClubCourse
     */
    private $clubCourse;

    /**
     * @var ClubStudent
     */
    private $clubStudent;

    private $clubSales;

    /**
     * Create a new command instance.
     *
     * SendGoToClassSms constructor.
     * @param ClubCourseSign $clubCourseSign
     * @param ClubCourse $clubCourse
     * @param ClubStudent $clubStudent
     */
    public function __construct(ClubCourseSign $clubCourseSign,ClubCourse $clubCourse,ClubStudent $clubStudent,ClubSales $clubSales)
    {
        parent::__construct();
        $this->clubCourseSign = $clubCourseSign;
        $this->clubCourse = $clubCourse;
        $this->clubStudent = $clubStudent;
        $this->clubSales = $clubSales;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Log::setGroup('SmsError')->error('自动上课通知短信-脚本start');

        //首先找到今天需要上课的课程ID
        $today = Carbon::now();
        $clubCourse = $this->clubCourse->query()->where('status',1)
            ->where('day',$today->format('Y-m-d'))
            ->select('id','start_time')
            ->get();

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

        if (empty($courseIds)) {
            Log::setGroup('SmsError')->error('自动上课通知短信-暂无符合条件发送短信通知的课程');
            return;
        }

        //判断课程上课短信是否发送过了
        $sendCacheKey = 'SendGoToClassSms';
        //$existsCourse = Redis::get('SendGoToClassSms');
        $existsCourseIds = Redis::lrange($sendCacheKey,0,-1);
        if (! empty($existsCourseIds)) {
            //$existsCourseIds = json_decode($existsCourse);
            Log::setGroup('SmsError')->error('自动上课通知短信-已经发送过短信的课程IDs',[$existsCourseIds]);

            $courseIds = array_diff($courseIds,$existsCourseIds);

            if (empty($courseIds)) {
                Log::setGroup('SmsError')->error('自动上课通知短信-今天要发送的课程已经没有了',[$existsCourseIds]);
                return;
            }
        }

        //根据要上课的courseId找出需要发送短信的
        $courseStudents = $this->clubCourseSign->query()
            ->with(['club','student','student.binduser','course','course.venue','class','class.teachers'])
            ->whereIn('course_id',$courseIds)
            ->whereNull('sign_status')
            ->get();

        if ($courseStudents->isEmpty()) {
            Log::setGroup('SmsError')->error('自动上课通知短信-没有要上课的学员签到记录');
            return;
        }

        $students = collect($courseStudents)->pluck('student');

        if ($students->isEmpty()) {
            Log::setGroup('SmsError')->error('自动上课通知短信-没有学员数据');
            return;
        }

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
                //Sms::sendSms($item->student->guarder_mobile,$data,'270690');
                Sms::sendSms('15055349677',$data,'270690');
                $arr = [
                    'stuId' => $item->student_id,
                    'guardMobile' => $item->student->guarder_mobile,
                    'courseId' => $item->course_id,
                    'push_date' => Carbon::now()->toDateTimeString()
                ];
                Log::setGroup('SmsError')->error('自动上课通知短信-发送的用户记录',[$arr]);
            }

            if ($item->student->binduser->isNotEmpty()) {
                $appUserMobiles = $item->student->binduser->pluck('app_account')->implode(',');
                $paramData = [
                    'courseDateTime' => $courseTimeStr,
                    'courseId' => $item->course->id,
                    'venueName' => $item->course->venue->name,
                    'venueAddress' => $item->course->venue->address,
                    'className' => $item->class->name,
                    'appUserMobiles' => $appUserMobiles
                ];

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

        $teacher = $this->clubSales->query()->find($teacherId);

        if (empty($teacher)) return [];

        return [
            'mobile' => $teacher->mobile,
            'name' => $teacher->sales_name
        ];
    }

}