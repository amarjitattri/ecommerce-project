<?php

namespace App\Http\Controllers\MyAccount;

use App\Http\Controllers\Controller;
use App\Models\CMS\BikeModel;
use App\Models\MyAccount\CustomUserWishlist;
use App\Models\MyAccount\UserWishlist;
use App\Repositories\Myaccount\Interfaces\MyVehicleRepositoryInterface;
use App\Services\Cart\Cart;
use App\Services\Watermark\WatermarkService;
use Auth;
use Illuminate\Http\Request;
use Response;
use App\Models\Catalog\Product\Product;

class WishlistController extends Controller
{
    private $__cart;
    private $myVehicleRepository;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct(Cart $cart, MyVehicleRepositoryInterface $myVehicleRepository)
    {
        $this->__cart = $cart;
        $this->myVehicleRepository = $myVehicleRepository;
    }
    public function index()
    {
        //custom Wishlist code
        $params = [
            'user_id' => Auth::user()->id,
            'website_id' => config('wmo_website.website_id'),
        ];
        
        $getCustomWishlists = CustomUserWishlist::getCustomWishlist($params)->orderBy('custom_user_wishlists.updated_at', 'DESC')->get();
        
        $getCustomWishlist = $this->getWishList($getCustomWishlists);
        // wislist
        $sidebar = 8;
        $userWishlist = UserWishlist::allWishlist($params)->orderBy('updated_at', 'DESC')->get();
        $userWishlists = $this->getWishList($userWishlist);

        foreach ($userWishlists as $product) {
            if (!empty($product['product']['productImages'][0]['name'])) {
                WatermarkService::processImage($product['product']['productImages'][0]['name']);
            }
        }
        $getModels = [];
        return view('myaccount.wishlist.index', compact('sidebar', 'userWishlists', 'getModels', 'getCustomWishlist'));
    }

    public function getWishList($userWishlist)
    {
        $userWishlists = [];
        
        if ($userWishlist->count() > 0) {
            $adminId = !empty(session()->get('local_shop_user')) ? session()->get('local_shop_user') :'';
            foreach ($userWishlist as $value) {
                $value->model_slug = '';
                if(!empty($value->modelname)){
                    $modelname='';
                    $modelname = $value->makename.' '.$value->modelname.' '.$value->year;
                    $modelname .= !empty($value->notes)?' - '.$value->notes:'';
                    $value->modelname = $modelname;
                }
                
                if (empty($value->url) && !empty($value->modelname)) {
                    $value->model_slug = BikeModel::getmodelSlug($value->model_id);
                }
                //generate product url
                if (!empty($value->product)) {
                    $options = $value->assoc_id
                    ? ['model_slug' => $value->association->alias . '-' . $value->bike_model_id]
                    : ['assoc_slug' => $value->categroies->url ?? ''];
                    $productCode = $value->product->code;
                    $productDetails =[
                        'code'=>$productCode,
                        'type'=>$value->product->type,
                        'price'=>$value->detailAssoc
                    ];
    
                    $productLabels = Product::codeLabels($productDetails,$adminId);
                    $value->product_code=$productLabels['product_code'];
                    $value->product_label=$productLabels['product_code_label'];
                    $value->product_url = $this->__cart->productUrl($options, $productCode);
                }

                $userWishlists[] = $value;

            }

        }
        return $userWishlists;
    }

  

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $status = true;
            $options = json_decode($request->options, true);
            $userId = Auth::check() ? Auth::user()->id : null;
            $assocId = $options['model_id'] ? $options['assoc_id'] : 0;
            $catId = $options['model_id'] ? 0 : $options['assoc_id'];

            $data = ['website_id' => config('wmo_website.website_id'),
                'user_id' => $userId,
                'product_id' => $request->product_id,
                'assoc_id' => $assocId,
                'bike_model_id' => $options['model_id'],
                'model_year' => $options['modelYear'],
                'cat_id' => $catId,
            ];

            UserWishlist::create($data);
        } catch (\Exception $e) {
            $status = false;
        }

        return Response::json([
            'success' => $status,
        ]);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        try {
            UserWishlist::where([
                'id' => $id,
            ])->delete();
            $status = true;
        } catch (\Exception $e) {
            $status = false;
        }

        return Response::json([
            'success' => $status,
        ]);
    }
}
