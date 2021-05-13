<?php
namespace App\Helpers;

use Illuminate\Support\Facades\Facade;

class TaxFacade extends Facade {
    
    protected static function getFacadeAccessor()
    {
        return 'tax';
    }
}