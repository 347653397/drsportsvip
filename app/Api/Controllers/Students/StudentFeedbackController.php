<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/16
 * Time: 9:41
 */

namespace App\Api\Controllers\Students;


use App\Http\Controllers\Controller;
use App\Model\ClubStudentFeedback\ClubStudentFeedback;
use App\Model\ClubStudentFeedbackReason\ClubStudentFeedbackReason;
use App\Model\ClubUser\ClubUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class StudentFeedbackController extends Controller
{
    /**
     * 添加反馈
     * @param Request $request
     * @return array
     */
    public function addFeedback(Request $request){
        $data = $request->all();
        $validator = \Validator::make($data,[
            'studentId' => 'numeric|required',
            'intentType' => 'numeric|required',
            'reasonId' => 'string|required',
            'remark' => 'string|required'
        ]);
        if($validator->fails()){
            return returnMessage('101','非法操作');
        }

        $clubId = $data['user']['club_id'];
        $operateId = $data['user']['id'];
        $studentId = $data['studentId'];
        $intentType = $data['intentType'];
        $reasonId = $data['reasonId'];
        $remark = $data['remark'];

        try{
            $feedback = new ClubStudentFeedback();
            $feedback->club_id = $clubId; //club_id
            $feedback->student_id = $studentId;//学员id
            $feedback->intenting_type = $intentType; //意向
            $feedback->reason_id = $reasonId;//原因
            $feedback->remark = $remark;//备注
            $feedback->operation_user_id = $operateId; //操作id
            $feedback->create_time = date('Y-m-d',time());//创建时间
            $feedback->save();
        }catch(\Exception $e){
            return returnMessage('400','添加失败');
        }
        return returnMessage('200','请求成功');
    }

    /**
     * 获取反馈列表
     * @param Request $request
     * @return array
     */
    public function getFeedback(Request $request){
        $data = $request->all();
        $validator = \Validator::make($data,[
            'studentId' => 'numeric|required',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if($validator->fails()){
            return returnMessage('101','非法操作');
        }
        $clubId = isset($data['user']['club_id']) ? $data['user']['club_id'] : ''; //该学员所在俱乐部
        $studentId = isset($data['studentId']) ? $data['studentId'] : '';

        $feedback = ClubStudentFeedback::where('club_id',$clubId)
            ->where('student_id',$studentId)
            ->paginate($data['pagePerNum'], ['*'], 'currentPage', $data['currentPage']);

        $result['totalNum'] = $feedback->total();
        $result['result'] = $feedback->transform(function($item){
            $arr = [
                'studentFeedbackId' => $item->id,
                'intentType' => $this->getIntentName($item->intentingType),
                'reason' => $this->getStuFeedbackReason($item->reason_id),
                'remark' => $item->remark,
                'opeartor' => $this->getUserName($item->operation_user_id),
                'creatTime' => $item->created_at->format('Y-m-d H:i:s')
            ];
            return $arr;
        });
        return returnMessage('200','请求成功',$result);
    }

    /**
     * 获取学员反馈原因
     * @param $reasonId
     * @return string
     */
    public function getStuFeedbackReason($reasonId)
    {
        $reasonIdArr = explode(',', $reasonId);

        if (count($reasonIdArr) == 1) {
            $reason = ClubStudentFeedbackReason::where('id', $reasonIdArr[0])->value('name');
        }
        else {
            $reason = '';
            for ($i = 0; $i < count($reasonIdArr); $i++) {
                if ($i == 0) {
                    $reason .= ClubStudentFeedbackReason::where('id', $reasonIdArr[$i])->value('name');
                }
                else {
                    $reason .= '，'.ClubStudentFeedbackReason::where('id', $reasonIdArr[$i])->value('name');
                }
            }
        }

        return $reason;
    }

    /**
     * 获取学员反馈原因checkbox
     * @return array
     */
    public function getFeedbackReason(){
        $feedReason = ClubStudentFeedbackReason::all();
        $result = $feedReason->transform(function ($item){
            $arr = [
              'studentFeedBackReasonId' => $item->id,
               'studentFeedBackReasonName' => $item->name
            ];
            return $arr;
        });
        return returnMessage('200','success',$result);
    }

    protected function getUserName($id){
            $name = ClubUser::where('id',$id)->value('username');
            return $name;
    }
    /**
     * 学员的反馈内容
     */
    public function getReasonName($ids){
        $reasonId = explode(',',$ids);
        $feedbackReason = [];
        foreach ($reasonId as $val){
            $student = ClubStudentFeedbackReason::select('name')->find($val);
            $feedbackReason[] = $student->name;
        }
        if(!empty(count($feedbackReason))){
            $feedbackReason = implode(',',$feedbackReason);
        }
        return $feedbackReason;
    }
    public function object_array($array){
        $reason = [];
        foreach ($array as $key => $val){
            $reason[] = (array)$val;
        }
        return $reason;
    }
    /**
     * 获取意向名称
     * @param $id
     * @return string
     */
    public function getIntentName($id){
        switch ($id){
            case "1": return '90%以上意向' ; break;
            case "2": return '75%~90%意向'; break;
            case "3": return '60~75%意向'; break;
            case "4": return '60%一下意向'; break;
            default: return '暂无'; break;
        }
    }
}