<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Carriage extends Model
{
    public static function getCarriages()
    {
        $carriages = Carriage::select('id', 'code', 'company', 'country_id', 'website_id', 'is_estimated', 'estimated_time', 'price', 'delivery_text', 'free_threshold');
        //add website id condition ones carriage module enabled and carriages added for all 
        return $carriages->get()->keyBy('id');
    }

    public function scopeGetCarriagesByIds($query, $argv)
    {
        return $query->select('id', 'code', 'company', 'country_id', 'website_id', 'is_estimated', 'estimated_time', 'price', 'delivery_text', 'free_threshold')
            ->whereIn('id', $argv['ids']);
    }
}
