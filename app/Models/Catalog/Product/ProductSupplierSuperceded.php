<?php

namespace App\Models\Catalog\Product;

use Illuminate\Database\Eloquent\Model;

class ProductSupplierSuperceded extends Model
{
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['product_id','supplier_id','franchise_id','updated_by','ceder_value'];
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "product_supplier_superceded";
}
