<?php

namespace App\Model\ClubUser;


use App\Model\ClubRole\ClubRole;
use Illuminate\Foundation\Auth\User as Authenticatable ;
use Illuminate\Notifications\Notifiable;

class ClubUser extends Authenticatable
{
    use Notifiable;
    //
    protected $table = 'club_user';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * 用户所对应的角色
     */
    public function role(){
        return $this->belongsTo('App\Model\Permission\Role','role_id','id');
    }

}
