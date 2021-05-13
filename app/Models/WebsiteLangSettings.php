<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteLangSettings extends Model
{
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $fillable = [
        'website_id',
        'language_id',
        'is_primary',
        'status',
    ];
    protected $table = "webiste_lang_settings";
}
