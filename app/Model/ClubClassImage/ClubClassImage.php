<?php

namespace App\Model\ClubClassImage;

use Illuminate\Database\Eloquent\Model;

class ClubClassImage extends Model
{
    /**
     * 关联到模型的数据表
     * @var string
     */
    protected $table = 'club_class_image';

    /**
     * @param $query
     * @return mixed
     */
    public function scopeNotDelete($query)
    {
        return $query->where('is_delete',0);
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeShow($query)
    {
        return $query->where('is_show',1);
    }
}