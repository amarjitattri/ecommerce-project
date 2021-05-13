<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

use Currency;
use App\Models\Order\OrderLineVariant;
use App\Models\Order\ExchangeProduct;
use App\Models\Order\OrderShipment;
use App\Models\Order\OrderShipmentHistory;
use App\Models\Order\OrderReturn;
use App\Models\Catalog\Product\Product;
use App\Models\CMS\BikeModel;
use App\Models\Catalog\DescriptionAssociation;
use App\Models\Order\OrderRefundDetail;
use App\Models\Locale\LocaleDynamicContent;
use DB;
class OrderLine extends Model
{
    protected $fillable = [
        'id', 'platformorderid', 'product_id', 'order_shipment_id', 'category_id', 'model_id', 'assoc_desc_id', 'quantity', 'unit_price', 'total_price', 'description', 'product_status', 'promo_applicable', 'promo_discount', 'exchange_item', 'vat','code'
    ];

    public function saveOrderlines($params)
    {
        $cart = $params['cart'];
        $calculatedOrderLines = $params['orderlines'];
        $shipmentsProducts = $params['shipments_products'];
        $shipmentsDetails = $params['order_shipments'];

        $currency = session('currency');
        $platformOrderId = $params['platformorderid'];
        $promoApplicable = 0;
        $promocodeDiscount = '';

        OrderLine::where('platformorderid',$platformOrderId)->delete();
        OrderLineVariant::where('platformorderid',$platformOrderId)->delete();
        if (isset($cart['promotion']) && $cart['promotion']['status'] && $cart['promotion']['promocode']) {
            $promoApplicable = 1;
        }
        $shipmentProducts = array_map(function($value) use($shipmentsDetails) {
            return $shipmentsDetails[$value];
        },$shipmentsProducts);
        foreach($calculatedOrderLines as $product) {
            $order_shipment_id = $shipmentProducts[$product['product_id']];
            $promocodeDiscount = isset($product['promo_discount']) ? round($product['promo_discount'], 2) : 0;
            $unitPrice = $product['price'];
            $orderLines = [
              'platformorderid' => $platformOrderId,
              'product_id' => $product['product_id'],
              'code' => $product['frontend_code'] ?? $product['product_code'],
              'order_shipment_id' => $order_shipment_id,
              'category_id' => $product['category_id'],
              'model_id' => $product['model_id'],
              'assoc_desc_id' => $product['assoc_id'],
              'quantity' => $product['qty'],
              'unit_price' => Currency::convertCurrencyRaw($unitPrice, $currency),
              'total_price' => (Currency::convertCurrencyRaw($product['qty'] * $unitPrice, $currency)),
              'description' => $product['product_name'],
              'product_status' => 1,
              'promo_applicable' => $promoApplicable,
              'promo_discount' => Currency::convertCurrencyRaw($promocodeDiscount, $currency),
              'vat' => $product['vat'] ?? 0,
              'other_model' => (isset($product['other_model']) && !empty($product['other_model'])) ?? $product['other_model'],
          ];
          $orderLineData = OrderLine::create($orderLines);

          //insert data in kit varient
          if ($product['type'] == 3) {
              foreach($product['product_kit_items'] as $pKit) {
                $productVarient = [
                  'platformorderid' => $platformOrderId,
                  'orderline_id' => $orderLineData->id,
                  'product_id' => $product['product_id'],
                  'varient_id' => $pKit['prdId'],
                  'variant_quantity' => $pKit['quantity'],
                  'quantity' => $pKit['quantity'] * $product['qty']
                ];
                OrderLineVariant::create($productVarient);
              }
          }
        }
    }

    public function ordershipment()
    {
        return $this->belongsTo(OrderShipment::class, 'order_shipment_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function model()
    {
        return $this->belongsTo(BikeModel::class, 'model_id');
    }

    public function assocation()
    {
        return $this->belongsTo(DescriptionAssociation::class, 'assoc_desc_id');
    }

    public function exchangeproduct()
    {
        return $this->hasMany(ExchangeProduct::class, 'order_line_id')
            ->selectRaw('exchange_order_id, order_line_id, SUM(qty) as qty')
            ->groupBy('order_line_id');
    }

    public function ordershipmenthistory()
    {
        return $this->hasMany(OrderShipmentHistory::class, 'order_line_id', 'id');
    }

    public function returnproduct()
    {
        return $this->hasMany(OrderReturn::class, 'order_line_id')
            ->selectRaw('id, order_line_id, SUM(returned_quantity) as qty')
            ->groupBy('order_line_id');
    }

    public function refund()
    {
        return $this->hasMany(OrderRefundDetail::class, 'module_id')->where('module_type','=', 2);
    }

    public static function scopeEmailProductList($q,$param){
        return $q->select('order_lines.id','order_lines.quantity', 'order_lines.unit_price', 'order_lines.total_price', 'order_lines.order_shipment_id','order_lines.code as ol_code','order_lines.platformorderid','order_lines.vat','order_lines.promo_discount', 'p.id as product_id', 'p.code', DB::raw('IF(lcd.content <> "" OR lcd.content IS NOT NULL, lcd.content, p.customer_description) as customer_description'), 'pi.name as product_image', 'm.name as model_name', 'm.year', 'mk.name as make_name')
        ->leftJoin('products as p', 'p.id', '=', 'order_lines.product_id')
        ->leftJoin('locale_dynamic_contents as lcd', function ($qr) use ($param) {
            $qr->on('lcd.type_id', '=', 'p.id')
                ->where('lcd.type', LocaleDynamicContent::CUSTOMER_DESCRIPTION)
                ->where('lcd.language_id', $param['langId']);
        })
        ->leftJoin('product_images as pi', function ($join) {
            $join->on('pi.product_id', '=', 'p.id');
            $join->where('pi.type', '=', 1);
        })
        ->leftJoin('model as m', 'm.id', '=', 'order_lines.model_id')
        ->leftJoin('make as mk', 'mk.id', '=', 'm.make_id');
    }

}
