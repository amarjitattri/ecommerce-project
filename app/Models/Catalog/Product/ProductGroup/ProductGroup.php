<?php

namespace App\Models\Catalog\Product\ProductGroup;

use Illuminate\Database\Eloquent\Model;
use App\Models\Catalog\Product\ProductGroup\ProductGroupDocument;

class ProductGroup extends Model
{
    const DOCUMENT = 'document';
    const DOCUMENT_PATH = 'document_path';
    public function documents() {
        return $this->hasMany(ProductGroupDocument::class);
    }
}
