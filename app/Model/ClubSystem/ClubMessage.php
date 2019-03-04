<?php

namespace App\Model\ClubSystem;

use Illuminate\Database\Eloquent\Model;

class ClubMessage extends Model
{

    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_message';

    /**
     * 所属俱乐部
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function club()
    {
        return $this->belongsTo('App\Model\Club\Club','club_id');
    }

    /**
     * 所属俱乐部
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function smstemplate()
    {
        return $this->belongsTo('App\Model\ClubSystem\ClubMessageTemplate','message_template_type');
    }
}
