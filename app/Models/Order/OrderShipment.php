<?php

namespace App\Models\Order;

use Auth;

use Currency;
use Carbon\Carbon;
use App\Models\Order\OrderLine;
use App\Models\Order\OrderShipmentHistory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Order\OrderStatus;
use App\Models\Order\OrderInvoice;

class OrderShipment extends Model
{
    const UNPROCESSED = 'UP';
    const AWAITING_COLLECTION = 'AC';

    protected $fillable = [
        'id', 'platformorderid', 'carriage_code', 'carriage_price', 'shipment_num', 'shipment_weight', 'user_id', 'status', 'dispatched_date', 'tentative_eta', 'shipment_price', 'cod_charge', 'carriage_vat_percentage', 'rule_id'
    ];

    public function saveShipment($params)
    {
        $userInfo = session()->get('checkout');
        $currency = session('currency');
        $userId = Auth::check() ? Auth::user()->id : null;
        $platformOrderId = $params['platformorderid'];
        $shippingCharges = $params['cart']['shipping_charges'] ?? null;
        $carriageCode = '';
        $carriagePrice = '';
        $dispatchedDate = '';
        $tentativeDate = '';
        $codShipmentCharges = $params['cart']['cod_charges']['shipments'];
        $shippingVat = session('shipping_country_calculation')['country_data']['sr'];

        OrderShipment::where('platformorderid',$platformOrderId)->delete();
        if (!empty($userInfo['delivery_method']) && $userInfo['delivery_method']['shipment_type'] == 2)
        {
            $shipmentsProducts = [];
            foreach($userInfo['delivery_method']['shipments'] as $key => $value) {
              //insert shipment wise charges
              $shipmentTotal = $this->calculateShipmentTotal($value['products']);
              foreach($value['products'] as $p) {
                $shipmentsProducts[$p['product_id']] = $key;
              }
              if (empty($userInfo['delivery_method']['collection'])) {
                    $carriageCode = $value['carriage']['shipper']['name'];
                    $carriagePrice = $value['carriage']['carriage_eta_charges']['final_eta']['charges'];
                    $carriagePrice = Currency::convertCurrencyRaw($carriagePrice, $currency);
                    $dispatchedDate = Carbon::now();
                    $tentativeDate = Carbon::now();
                    $tentativeDate->addDays($value['dispatch']['to']);
              }
              if ($shippingCharges == 0) {
                $carriagePrice = 0;
              }
              $shipmentVat = $carriagePrice > 0 ? $shippingVat : null;
              //calculate eta
              $orderShipment[] = [
                'platformorderid' => $platformOrderId,
                'carriage_code' => $carriageCode,
                'carriage_price' => $carriagePrice,
                'cod_charge' => Currency::convertCurrencyRaw($codShipmentCharges[$key]['fee'], $currency),
                'carriage_vat_percentage' => $shipmentVat,
                'shipment_num' => $key,
                'shipment_weight' => $value['weight'] ?? 0,
                'user_id' => $userId,
                'status' => static::UNPROCESSED,
                'dispatched_date' => $dispatchedDate,
                'tentative_eta' => $tentativeDate,
                'shipment_price' => Currency::convertCurrencyRaw($shipmentTotal, $currency),
                'rule_id' => $value['carriage_id'] ?? null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
              ];
            }
        }
        elseif(!empty($userInfo['delivery_method']) && $userInfo['delivery_method']['shipment_type'] == 1){
          $shipmentTotal = $this->calculateShipmentTotal($userInfo['delivery_method']['products']);
          foreach($userInfo['delivery_method']['products'] as $p) {
            $shipmentsProducts[$p['product_id']] = 1;
          }

            if (empty($userInfo['delivery_method']['collection'])) {
                $carriageCode = $userInfo['delivery_method']['carriage']['shipper']['name'];
                $carriagePrice = $userInfo['delivery_method']['carriage']['carriage_eta_charges']['final_eta']['charges'];
                $carriagePrice = Currency::convertCurrencyRaw($carriagePrice, $currency);
                $dispatchedDate = Carbon::now();
                //calculate eta
                $tentativeDate = Carbon::now();
                $tentativeDate->addDays($userInfo['delivery_method']['dispatch']['to']);
            }

          if ($shippingCharges == 0) {
            $carriagePrice = 0;
          }
          $shipmentVat = $carriagePrice > 0 ? $shippingVat : null;
          $orderShipment[] = [
            'platformorderid' => $platformOrderId,
            'carriage_code' => $carriageCode,
            'carriage_price' => $carriagePrice,
            'cod_charge' => Currency::convertCurrencyRaw($codShipmentCharges[config('constant.checkout.FIRST_SHIPMENT_TYPE')]['fee'], $currency),
            'carriage_vat_percentage' => $shipmentVat,
            'shipment_num' => $userInfo['delivery_method']['shipment_type'],
            'shipment_weight' => $userInfo['delivery_method']['weight'] ?? 0,
            'user_id' => $userId,
            'status' => static::UNPROCESSED,
            'dispatched_date' => $dispatchedDate,
            'tentative_eta' => $tentativeDate,
            'shipment_price' => Currency::convertCurrencyRaw($shipmentTotal, $currency),
            'rule_id' => $userInfo['delivery_method']['carriage_id'] ?? null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
          ];
        }
        if (!empty($userInfo['collection_shipment'])) {
          $shipmentTotal = $this->calculateShipmentTotal($userInfo['collection_shipment']['products']);
            foreach ($userInfo['collection_shipment']['products'] as $p) {
              $shipmentsProducts[$p['product_id']] = 0;
            }
            $orderShipment[] = [
              'platformorderid' => $platformOrderId,
              'carriage_code' => '',
              'carriage_price' => '',
              'cod_charge' => '',
              'carriage_vat_percentage' => '',
              'shipment_num' => config('constant.checkout.SHIPMENT_COLLECTION_TYPE'),
              'shipment_weight' => $userInfo['collection_shipment']['weight'] ?? 0,
              'user_id' => $userId,
              'status' => static::UNPROCESSED,
              'dispatched_date' => '',
              'tentative_eta' => '',
              'shipment_price' => Currency::convertCurrencyRaw($shipmentTotal, $currency),
                    'rule_id' => null,
              'created_at' => Carbon::now(),
              'updated_at' => Carbon::now()
            ];
        }
        OrderShipment::insert($orderShipment);
        $orderShipment = OrderShipment::where('platformorderid', $platformOrderId)->get()->pluck('id', 'shipment_num')->toArray();
        return [
            'order_shipments' => $orderShipment,
            'shipments_products' => $shipmentsProducts
        ];
    }

    public function orderlines()
    {
        return $this->hasMany(OrderLine::class, 'order_shipment_id');
    }

    public function shipmenthistories()
    {
        return $this->hasMany(OrderShipmentHistory::class, 'original_shipment_id')->latest();
    }

    public function orderstatus()
    {
        return $this->belongsTo(OrderStatus::class, 'status', 'abbreviation');
    }

    public function calculateShipmentTotal($products) {
      return array_sum(array_map(function($value) {
        return $value['item_total'];
    }, $products));
    }

    public function orderinvoice()
    {
        return $this->hasMany(OrderInvoice::class, 'shipment_id', 'id');
    }
}
