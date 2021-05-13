<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    //

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "countries";
    const ACTIVE_STATUS='1';

    static function getCountriesList(){
        return Country::orderBy('country_name','asc')->where('status',static::ACTIVE_STATUS)->pluck('country_name','id');
    }

    static function getBillingCountries(){
        return Country::select('country_name', 'id', 'status', 'country_2_code', 'country_3_code')->where('status', static::ACTIVE_STATUS)->orderBy('country_name', 'ASC')->get()->keyBy('id');
    }

    static function getCountriesArray($countries){
        $response=[];
        if(!empty($countries)){
            foreach($countries as $val){
                $id = isset($val['country'])?$val['country']['id']:$val['id'];
                $response[$id] = isset($val['country'])?$val['country']['country_name']:$val['country_name'];
            }
        }
        return $response;
    }

    public function scopeGetCountry($query, $argv)
    {
        return $query->select(
            'id',
            'country_name',
            'country_3_code',
            'country_2_code',
            'vat_applicable',
            'sr',
            'rr1',
            'rr2',
            'rr3',
            'rr4',
            'rr5',
            'union_id',
            'status'
            )->where('id', $argv['country_id']);
    }

    public function scopeGetCountryByCountryCode($query, $argv)
    {
        return $query->select(
            'id',
            'country_name',
            'country_3_code',
            'country_2_code',
            'vat_applicable',
            'sr',
            'rr1',
            'rr2',
            'rr3',
            'rr4',
            'rr5',
            'union_id',
            'status'
            )->where('country_2_code', $argv['code']);
    }
}
