<?php

namespace App\Http\Controllers\Checkout;

use App\Services\Cart\Cart;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Services\Checkout\CODFee;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Trade\TradeOrderDetail;
use App\Services\Checkout\DeliveryMethod;
use App\Http\Requests\Checkout\AddressRequest;
use App\Models\CMS\Website\WebsiteDeliveryLocation;
use App\Http\Requests\Checkout\DeliveryMethodRequest;
use App\Http\Requests\Checkout\CollectionMethodRequest;
use App\Models\Order\OrderPaymentDetail;
use App\Repositories\Order\Interfaces\OrderRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Repositories\Order\Interfaces\TradeOrderRepositoryInterface;
use App\Repositories\Checkout\Interfaces\CheckoutRepositoryInterface;
use Session;

class CheckoutController extends Controller
{
    private $__cart;
    private $checkoutRepository;
    private $tradeRepository;
    protected $__orderRepo;

    public function __construct(
        Cart $cart,
        CheckoutRepositoryInterface $checkoutRepository,
        TradeOrderRepositoryInterface $tradeRepository,
        OrderRepositoryInterface $order
    ) {
        $this->__cart = $cart;
        $this->checkoutRepository = $checkoutRepository;
        $this->tradeRepository = $tradeRepository;
        $this->__orderRepo = $order;
    }
    const SHIPPING_ADDRESS_TYPE = 1;
    const BILLING_ADDRESS_TYPE = 2;
    const IS_PRIME_TYPE = 1;
    const VAT_APPLICABLE = 2;
    const VAT_NOT_APPLICABLE = 1;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, CODFee $cod)
    {
        //check payment already exists
        $params = [
            'platformorderid' => session('payment.platformorderid')
        ];
        if (OrderPaymentDetail::checkPaymentExists($params)) {
            return redirect('/order/complete');
        }

        $userId = null;
        if (Auth::check()) {
            $userId = Auth::user()->id;
        }

        $this->__cart->userId($userId);
        if (!$this->__cart->hasCart()) {
            return redirect('basket');
        }

        $websiteId = config('wmo_website.website_id');
        $checkoutSession = $request->session()->get('checkout');
        if (!$checkoutSession || $checkoutSession['stage'] == config('constant.checkout.CUSTOMER_DETAIL_STAGE')) {
            $this->__cart->setDelivery(null);
        }
        $collection = $this->tradeRepository->checkOrderType();
        // if user switch checkout mode in between
        if (isset($checkoutSession['trader']['type']) && $checkoutSession['trader']['type'] != $collection) {
            $request->session()->forget('checkout');
        }

        $addresses = $this->loginSession($request, $checkoutSession, $websiteId);
        $shippingAddresses = $addresses['shippingAddresses'];
        $billingAddresses = $addresses['billingAddresses'];

        $this->__cart->setCODCharges(null);
        $basket = $this->__cart->transformCart() ?? [];
        if (!$this->__orderRepo->isProductDataMatched($checkoutSession, $basket)) {
            $checkoutSession = $request->session()->get('checkout');
        }

        $deliveryLocations = $this->checkoutRepository->getShippingCountries($websiteId);
        $countries = $this->checkoutRepository->getBillingCountries();

        $shipping = $checkoutSession['user_addresses']['shipping'] ?? [];
        $billing = $checkoutSession['user_addresses']['billing'] ?? [];
        if (!empty($shipping['country_id']) && empty($deliveryLocations[$shipping['country_id']])) {
            $request->session()->forget('checkout');
            $addresses = $this->loginSession($request, $checkoutSession, $websiteId);
            $shippingAddresses = $addresses['shippingAddresses'];
            $billingAddresses = $addresses['billingAddresses'];
        }
        if (!empty($billing['country_id']) && empty($countries[$billing['country_id']])) {
            $request->session()->forget('checkout');
            $addresses = $this->loginSession($request, $checkoutSession, $websiteId);
            $shippingAddresses = $addresses['shippingAddresses'];
            $billingAddresses = $addresses['billingAddresses'];
        }

        $deliveryMethods = DeliveryMethod::getMethods($basket);
        $checkoutSession = $request->session()->get('checkout');
        $carriagesAll = DeliveryMethod::getCarriages($checkoutSession, $deliveryMethods, $collection);
        $carriages = $carriagesAll['carriages'];
        $seperateCarriages = $carriagesAll['seperateCarriages'];
        // unset selected carriage from session if not available
        if (!$collection) {
            DeliveryMethod::validateSessionValues($checkoutSession, $carriagesAll, $request);
        }

        $singleShipment = $deliveryMethods[config('constant.checkout.SHIPMENT_TOGETHER_TYPE')] ?? [];
        $multipleShipments= [];

        $haveCarriagesForBoth = !empty($deliveryMethods[config('constant.checkout.SHIPMENT_SEPARATE_TYPE')]) && !empty($seperateCarriages[1]) && !empty($seperateCarriages[2]);
        if (!empty($collection) ||  $haveCarriagesForBoth) {
            $multipleShipments = $deliveryMethods[config('constant.checkout.SHIPMENT_SEPARATE_TYPE')];
        }

        $collectionItemsOnly = !(!empty($singleShipment) || !empty($multipleShipments));
        $request->session()->put('collection_items_only', $collectionItemsOnly);

        $checkoutSession = $request->session()->get('checkout');

        $basket = $this->__cartCurrencyConversion($basket);

        $basketReviewListing = $this->__cart->transformBasket($basket);
        $websiteFranchise = config('wmo_website.franchise');
        $request['localShopOrderType'] ? session(['localShopOrderType' => $request['localShopOrderType']]) : '';

        $refNum = '';
        $refnotes = '';
        if (isset($request['refNum'])) {
            $refNum = $request['refNum'];
            session(['refNum' => $request['refNum']]);
        }
        if (isset($request['refnotes'])) {
            $refnotes = $request['refnotes'];
            session(['refnotes' => $request['refnotes']]);
        }

        $localshopOrderType = $request['localShopOrderType'] ?: request()->session()->get('localShopOrderType');

        $paymentMethods = config('wmo_website.payment_gateways');
        $brainTreeOptions = array();
        if (isset($paymentMethods['braintree'])) {
            $brainTreeOptions = explode(',', $paymentMethods['braintree']);
        }

        $codCharges = $cod->validateCODMethod($this->checkoutRepository);
        if (!$codCharges) {
            unset($paymentMethods['cod']);
        }

        $this->tradeRepository->setChecoutDetails();

        isset($request['localshopfreeshipping']) ? $request->session()->put('localshopfreeshipping', 'localshopfreeshipping') : $request->session()->forget('localshopfreeshipping');

        return view('checkout.index', compact('checkoutSession', 'deliveryLocations', 'countries', 'shippingAddresses', 'billingAddresses', 'basket', 'carriages', 'seperateCarriages', 'singleShipment', 'multipleShipments', 'collectionItemsOnly', 'basketReviewListing', 'websiteFranchise', 'localshopOrderType', 'paymentMethods', 'brainTreeOptions', 'collection', 'refNum', 'refnotes'));
    }

    public function loginSession($request, $checkoutSession, $websiteId)
    {
        $checkoutSession = $request->session()->get('checkout');
        $shippingAddresses = [];
        $billingAddresses = [];
        if (Auth::check()) {
            $userId = Auth::user()->id;
            $shippingAddresses = $this->checkoutRepository->getShippingAddresses($userId);
            $billingAddresses = $this->checkoutRepository->getBillingAddresses($userId);

            if (empty($checkoutSession) || $checkoutSession['method_type'] != config('constant.checkout.LOGIN_METHOD')) {
                $request->session()->put('checkout', [
                    'website' => $websiteId,
                    'method_type' => config('constant.checkout.LOGIN_METHOD'),
                    'stage' => config('constant.checkout.CUSTOMER_DETAIL_STAGE'),
                    'user_info' => Auth::user(),
                ]);
            }

            if (empty($checkoutSession['stage'])) {
                $request->session()->put('checkout.stage', config('constant.checkout.CUSTOMER_DETAIL_STAGE'));
            }
        }
        return compact('shippingAddresses', 'billingAddresses');
    }

    /**
     * when user proceed as guest
     *
     * @param Request $request
     * @param Response $response
     * @return json
     */
    public function guestSession(Request $request, Response $response)
    {
        try {
            $userId = Auth::check() ? Auth::user()->id : null;
            $data = $request->all();
            $this->__cart->userId($userId);

            if (!$this->__cart->hasCart()) {
                return response()->json(['message' => __('message.cart_is_empty'), 'url' => route('basket.index')], $response::HTTP_TEMPORARY_REDIRECT);
            }

            $data = $request->all();
            $userInfo = [
                "email" => $data['email'],
            ];
            if(isset($data['unique_id']) && $data['email']=='')
            {
                $userInfo['unique_id'] = $data['unique_id'];
            }
            $webType = config('wmo_website.website_code');
            if (isset($data['is_business']) && $data['is_business']==1 && $webType=='IT') {
                $userInfo['vat_number'] = $data['vat_number'];
                $userInfo['tax_code'] = $data['tax_code'];
                $userInfo['is_business'] = 1;
            } elseif (isset($data['is_business']) && $data['is_business']==0 && $webType=='IT') {
                $userInfo['vat_number'] = '';
                $userInfo['tax_code'] = '';
                session()->forget('checkout.user_info');
            }
            $checkoutSession = $request->session()->get('checkout');

            if (!empty($checkoutSession)) {
                $request->session()->put('checkout.website', config('wmo_website.website_id'));
                $request->session()->put('checkout.method_type', config('constant.checkout.GUEST_METHOD'));
                if (empty($checkoutSession['stage']) || ($checkoutSession['stage'] < config('constant.checkout.CUSTOMER_DETAIL_STAGE'))) {
                    $request->session()->put('checkout.stage', config('constant.checkout.CUSTOMER_DETAIL_STAGE'));
                }
                $request->session()->put('checkout.user_info', $userInfo);
            } else {
                $request->session()->put('checkout', [
                    'website' => config('wmo_website.website_id'),
                    'method_type' => config('constant.checkout.GUEST_METHOD'),
                    'stage' => config('constant.checkout.CUSTOMER_DETAIL_STAGE'),
                    'user_info' => $userInfo,
                ]);
            }
            $response_return = response()->json(['message' => __('message.logged_in_successfully'), 'user' => $userInfo], $response::HTTP_OK);
        } catch (\Exception $exception) {
            logger()->error($exception);
            $response_return = response()->json(['message' => __('messages.something_went_wrong')], $response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return $response_return;
    }

    /**
     * API for shipping address
     *
     * @param AddressRequest $request
     * @param Response $response
     * @return json
     */
    public function storeAddress(AddressRequest $request, Response $response)
    {
        try {
            $websiteId = config('wmo_website.website_id');
            $userId = Auth::check() ? Auth::user()->id : null;
            $data = $request->all();
            $this->__cart->userId($userId);

            $checkoutSession = $request->session()->get('checkout');
            $returnWithError = null;
            if (!$this->__cart->hasCart()) {
                $returnWithError = response()->json(['message' => __('message.cart_is_empty'), 'url' => route('basket.index')], $response::HTTP_TEMPORARY_REDIRECT);
            } elseif (empty($checkoutSession['stage']) || ($checkoutSession['stage'] < config('constant.checkout.CUSTOMER_DETAIL_STAGE'))) {
                $returnWithError = response()->json(['message' => 'Please fill customer details first'], $response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $userAddresses = $this->processUserAddresses($data);

            if (empty($returnWithError) && $this->checkoutRepository->ifZipBlocked($userAddresses)) {
                $returnWithError = response()->json(['message' => "We are not able to process your order. Please contact administrator"], $response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $deliveryLocations = $this->checkoutRepository->getShippingCountries($websiteId);
            $countries = $this->checkoutRepository->getBillingCountries();

            $shipping = $userAddresses['shipping'] ?? [];
            $billing = $userAddresses['billing'] ?? [];
            $shippingCountryCheck = (!empty($shipping['country_id']) && empty($deliveryLocations[$shipping['country_id']]));
            $billingCountryCheck = (!empty($billing['country_id']) && empty($countries[$billing['country_id']]));
            if ($shippingCountryCheck || $billingCountryCheck) {
                $returnWithError = response()->json(['message' => 'We are not able to process your order. Please contact administrator', 'error' => 'Shipping Address not in Delivery Locations'], $response::HTTP_INTERNAL_SERVER_ERROR);
            }

            if (!empty($returnWithError)) {
                return $returnWithError;
            }
            $request->session()->put('checkout.user_addresses', $userAddresses);

            if (empty($checkoutSession['stage']) || ($checkoutSession['stage'] < config('constant.checkout.ADDRESS_STAGE'))) {
                $request->session()->put('checkout.stage', config('constant.checkout.ADDRESS_STAGE'));
            }

            $collectionItemsOnly = $request->session()->get('collection_items_only');
            if ($collectionItemsOnly) {
                $basket = $this->__cart->transformCart() ?? [];
                $deliveryMethods = DeliveryMethod::getMethods($basket);
                $collectionShipment = $deliveryMethods[config('constant.checkout.SHIPMENT_COLLECTION_TYPE')] ?? [];
                $request->session()->put('checkout.collection_shipment', $collectionShipment);
            }

            $checkoutSession = $request->session()->get('checkout');
            $response_return = response()->json(['message' => __('message.address_saved_successfully'), 'collectionItemsOnly' => $collectionItemsOnly, 'data' => $checkoutSession], $response::HTTP_OK);

            /* changes for special cart start */
            $deliveryLocationSpecialCart = $this->__cart->transformCart() ?? [];
            $deliveryLocation = count(array_filter(array_column($deliveryLocationSpecialCart['products'], 'brand_id')));
            /* changes for special cart end */
            if ($deliveryLocation) {
                $deliveryLocation = $this->checkVatDeliveryLocation($request);
            }
            if ($deliveryLocation == static::VAT_APPLICABLE || $deliveryLocation == static::VAT_NOT_APPLICABLE) {
                return response()->json([
                    'status' => $deliveryLocation,
                ]);
            }
        } catch (\Exception $exception) {
            logger()->error($exception);
            $response_return = response()->json(['message' => __('messages.something_went_wrong')], $response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return $response_return;
    }

    public function getAddressDropdowns(Request $request, Response $response)
    {
        if (Auth::check()) {
            $userId = Auth::user()->id;
            $checkoutSession = $request->session()->get('checkout');
            $shipping = $checkoutSession['user_addresses']['shipping'] ?? [];
            $billing = $checkoutSession['user_addresses']['billing'] ?? [];

            $shippingAddresses = $this->checkoutRepository->getShippingAddresses($userId);
            $billingAddresses = $this->checkoutRepository->getBillingAddresses($userId);
            $shippingDropdowns = view('checkout.partials.shipping_dropdowns', compact('shippingAddresses', 'shipping'))->render();
            $billingDropdowns = view('checkout.partials.billing_dropdowns', compact('billingAddresses', 'billing'))->render();
            $response_return = response()->json(['billing_addresses' => $billingAddresses, 'shipping_addresses' => $shippingAddresses, 'shipping_dropdowns' => $shippingDropdowns, 'billing_dropdowns' => $billingDropdowns], $response::HTTP_OK);
        } else {
            $response_return = response()->json(['message' => __('messages.user_not_logged_in')], $response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return $response_return;
    }

    public function deliveryMethod(DeliveryMethodRequest $request, Response $response, CODFee $cod)
    {
        try {
            $collection = $this->tradeRepository->checkOrderType();
            $returnWithError = null;
            $userId = Auth::check() ? Auth::user()->id : null;
            $this->__cart->userId($userId);

            $checkoutSession = $request->session()->get('checkout');
            $ADDRESS_STAGE = config('constant.checkout.ADDRESS_STAGE');
            $address = session()->get('checkout.user_addresses');
            if (!$this->__cart->hasCart()) {
                $returnWithError = response()->json(['message' => __('message.cart_is_empty'), 'url' => route('basket.index')], $response::HTTP_TEMPORARY_REDIRECT);
            } elseif (empty($checkoutSession['stage']) || ($checkoutSession['stage'] < $ADDRESS_STAGE && empty($request->collection))) {
                $returnWithError = response()->json(['message' => 'Please fill shipping address first'], $response::HTTP_INTERNAL_SERVER_ERROR);
            } elseif ($this->checkoutRepository->ifZipBlocked($address)) {
                $returnWithError = response()->json(['message' => 'We are not able to process your order. Please contact administrator'], $response::HTTP_INTERNAL_SERVER_ERROR);
            }

            if (!empty($returnWithError)) {
                return $returnWithError;
            }

            //set delivery charges
            $this->__cart->setDelivery($request->all());

            $basket = $this->__cart->transformCart() ?? [];
            $deliveryMethods = DeliveryMethod::getMethods($basket);
            $carriagesAll = DeliveryMethod::getCarriages($checkoutSession, $deliveryMethods, $collection);
            
            $carriages = $carriagesAll['carriages'];
            $seperateCarriages = $carriagesAll['seperateCarriages'];

            $singleShipment = $deliveryMethods[config('constant.checkout.SHIPMENT_TOGETHER_TYPE')] ?? [];

            $multipleShipments = [];
            $haveCarriagesForBoth = !empty($deliveryMethods[config('constant.checkout.SHIPMENT_SEPARATE_TYPE')]) && !empty($seperateCarriages[1]) && !empty($seperateCarriages[2]);
            if (!empty($collection) ||  $haveCarriagesForBoth) {
                $multipleShipments = $deliveryMethods[config('constant.checkout.SHIPMENT_SEPARATE_TYPE')];
            }

            $collectionItemsOnly = (!empty($singleShipment) || !empty($multipleShipments));


            // no carriage array if collection oredr from trade site
            if ($request['shipment_type'] == config('constant.checkout.SHIPMENT_SEPARATE_TYPE')) {
                $selectedDeliveryMethod['shipments'] = $deliveryMethods[$request['shipment_type']];
                if (empty($collection)) {
                    foreach ($request['carriage_id'] as $shipment => $carriage_id) {
                        $selectedDeliveryMethod['shipments'][$shipment]['carriage_id'] = $carriage_id;
                        $selectedDeliveryMethod['shipments'][$shipment]['carriage'] = $seperateCarriages[$shipment][$carriage_id];
                    }
                }
            } else {
                $selectedDeliveryMethod = $deliveryMethods[$request['shipment_type']];
                if (empty($collection)) {
                    $selectedDeliveryMethod['carriage_id'] = $request['carriage_id'];
                    $selectedDeliveryMethod['carriage'] = $carriages[$request['carriage_id']];
                }
            }

            $selectedDeliveryMethod['shipment_type'] = $request['shipment_type'];
            $selectedDeliveryMethod['collection'] = $collection;
            if (empty($checkoutSession['stage']) || ($checkoutSession['stage'] < config('constant.checkout.DELIVERY_METHOD_STAGE'))) {
                $request->session()->put('checkout.stage', config('constant.checkout.DELIVERY_METHOD_STAGE'));
            }
            $request->session()->put('checkout.delivery_method', $selectedDeliveryMethod);
            $collectionShipment = $deliveryMethods[config('constant.checkout.SHIPMENT_COLLECTION_TYPE')] ?? [];
            $request->session()->put('checkout.collection_shipment', $collectionShipment);
            $checkoutSession = $request->session()->get('checkout');

            $view_name = 'checkout.partials.collection_details';
            if (empty($request->collection)) {
                $view_name = 'checkout.partials.delivery_method';
            }
            $html = view($view_name, compact('checkoutSession', 'carriages', 'seperateCarriages', 'singleShipment', 'multipleShipments', 'collectionItemsOnly'))->render();

            $basket = $this->__cartCurrencyConversion($basket);

            $codFlag = $this->checkCodOptions($cod);
            $response_return = response()->json(['message' => __('checkout.delivery_method_selected'), 'html' => $html, 'basket' => $basket, 'data' => $checkoutSession, 'cod' => $codFlag], $response::HTTP_OK);
        } catch (\Exception $exception) {
            logger()->error($exception);
            $response_return = response()->json(['message' => __('messages.something_went_wrong')], $response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return $response_return;
    }

    private function __cartCurrencyConversion($basket)
    {
        //currency conversions
        if (count($basket)) {
            $basket = $this->__cart->cartCurrencyConversion($basket, session('currency'));
        }
        return $basket;
    }

    public function tradeOrderNotes(Request $request)
    {
        if (!isTradeSite()) {
            return redirect()->back()->with('message', __('messages.something_went_wrong'));
        }

        $this->validate($request, [
            'trade_order_notes' => 'present|max:500',
        ]);

        $checkoutSession = $request->session()->get('checkout');
        $request->session()->put('checkout.trader.order_notes', $request->input('trade_order_notes') ?? '');

        if ($checkoutSession['stage'] < config('constant.checkout.TRADE_ORDER_NOTES_STAGE')) {
            $request->session()->put('checkout.stage', config('constant.checkout.TRADE_ORDER_NOTES_STAGE'));
        }

        return redirect()->back();
    }

    public function processUserAddresses($data)
    {
        $userId = Auth::user()->id ?? null;
        $shippingAddress = $data['shipping'];
        $billingAddress = $data['billing'];
        $shippingId = $shippingAddress['id'] ?? null;
        $shippingFrmUsrDetail = $shippingAddress['from_user_detail'] ?? 0;
        $billingId = $billingAddress['id'] ?? null;
        $billingFrmUsrDetail = $billingAddress['from_user_detail'] ?? 0;
        $userAddresses = [
            'shipping' => $shippingAddress,
            'billing' => $billingAddress,
        ];

        // if chooses to save address and logged in
        $saveShipping = (!empty($shippingAddress['save_address']) && Auth::check());
        if ($saveShipping) {
            $shippingAddress['type'] = static::SHIPPING_ADDRESS_TYPE;
            $shippingAddress['user_id'] = $userId;

            // to make first time added address prime
            $primeShippingAddress = $this->checkoutRepository->primeShippingAddress($userId);
            if (empty($primeShippingAddress)) {
                $shippingAddress['is_prime'] = static::IS_PRIME_TYPE;
            }

            $shippingId = $this->checkoutRepository->updateOrCreateAddress(['id' => $shippingAddress['id']], $shippingAddress);
        }
        if (!empty($shippingId) && ($shippingFrmUsrDetail == 1 || $saveShipping)) {
            $userAddresses['shipping'] = $this->checkoutRepository->getAddressById($shippingId);
            $userAddresses['shipping']['same_for_billing'] = $shippingAddress['same_for_billing'] ?? null;
        }
        if (!empty($shippingAddress['same_for_billing'])) {
            $oldBillingId = $billingAddress['id'] ?? null;
            $billingAddress = $shippingAddress;
            $billingAddress['id'] = $oldBillingId;
        }
        $saveBilling = ((!empty($billingAddress['save_address']) || (!empty($shippingAddress['save_address']) && !empty($shippingAddress['same_for_billing']))) && Auth::check());

        if ($saveBilling) {
            $billingAddress['type'] = static::BILLING_ADDRESS_TYPE;
            $billingAddress['user_id'] = $userId;

            // to make first time added address prime
            $primeBillingAddress = $this->checkoutRepository->primeBillingAddress($userId);
            if (empty($primeBillingAddress)) {
                $billingAddress['is_prime'] = static::IS_PRIME_TYPE;
            } else {
                $billingAddress['id'] = $billingAddress['id'] ?: $primeBillingAddress->id;
            }

            $billingId = $this->checkoutRepository->updateOrCreateAddress(['id' => $billingAddress['id']], $billingAddress);
        }
        if (!empty($billingId) && ($billingFrmUsrDetail == 1 || $saveBilling)) {
            $userAddresses['billing'] = $this->checkoutRepository->getAddressById($billingId);
        }

        return $userAddresses;
    }

    public function checkVatDeliveryLocation($request)
    {
        $countryData = config('wmo_website.countryData');
        $noIP = is_null($countryData) && is_null(session('shipping_country_id'));
        if (session('shipping_country_id')) {
            $countryData = (object) ['id' => session('shipping_country_id')];
        }

        $return = false;
        if (isset($request['shipping']['country_id'])) {
            $params = [
                'website_id' => config('wmo_website.website_id'),
                'country_id' => $request['shipping']['country_id'],
            ];

            session(['shipping_country_id' => $request['shipping']['country_id']]);
            $websiteDeliverLocation = WebsiteDeliveryLocation::getDeliverLocationByCountryId($params)->first();

            if ($noIP && $websiteDeliverLocation['vat_applicable'] != 1) {
                $return = static::VAT_APPLICABLE;
            } elseif (!empty($countryData) && $countryData->id != $request['shipping']['country_id']) {
                if ($websiteDeliverLocation['vat_applicable'] != 1) {
                    $return = static::VAT_APPLICABLE;
                } elseif ($websiteDeliverLocation['vat_applicable'] == 1) {
                    $return = static::VAT_NOT_APPLICABLE;
                }
            }
        }
        return $return;
    }

    public function calculateCODCharges(CODFee $cod)
    {
        $userId = Auth::check() ? Auth::user()->id : null;
        $this->__cart->userId($userId);
        $codView = [];
        $status = false;
        $codCharges = [];
        if ($this->__cart->hasCart()) {
            $codCharges = $cod->calculateCODCharges($this->__cart, $this->checkoutRepository);
            if ($codCharges) {
                $status = true;
                $codView = view('checkout.partials.codecharges', compact('codCharges'))->render();
            }
        }

        return response()->json([
            'success' => $status,
            'codcharges' => [
                'html' => $codView,
                'json' => $codCharges,
            ],
        ]);
    }

    public function checkCodOptions($cod)
    {
        $paymentMethods = config('wmo_website.payment_gateways');
        $codCharges = $cod->validateCODMethod($this->checkoutRepository);
        if (!$codCharges) {
            unset($paymentMethods['cod']);
        }

        return $codCharges;
    }
}
