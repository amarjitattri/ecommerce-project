<?php

namespace App\Models\SupplierOrder;

use Illuminate\Database\Eloquent\Model;

class SupplierCustomerOrder extends Model
{
    const STOCKORDERTYPE = '1';
    const CUSTOMERORDERTYPE = '2';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'supplier_order_id', 'supplier_order_product_id', 'order_id', 'quantity', 'order_number', 'type'
    ];
}
