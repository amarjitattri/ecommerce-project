<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    public function zonePostcodes()
    {
        return $this->hasMany(ZonePostcode::class);
    }
}
