<?php

namespace App\Model\ClubSystem;

use Illuminate\Database\Eloquent\Model;

class ClubMessageApp extends Model
{

    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_message_app';

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
     * 根据公告内学员剩余课时数转换成app内存的数据（主要是数据的转换）
     * @param $count
     * @return mixed|null
     */
    public function getLeftCountKeyForApp($count)
    {
        $arr = [
            '1' => '0',
            '2' => '1',
            '3' => '2'
        ];

        return isset($arr[$count]) ? $arr[$count] : null;
    }

    /**
     * 根据公告内响应状态转换为app内存的数据（主要是数据的转换）
     * @param $respondType
     * @return mixed|null
     */
    public function getRespondTypeNameForApp($respondType)
    {
        //1=无、2=打开促销场馆列表、3=打开某场馆、4=打开某班级、5=打开某网页
        $arr = [
            '1' => 'none',
            '2' => 'promote_venue',
            '3' => 'venue',
            '4' => 'class',
            '5' => 'web'
        ];

        return isset($arr[$respondType]) ? $arr[$respondType] : null;
    }
}
