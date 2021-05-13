<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\Order\WebsiteInvoiceId;
use App\Models\Catalog\Promocode\Promocode;
use App\Models\Order\ExchangeOrder;

class OrderInvoice extends Model
{
    const INTERNET = 'IN';
    const TELEPHONIC = 'LS';
    const COLLECTION = 'LS';
    const QUOTE = 'LS';
    const SHOWROOM = 'SR';
    const SERVICE = 'CS';
    const EXCHANGE ='EX';
    public function saveInvoice($params)
    {
        $platformOrderId = $params['platformorderid'];
        $shipmentsDetails = $params['order_shipments'];
        $token = $params['cart']['token'];
        $websiteId = config('wmo_website.website_id');
        $websiteCode = config('wmo_website.website_code');
        $now = Carbon::now();

        $typeCode = static::INTERNET;
        if (!empty(request()->session()->get('local_shop_user'))) {
            $contantName = strtoupper(session('localShopOrderType'));
            $typeCode = constant("static::$contantName");
        }
        if (isset($params['cart']['promotion']['promocode'])) {
            $typeCode = $this->exchnageInvoice($params['cart']['promotion']['promocode'], $typeCode);
        }
    
        //insert website order id
        
        
        OrderInvoice::where('platformorderid', $platformOrderId)->delete();
        foreach ($shipmentsDetails as $shipment) {
            $websiteInvoiceData = ['website_id' => $websiteId, 'token' => $token];
            $tableName = strtolower($websiteCode).'_invoice_ids';
            $invoiceModelName = new WebsiteInvoiceId;
            $invoiceModelName->setTable($tableName);
            $websiteInvoiceId = $invoiceModelName->create(['website_id' => $websiteId, 'token' => $token], $websiteInvoiceData);
            $invoiceId = $websiteCode.$typeCode.$now->format('y').sprintf('%06d', $websiteInvoiceId->id);

            $invoiceNumber = $invoiceId;
            $invoiceData[] = [
            'platformorderid' => $platformOrderId,
            'shipment_id' => $shipment,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => Carbon::now()
          ];
        }
        OrderInvoice::insert($invoiceData);
    }

    public static function scopeGetInvoiceData($query, $shipmentId)
    {
        $query->select('order_invoices.invoice_number', 'os.carriage_code', 'opd.transaction_id', 'opd.type')
          ->leftJoin('order_shipments as os', 'os.platformorderid', '=', 'order_invoices.platformorderid')
          ->leftJoin('order_payment_details as opd', 'opd.platformorderid', '=', 'order_invoices.platformorderid')
          ->where('order_invoices.shipment_id', $shipmentId);
          
        return $query;
    }
    
    public function exchnageInvoice($promocode, $typeCode)
    {
        $pattern = "/EXPR/i";
        if (preg_match($pattern, $promocode)) {
            $promoData = Promocode::select('id')->where('promocode', $promocode)->get()->first();
            if (ExchangeOrder::select('id')->where('promo_code_id', $promoData['id'])->exists()) {
                $typeCode = static::EXCHANGE;
            }
        }
        return $typeCode;
    }
}
