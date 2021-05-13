<?php

namespace App\Models\Catalog\Product;

use Illuminate\Database\Eloquent\Model;
use App\Models\Catalog\Attribute;
use App\Models\Catalog\Product\{ 
  Product
};

class ProductAttribute extends Model
{
    const TYPE ='1';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['product_id','attribute_id','attribute_value_id','type'];
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "product_attribute_value";

    public static function findAttribute($attribute,$value,$product_id)
    {
        return  Product:: where(['id'=>$product_id,'attributeset_id'=>$value])->exists();
       
    }

    public function attribute() {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }
    public function attributeSetAssoc() {
     
        return $this->hasManyThrough(
            
            'App\Models\Catalog\Attribute',
            'App\Models\Catalog\AttributeValue',
            'attribute_id',
            'id',
            'product_id','attribute_id');
}
}
