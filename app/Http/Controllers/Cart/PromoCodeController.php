<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Services\Cart\Cart;
use Response;
use App\Http\Requests\Promocode\PromocodeRequest;
use Validator;
use Auth;
class PromoCodeController extends Controller
{
    private $__cartObj;

    public function __construct(Cart $cart)
    {
        $this->__cartObj = $cart;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $response = []; 
        $validator = Validator::make($request->all(), [
            'promocode' => ['required', 'string', 'min:5', 'max:20']
        ]);

        if ($validator->fails()) {
            $response = [
                'success' => false,
                'errors' => [
                    'error_code' => 'VALIDATION_ERROR',
                    'messages' => ['promocode' => $validator->errors()->first('promocode')],
                ]
            ];
        } else {
            $promoCode = $request->promocode;
            $userId = Auth::check() ? Auth::user()->id : null;
            
             $this->__cartObj->userId($userId);
           
            
            $this->__cartObj->setPromoCode($promoCode);
            $transformCart = $this->__cartObj->transformCart();
            if (isset($transformCart['promotion']) && $transformCart['promotion']['status']) {
                $response = [
                    'success' => true,
                    'errors' => false,
                    'message' => $transformCart['promotion']['message'],
                    'cart' => []
                ];
            } else {
                $response = $transformCart['promotion'];
                // temptorary solution for locale
                // app()->setLocale(session('language.languagecode'))
                
                $response['errors']['messages'] = ['promocode' => trans('checkout.invalid_promocode')];
            }
            
        }
        return Response::json($response);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $userId = Auth::check() ? Auth::user()->id : null;
        $this->__cartObj->userId($userId);
        $this->__cartObj->removePromoCode();
        return [
            'success' => true
        ];
    }
}
