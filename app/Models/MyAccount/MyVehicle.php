<?php

namespace App\Models\MyAccount;

use Illuminate\Database\Eloquent\Model;

class MyVehicle extends Model
{
    
protected $fillable = [
                        'user_id',
                        'model_id'
                    ];
protected $table = "user_saved_vehicles";

/**
 * Get association with BikeModel
 */
public function bikeModelAssoc()
{
    return $this->belongsTo('App\Models\CMS\BikeModel','model_id','id');
}

}
