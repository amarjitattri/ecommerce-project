<?php

namespace App\Models\Locale;

 
use Illuminate\Database\Eloquent\Model;

class LocaleModel extends Model
{
    protected $fillable = ['type', 'model_id', 'language_id', 'name', 'seo_title', 'description', 'seo_description', 'translation_status', 'user_id'];

    const TYPE_MODEL = 1;
    const TYPE_MODEL_FAMILY = 2;
    const TYPE_MAKE = 3;    

}
