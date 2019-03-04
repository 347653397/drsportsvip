<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/16
 * Time: 11:41
 */

namespace App\Api\Controllers\Students;


use App\Http\Controllers\Controller;
use App\Model\ClubStudentRemark\ClubStudentRemark;
use App\Model\ClubUser\ClubUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class StudentRemarkController extends Controller
{
    /**
     * 添加备注
     * @param Request $request
     * @return array
     */
    public function addRemark(Request $request){
            $data = $request->all();
            $validator = \Validator::make($data,[
                'studentId' => 'required|numeric',
                'remark' => 'string'
            ]);
            if($validator->fails()){
                return returnMessage('101','非法操作');
            }

            $clubId = isset($data['user']['club_id']) ? $data['user']['club_id'] : '';
            $operation = isset($data['user']['id']) ? $data['user']['id'] : '';
            $studentId = isset($data['studentId']) ? $data['studentId'] : '';
            $remark = isset($data['remark']) ? $data['remark'] : '';

            try{
                $studentRemark = new ClubStudentRemark();
                $studentRemark->student_id = $studentId;
                $studentRemark->club_id = $clubId;
                $studentRemark->remark = $remark;
                $studentRemark->create_time = date('Y-m-d H:i:s',time());
                $studentRemark->operation_user_id = $operation;
                $studentRemark->save();
            }catch(\Exception $e){
                return returnMessage('400','添加失败');
            }

            return returnMessage('200','success');


    }

    /**
     * 学员备注列表
     * @param Request $request
     * @return array
     */
    public function getStudentRemark(Request $request){
        $data = $request->all();

        $validator = \Validator::make($data,[
           'studentId' => 'required|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if($validator->fails()){
            return returnMessage('101','非法操作');
        }
        $clubId = isset($data['user']['club_id']) ? $data['user']['club_id'] : '';
        $studentId = isset($data['studentId']) ? $data['studentId'] : '';//学员id
        $pagePerNum = isset($data['pagePerNum']) ? $data['pagePerNum'] : 10;//一页显示多少条
        $currentPage = isset($data['currentPage']) ? $data['currentPage'] : 1;//页码

        $offset = ($currentPage-1)*$pagePerNum;
        $studentRemark = ClubStudentRemark::where('club_id',$clubId)->where('student_id',$studentId)->get();
        $result['totalNum'] = count($studentRemark);
        $studentRemark = ClubStudentRemark::where('club_id',$clubId)->where('student_id',$studentId)->offset($offset)->limit($pagePerNum)->get();


        $result['result'] = $studentRemark->transform(function($item){
            $arr = [
                'studentRemarkId' => $item->id,
                'remark' => $item->remark,
                'opeartor' => $this->operationName($item->operation_user_id),
                'createTime' => $item->create_time,
            ];
            return $arr;
        });
        return returnMessage('200','success',$result);
    }

    public function operationName($id){
        $user = ClubUser::find($id);
        return $user->username;
    }
}