<?php

namespace App\Model\Club;

use Illuminate\Database\Eloquent\Model;

class ClubQrcodeImage extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_club_qrcode_image';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = True;


}