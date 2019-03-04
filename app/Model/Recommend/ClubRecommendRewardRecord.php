<?php
namespace App\Model\Recommend;

use Illuminate\Database\Eloquent\Model;
use App\Model\Recommend\ClubCourseReward;
use App\Facades\Util\Log;

class ClubRecommendRewardRecord extends Model
{
    /**
     * @var string
     */
    protected $table = 'club_recommend_reward_record';

    /**
     * 推荐学员
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function recommendstu()
    {
        return $this->belongsTo('App\Model\ClubStudent\ClubStudent','stu_id');
    }

    /**
     * 未删除的数据
     * @param $query
     * @return mixed
     */
    public function scopeNotDelete($query)
    {
        return $query->where('is_delete',0);
    }

    /**
     * 获取俱乐部买单奖励课时数
     * @param $clubId
     * @return array
     */
    public function getClubCourseRewardNumForBuy($clubId)
    {
        $courseReward = ClubCourseReward::where('club_id',$clubId)->first();

        if (empty($courseReward)) {
            Log::setGroup('RecommendError')->error('俱乐部没有奖励课时设置',['clubId' => $clubId]);
            return ['buyCourseRewardNum' => 0];
        }

        return ['buyCourseRewardNum' => $courseReward->num_for_buy];
    }
}