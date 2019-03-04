<?php
namespace App\Model\Recommend;

use Illuminate\Database\Eloquent\Model;

class ClubCourseReward extends Model
{
    /**
     * @var string
     */
    protected $table = 'club_course_reward';

    /**
     * 未删除的数据
     * @param $query
     * @return mixed
     */
    public function scopeNotDelete($query)
    {
        return $query->where('is_delete',0);
    }
}