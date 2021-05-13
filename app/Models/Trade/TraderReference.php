<?php

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Model;

class TraderReference extends Model
{
    protected $fillable = [
        'company_name', 'contact_name', 'addressline1', 'addressline2', 'user_id', 'state', 'city', 'country_id', 'postcode',
        'contact_no', 'account_open_year', 'reference_no'
    ];
}
