<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Currency;
use App\Repositories\Currency\Interfaces\CurrencyRepositoryInterface;
use Response;

class CurrencyController extends Controller
{

    public function changeCurrency(CurrencyRepositoryInterface $currency, Request $request)
    {
        $currency = Currency::setCurrency($currency, $request->currency);
        session(['currency' => $currency]);
        
        return Response::json([
            'success' => true,
        ]);
    }
}
