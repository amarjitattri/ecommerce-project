<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Franchise extends Model
{
    const STATUS_ACTIVE = '1';
    const STATUS_INACTIVE = '0';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'logo', 'short_code', 'address_1','city','county', 'address_2', 'address_3', 'country_id', 'postcode', 'representive_name', 'email', 'private_notes', 'bank_details', 'sort_code', 'paypal_account', 'vat_registration_number', 'enterprise_type', 'company_number', 'eori_number', 'deactivation_reason','date_of_deactivation','incorporation_date', 'fiscal_period_start', 'fiscal_period_end', 'currency_id', 'ordering_frequency', 'is_master', 'is_headless', 'status',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "wmbackend_franchises";

    public function country()
    {
        return $this->belongsTo('App\Models\Country')->select('id', 'country_name', 'country_3_code', 'country_2_code');
    }

}
