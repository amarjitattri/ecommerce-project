<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Timezone extends Model
{
    public function country()
    {
        return $this->hasOne(Country::class);
    }
}
