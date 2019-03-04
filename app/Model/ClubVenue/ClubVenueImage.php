<?php
namespace App\Model\ClubVenue;

use Illuminate\Database\Eloquent\Model;

class ClubVenueImage extends Model
{
    /**
     * @var string
     */
    protected $table = 'club_venue_image';

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