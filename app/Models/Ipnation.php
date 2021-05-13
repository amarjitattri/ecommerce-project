<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\Country;
class Ipnation extends Model
{
    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}
