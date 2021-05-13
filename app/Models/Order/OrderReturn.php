<?php

namespace App\Models\Order;

use App\Models\Catalog\Product\ProductAttribute;
use Illuminate\Database\Eloquent\Model;

use Carbon\Carbon;
use DB;

use App\Models\Order\OrderRefundDetail;

class OrderReturn extends Model{

    protected $table = 'order_returns';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'platformorderid', 'order_line_id','product_code', 'returned_quantity','amount','reason','delivery_return','admin_id','status',
    ];

    public function refund()
    {
        return $this->hasMany(OrderRefundDetail::class, 'module_id')->where('module_type','=', 1);
    }

    public function orderline()
    {
        return $this->belongsTo(OrderLine::class, 'order_line_id', 'id');
    }


    
}
