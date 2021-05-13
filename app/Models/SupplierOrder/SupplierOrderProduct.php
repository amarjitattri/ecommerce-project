<?php

namespace App\Models\SupplierOrder;

use Illuminate\Database\Eloquent\Model;

class SupplierOrderProduct extends Model
{
    const UNPROCESSED = '0';
    const PROCESSED = '1';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'supplier_order_id', 'product_id', 'order_number', 'make', 'model', 'description', 'quantity', 'review_quantity', 'is_review', 'notes', 'status'
    ];
}
