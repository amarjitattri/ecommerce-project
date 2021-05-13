<?php

namespace App\Models\Catalog;

use App\Models\Catalog\Product\Product;
use App\Models\CMS\InfoPageContent;
use App\Models\Locale\LocaleCategory;
use DB;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    const ACTIVE_STATUS = '1';
    const ACTIVE_STATUS_CATEGORY = '1';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $primaryKey = 'id';
    protected $fillable = [
        'parent_id', 'menu_title', 'page_title', 'url', 'description', 'short_description', 'thumbnail', 'tierlevel', 'show_in_menu', 'status',
    ];
    protected $table = "categories";

    public function categories()
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function localeCategories()
    {
        return $this->hasMany(LocaleCategory::class);
    }

    public function childrenCategories()
    {
        return $this->hasMany(static::class, 'parent_id')->where('status', static::ACTIVE_STATUS_CATEGORY)->with('categories');
    }

    public function categoyProducts()
    {
        return $this->hasMany(Product::class, 'category_id')->where('status', Product::ACTIVE_STATUS);
    }

    public function getParentsNames()
    {
        if ($this->categories) {
            return $this->categories->getParentsNames() . "|" . $this->menu_title;
        } else {
            return $this->menu_title;
        }
    }

    public function getParentsIds()
    {
        if ($this->categories) {
            return $this->categories->getParentsIds() . "|" . $this->id;
        } else {
            return $this->id;
        }
    }

    public function scopeLanguageJoin($query, $alias = 'categories')
    {
        $query->when(session('language.languagecode') != 'en', function ($q) use ($alias) {
            $q->selectRaw('IF(lc.menu_title = "" OR lc.menu_title IS NULL, ' . $alias . '.menu_title, lc.menu_title) as menu_title,
            IF(lc.description = "" OR lc.description IS NULL, ' . $alias . '.description, lc.description) as description')
                ->leftJoin('locale_categories as lc', function ($qr) use ($alias) {
                    $qr->on('lc.category_id', '=', $alias . '.id')
                        ->where('language_id', session('language.id'));
                });
        });
    }

    public static function getExportCollection()
    {
        return DB::table('categories as maincat')
            ->leftJoin('categories as subcat', 'subcat.id', '=', 'maincat.parent_id')
            ->select('maincat.menu_title AS menu_title', 'subcat.menu_title AS parent_name', 'maincat.page_title AS page_title', 'maincat.url AS url', 'maincat.thumbnail AS thumbnail', 'maincat.description AS description', 'maincat.tierlevel AS tierlevel', 'maincat.status AS status', 'maincat.show_in_menu AS show_in_menu')
            ->get();
    }
    /**
     * The table associated with the model.
     *
     * @var string
     */

    public static function getRelatedCategories($id)
    {
        return DB::table('related_categories as rc')
            ->leftJoin('categories as c', 'c.id', '=', 'rc.related_category_id')
            ->where('category_id', $id)
            ->where('status', 1)
            ->select('*')
            ->get();
    }

    public static function getRelatedCategoriesExpectParent($getParentIds)
    {
        return static::whereNotIn('id', $getParentIds)
            ->where('status', 1)
            ->get();
    }

    public static function getCatsByPageId($info_page_id)
    {
        return static::from('categories as c')->select('c.url', 'c.thumbnail', 'c.id', 'c.menu_title', 'c.short_description', 'c.page_title')
            ->where('ipc.content_type', InfoPageContent::TYPE_CATEGORY)
            ->where('ipc.info_page_id', $info_page_id)
            ->where('c.status', static::ACTIVE_STATUS)
            ->join('info_page_contents as ipc', 'c.id', '=', 'ipc.content_id')
            ->languageJoin('c')->get()->toArray();
    }

}
