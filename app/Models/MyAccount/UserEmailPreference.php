<?php

namespace App\Models\MyAccount;

use Illuminate\Database\Eloquent\Model;

class UserEmailPreference extends Model
{
    protected $fillable = ['user_id', 'status', 'message_event_id'];
    public $timestamps = false;

    public static function isPreferedEvent($userId, $eventId)
    {
        return (bool) static::where([
            'user_id' => $userId,
            'message_event_id' => $eventId,
            'status' => 1,
        ])->count();
    }
}
