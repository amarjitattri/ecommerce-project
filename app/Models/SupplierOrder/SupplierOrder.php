<?php

namespace App\Models\SupplierOrder;

use Illuminate\Database\Eloquent\Model;

class SupplierOrder extends Model
{
    const STOCKCUSTOMERORDERTYPE = '1';
    const MANUALORDERTYPE = '3';
    const UNPROCESSED = '0';
    const PROCESSED = '1';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'franchise_id', 'supplier_id', 'product_id', 'order_number', 'make', 'model', 'description', 'quantity', 'is_review', 'review_quantity', 'notes', 'self_manufactured', 'status', 'order_type', 'created_by', 'is_wemoto_uk'
    ];

    public static function updateOrder($existSupplierPendingOrder, $qty)
    {
        $pendingOrderId = $existSupplierPendingOrder->id;
        $newQuantity = $existSupplierPendingOrder->quantity + $qty;
        SupplierOrder::where('id', $pendingOrderId)->update(['quantity' => $newQuantity]);
        return $pendingOrderId;
    }
}
