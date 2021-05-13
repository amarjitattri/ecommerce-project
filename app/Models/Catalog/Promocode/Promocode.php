<?php
namespace App\Models\Catalog\Promocode;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use App\Models\Catalog\Category;

class Promocode extends Model
{
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'promocode_categories');
    }

    public function countries()
    {
        return $this->belongsToMany(Category::class, 'promocode_countries', 'promocode_id', 'country_id');
    }

    public function scopeStatus($query, $status)
    {
        $query->where('status', $status);
    }

    public function scopeActiveFrom($query)
    {
        $value = Carbon::now();
        return $query->where('valid_from', '<=', $value);
    }

    public function scopeActiveTill($query)
    {
        $value = Carbon::now();
        return $query->where('valid_to', '>=', $value);
    }

    public function scopeGetPromoByCode($query, $argv) 
    {
        return $query->with(['categories', 'countries'])
            ->activeFrom()
            ->activeTill()
            ->status($argv['status'])
            ->where(['promocode' => $argv['promocode'], 'website_id' => $argv['website_id']]);
    }
}
