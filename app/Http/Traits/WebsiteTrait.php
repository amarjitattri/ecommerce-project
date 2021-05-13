<?php

namespace App\Http\Traits;

use App\Models\Timezone;
use App\Models\CMS\Language;
use App\Models\Catalog\Category;
use App\Models\CMS\OrderFraudParam;
use Illuminate\Support\Facades\Auth;
use App\Models\MyAccount\MessageEvent;
use App\Models\MyAccount\UserEmailPreference;

trait WebsiteTrait{


    public function useremailpreferences()
    {
        return $this->belongsToMany(MessageEvent::class, 'user_email_preferences')->where('user_id', Auth::user()->id)
            ->withPivot('user_id', 'status');
    }

    public function emailpreferences()
    {
        return $this->hasMany(UserEmailPreference::class);
    }

    public function fraudParams()
    {
        return $this->hasMany(OrderFraudParam::class)->select('id', 'website_id', 'type', 'value');
    }

    
    /**
     * Get All website settings based on website_host
     */
    public function getWebsiteSettings()
    {
    }

    /**
     * The roles that belong to the user.
     */
    public function languages()
    {
        return $this->belongsToMany(Language::class, 'webiste_lang_settings');
    }

    /**
     * Relationship with website categories by pivot table
     */
    public function website_categories()
    {
        return $this->belongsToMany(Category::class, 'website_categories', 'website_id', 'category_id');
    }

    /**
     * Relationship with website categories by pivot table
     */
    public function websiteNavigation()
    {
        return $this->belongsToMany(Category::class, 'website_navigations', 'website_id', 'category_id');
    }

    public function timezoneData()
    {
        return $this->hasOne(Timezone::class, 'id', 'timezone');
    }

    /**
     * Get the franchises association with user
     */
    public function websiteLanguage()
    {
        return $this->hasManyThrough(
            'App\Models\CMS\Language',
            'App\Models\WebsiteLangSettings',
            'website_id',
            'id',
            'id',
            'language_id'
        );
    }
}
