<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class OrderLineVariant extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'platformorderid', 'orderline_id', 'product_id', 'varient_id', 'variant_quantity', 'quantity'
    ];



    public function scopeGetKitCartData($query,$platformorderid)
    {
        $query->select('order_line_variants.varient_id','p.code','p.type','p.customer_description')
        ->leftJoin('products as p','p.id', '=', 'order_line_variants.varient_id');
        if(!empty($platformorderid))
        {
            $query->where('order_line_variants.platformorderid',$platformorderid);
        }
       return $query;
    }
}
