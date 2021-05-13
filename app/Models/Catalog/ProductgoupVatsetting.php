<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;

class ProductgoupVatsetting extends Model
{
    //
    protected $table = 'productgroup_vatsettings';
    public function scopeGetProductGroupVat($query, $argv)
    {
        return $query->select('productgroup_id', 'website_id', 'country_id', 'vat_applicable', 'vat_rate')
                ->where([
                    'productgroup_id' => $argv['productgroup_id'],
                    'website_id' => $argv['website_id'],
                    'country_id' => $argv['country_id'],
                ]);
    }

    public function scopeGetVatProductGroups($query, $argv)
    {
        return $query->select('productgroup_id', 'website_id', 'country_id', 'vat_applicable', 'vat_rate')
                ->whereIn('productgroup_id', $argv['productgroup_ids'])
                ->where([
                    'website_id' => $argv['website_id'],
                    'country_id' => $argv['country_id'],
                ]);
    }

}
