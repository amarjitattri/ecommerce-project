<?php

namespace App\Models\Locale;

 
use Illuminate\Database\Eloquent\Model;

class LocaleCategory extends Model
{
    protected $fillable = ['category_id', 'language_id', 'menu_title', 'page_title', 'description', 'translation_status', 'user_id'];

    

}
