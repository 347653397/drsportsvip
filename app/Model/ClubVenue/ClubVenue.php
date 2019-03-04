<?php

namespace App\Model\ClubVenue;

use App\Model\Club\Club;
use App\Model\ClubClassStudent\ClubClassStudent;
use Illuminate\Database\Eloquent\Model;
use App\Model\ClubClass\ClubClass;

class ClubVenue extends Model
{
    //
    protected $table = 'club_venue';

    /**
     * 场馆对应的课程
     */
    public function classes(){
        return $this->hasMany(ClubClass::class,'venue_id');
    }

    /**
     * 场馆对应的班级
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function club()
    {
        return $this->belongsTo('App\Model\Club\Club','club_id','id');
    }

    /**
     * 场馆图片
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function images()
    {
        return $this->hasMany('App\Model\ClubVenue\ClubVenueImage','venue_id','id');
    }

    /**
     * 场馆学员
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function students()
    {
        return $this->hasMany('App\Model\ClubClassStudent\ClubClassStudent','venue_id','id');
    }

    /**
     * 场馆学员数
     * @param $classIds
     * @return mixed
     */
    public function getVenueStudents($classIds)
    {
        $students = ClubClassStudent::with(['student'])
            ->whereIn('class_id',$classIds)
            ->get();

        $validStudents = collect($students)->filter(function ($item) {
            return !empty($item->student) && $item->student->status == 1;;
        });

        return collect($validStudents)->count();
    }

    /**
     * 可以在app显示的场馆
     * @param $query
     * @return mixed
     */
    public function scopeShowInApp($query)
    {
        return $query->where('show_in_app',1);
    }

    /**
     * 有效场馆
     * @param $query
     * @return mixed
     */
    public function scopeValid($query)
    {
        return $query->where('status',1);
    }

    /**
     * 未删除场馆
     * @param $query
     * @return mixed
     */
    public function scopeNotDelete($query)
    {
        return $query->where('is_delete',0);
    }
}
