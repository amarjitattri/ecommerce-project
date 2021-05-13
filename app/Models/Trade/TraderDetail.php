<?php

namespace App\Models\Trade;

use App\Models\Trade\TradeTierStructure;
use Illuminate\Database\Eloquent\Model;

class TraderDetail extends Model
{
    protected $fillable = [
        'legal_entity', 'trader_name', 'business_owner_name', 'websites', 'business_type', 'legal_entity_other', 'user_id',
        'years_trading', 'other_information', 'purchase_from_other', 'purchase_from', 'business_activities', 'vehicle_range',
    ];

    const LEGAL_ENTITY_OTHER = 4;
    const PURCHASE_FROM_OTHER = 4;

    public function tierStructure()
    {
        return $this->belongsTo(TradeTierStructure::class, 'discount_tier', 'id');
    }
}
