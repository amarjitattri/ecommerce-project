<?php

namespace App\Models\MyAccount;

use Illuminate\Database\Eloquent\Model;
use App\Models\CMS\BikeModel;

class CalendarEvent extends Model
{
    const EMAIL_SENT=0;
    protected $table = "calendar_events";

    protected $fillable = [ 'user_id', 'reminder_type','vehicle_id', 'event_date', 'notes','email_sent'];

    /**
     * Get association with BikeModel
     */
    public function eventBikeModelAssoc()
    {
        return $this->belongsTo('App\Models\CMS\BikeModel','vehicle_id','id');
    }
    /**
     * Get association with BikeModel
     */
    public function eventReminderAssoc()
    {
        return $this->belongsTo('App\Models\MyAccount\CalendarReminder','reminder_type','id');
    }

    public static function scopeEventSelect($query)
    {
        $websiteMake = BikeModel::getWebsiteMake();
       
        return $query->with(['eventBikeModelAssoc'=>function($query) use ($websiteMake){
                            $query->select('model.id','name','make_id','year','customer_notes')
                            ->whereIn('make_id',$websiteMake)
                            ->with([       
                                'makeAssoc'=> function($query2){
                                $query2->select('id','name','status');
                                },
                                'customerNotesAssoc' => function ($query6) {
                                    $query6->select('id', 'notes');
                                }


                            ]);
                        },
                        'eventReminderAssoc'
         ]);
    }
}
