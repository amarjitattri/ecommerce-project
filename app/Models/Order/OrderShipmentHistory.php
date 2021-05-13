<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;
use App\Models\Catalog\Product\Product;
use App\Models\Order\OrderShipment;
class OrderShipmentHistory extends Model
{
    
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function shipment()
    {
        return $this->belongsTo(OrderShipment::class, 'shipment_id');
    }
}
