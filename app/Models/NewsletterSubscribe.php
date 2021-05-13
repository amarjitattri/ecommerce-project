<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NewsletterSubscribe extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email'
    ];

       /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "newsletter_subscriber";
}
