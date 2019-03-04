<?php
namespace App\Api\Controllers\coach;

use App\Services\ClubClass\IClubClassService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ClubClassController extends Controller
{
  /**
   * @var IClubClassService
   */
  public $IClubClassService;

  /**
   * BannerController constructor.
   * @param IClubClassService $clubClassService
   */
  public function __construct(IClubClassService $clubClassService)
  {
      $this->clubClassService = $clubClassService;
  }
  // 班级列表
  public function lists(Request $request){
    $postData = $request->all();
    $validator = Validator::make($postData , [
        'keyword' => 'required|string',
        'clueNo' => 'required|string',
        'status' => 'nullable|numeric',
        'venue_id' => 'required|numeric',
        'calss_id' => 'required|numeric',
        'page' => 'required|numeric',
        'pageSize' => 'required|numeric',
    ]);
    if ($validator->fails()) {
        return parent::error('001', config('error.param.001'));
    }
    try {
        $this->clubClassService->lists($postData);
    } catch (Throwable $throwable) {
        return parent::error($throwable->getCode(), $throwable->getMessage());
    }
    return parent::success();
  }

  // 添加班级
  public function create(Request $request){
    $postData = $request->all();
    $validator = Validator::make($postData , [
      'className' => 'required|string',
      'classType' => 'required|numeric',
      'payPlanTypeId' => 'nullable|numeric',
      'venueId' => 'required|numeric',
      'teacherId' => 'required|numeric',
      'teacherName' => 'required|string',
      'studentLimit' => 'required|numeric',
      'showInApp' => 'required|numeric',
      'remark' => 'required|string',
      'classTime' => 'required|string',
    ]);
    if ($validator->fails()) {
        return parent::error('001', config('error.param.001'));
    }
    try {
        $this->clubClassService->create($postData);
    } catch (Throwable $throwable) {
        return parent::error($throwable->getCode(), $throwable->getMessage());
    }
    return parent::success();
  }

  // 修改班级
  public function update(Request $request){
    $postData = $request->all();
    $validator = Validator::make($postData , [
        'classId' => 'required|numeric',
        'className' => 'required|string',
        'classType' => 'required|numeric',
        'payPlanTypeId' => 'nullable|numeric',
        'venueId' => 'required|numeric',
        'teacherId' => 'required|numeric',
        'teacherName' => 'required|string',
        'studentLimit' => 'required|numeric',
        'showInApp' => 'required|numeric',
        'remark' => 'required|string',
        'classTime' => 'required|string',
    ]);
    if ($validator->fails()) {
        return parent::error('001', config('error.param.001'));
    }
    try {
        $this->clubClassService->update($postData);
    } catch (Throwable $throwable) {
        return parent::error($throwable->getCode(), $throwable->getMessage());
    }
    return parent::success();
  }

  // 删除班级
  public function delete(Request $request){
    $postData = $request->all();
    $validator = Validator::make($postData , [
        'classId' => 'required|numeric',
    ]);
    if ($validator->fails()) {
        return parent::error('001', config('error.param.001'));
    }
    try {
        $this->clubClassService->delete($postData);
    } catch (Throwable $throwable) {
        return parent::error($throwable->getCode(), $throwable->getMessage());
    }
    return parent::success();
  }

  // 班级详情
  public function detail(Request $request){
    $postData = $request->all();
    $validator = Validator::make($postData , [
        'classId' => 'required|numeric',
    ]);
    if ($validator->fails()) {
        return parent::error('001', config('error.param.001'));
    }
    try {
        $this->clubClassService->detail($postData);
    } catch (Throwable $throwable) {
        return parent::error($throwable->getCode(), $throwable->getMessage());
    }
    return parent::success();
  }

  // 班级概况汇总
  public function classCeneralSituationAll(Request $request){
    $postData = $request->all();
    $validator = Validator::make($postData , [
        'classId' => 'required|numeric',
    ]);
    if ($validator->fails()) {
        return parent::error('001', config('error.param.001'));
    }
    try {
        $this->clubClassService->classCeneralSituationAll($postData);
    } catch (Throwable $throwable) {
        return parent::error($throwable->getCode(), $throwable->getMessage());
    }
    return parent::success();
  }

  // 班级概况
  public function classCeneralSituation(Request $request){
    $postData = $request->all();
    $validator = Validator::make($postData , [
        'classId' => 'required|numeric',
    ]);
    if ($validator->fails()) {
        return parent::error('001', config('error.param.001'));
    }
    try {
        $this->clubClassService->classCeneralSituation($postData);
    } catch (Throwable $throwable) {
        return parent::error($throwable->getCode(), $throwable->getMessage());
    }
    return parent::success();
  }
}
