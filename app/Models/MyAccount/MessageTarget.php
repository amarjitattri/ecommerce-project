<?php

namespace App\Models\MyAccount;

use App\Mail\MessageCentreMail;
use App\Models\MyAccount\MessageEvent;
use App\Models\MyAccount\MessageTemplate;
use App\Models\MyAccount\UserEmailPreference;
use App\Models\Order\Order;
use App\Models\Order\OrderAddress;
use App\Models\Order\OrderInvoice;
use App\Models\Order\OrderLine;
use App\Models\Order\OrderShipment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use PDF;
use App\Models\User;

class MessageTarget extends Model
{
    const UPDATED_AT = null;
    const PLACEMENT = 3;
    const COLLECTION = 'Collection';
    const SHIPMENT = 'Shipment';

    protected $fillable = [
        'template_id', 'user_id', 'is_email_sent', 'is_seen', 'content',
    ];

    public function template()
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

    public function scopeWhereAuthUser($query)
    {
        return $query->where('user_id', Auth::user()->id);
    }

    public static function sendOrderPlacementMessage($email, $order)
    {
        $template = MessageTemplate::from('message_templates as mt')
            ->select('mt.subject', 'mt.content', 'mt.id', 'is_optional')
            ->where([
                'mt.website_id' => config('wmo_website.website_id'),
                'mt.event_id' => MessageEvent::ORDER_PLACEMENT,
            ])->join('message_events as me', 'me.id', 'mt.event_id')
            ->languageJoin('mt')->first();

        if (!$template) {
            return false;
        }

        $send_email = true;
        if ($template->is_optional && Auth::check()) {
            $send_email = UserEmailPreference::isPreferedEvent(Auth::user()->id, MessageEvent::ORDER_PLACEMENT);
        }
        $order['orderAddress'] = OrderAddress::where('platformorderid', $order['order_data']['platformorderid'])->get()->toArray();
        $template['content'] = static::__changetemplateCodes($order, MessageEvent::ORDER_PLACEMENT, $template->content);
        if ($send_email) {
            $pdf = array();
            $userId = Order::select('user_id')->where('platformorderid',$order['order_data']['platformorderid'])->get()->first(); 
            //session()->get('checkout.user_info')
            if(isset($userId->user_id))
            {
                $userInfo = User::select('email','vat_number','tax_code','is_business')->where('id',$userId->user_id)->get()->first();
            }
            $webType = config('wmo_website.website_code');
            
            $userGuest = array();
            if (isset($userInfo['is_business']) && $userInfo['is_business'] == 1 && $webType == 'IT') {
                $userGuest = array(
                    'email' => $userInfo['email'],
                    'vat_number' => $userInfo['vat_number'],
                    'tax_code' => $userInfo['tax_code'],
                    'is_business' => $userInfo['is_business'],
                );
            }
            $order['userGuest'] = $userGuest;
            foreach ($order['order_shipments'] as $shipment) {
                $order['invoice'] = OrderInvoice::getInvoiceData($shipment)->get()->first();
                $order['oderLineData'] = OrderLine::where(['platformorderid' => $order['order_data']['platformorderid'], 'order_shipment_id' => $shipment])->with(['model', 'ordershipment'])->get();
                $orderShipmentData = OrderShipment::select('carriage_price', 'carriage_vat_percentage')->where('id', $shipment)->get()->first();
                $vatDivisor = 1 + round($orderShipmentData['carriage_vat_percentage'] / 100, 2);
                $priceBeforeVat = round($orderShipmentData['carriage_price'] / $vatDivisor, 2);
                $vatAmount = $orderShipmentData['carriage_price'] - $priceBeforeVat;
                $customPaper = array(0, 0, 567.00, 1000.80);
                if (config('wmo_website.type') == 1) {
                    $vatNumber = Auth()->user()->vat_number;

                    $pdf[] = PDF::loadView('emails.b2bpdf', compact('order', 'vatAmount', 'vatNumber','userInfo'))->setPaper($customPaper, 'portrait');
                } else {
                    $pdf[] = PDF::loadView('emails.pdf1', compact('order', 'vatAmount'))->setPaper($customPaper, 'portrait');
                }
            }
            $template['pdf'] = $pdf;
            Mail::to($email)->send(new MessageCentreMail($template));
        }

        if (Auth::check()) {
            static::create([
                'template_id' => $template->id,
                'user_id' => Auth::user()->id,
                'is_email_sent' => $send_email ? 1 : 0,
                'is_seen' => 0,
                'content' => $template['content'],
            ]);
        }
    }

    /*change sort codes */
    public static function __changetemplateCodes($order, $eventId, $template)
    {
        $messageCodes = MessageEvent::select('short_code')->where('id', $eventId)->first();
        $shortCodes = (explode(',', trim($messageCodes->short_code)));
        $platformorderid = $order['platformorderid'];
        $orderData = Order::select(
            'orders.first_name',
            'orders.last_name',
            'orders.language_id',
            'orders.email',
            'orders.total',
            'op.type',
            'orders.created_at',
            'c.symbol_entity',
            'w.hostname'
        )
            ->where('orders.platformorderid', $platformorderid)
            ->join('currencies as c', 'c.id', 'orders.currency_id')
            ->join('order_payment_details as op', 'op.platformorderid', 'orders.platformorderid')
            ->join('websites as w', 'w.id', 'orders.website_id')
            ->first();
        $product_url = $orderData->hostname . '/parts/';
        $orderDetailUrl = $orderData->hostname . '/user/order-history/' . $platformorderid . '/edit';

        $langId = $orderData->language_id ?? 1;
        //get all shipment wise order details
        $orderShipmentData = OrderShipment::with([
            'orderlines' => function ($q) use ($langId) {
                $q->emailProductList(['langId' => $langId]);
            }])->where('platformorderid', $platformorderid)->get();
        $productListHtml = '';

        $address = ['shipping' => '', 'billing' => ''];
        
        
        foreach ($order['orderAddress'] as $key => $row) {
            $address[$key] = $row['state'] . '<br/> ' . $row['city'] . '<br/> ' . $row['post_code'] . '<br/> ' . $row['addressline1'];
            if ($row['addressline2']) {
                $address[$key] .= '<br/> ' . $row['addressline2'];
            }
            if($row['type']==3 || $row['type']==2)
            {
                $address['shipping'] = $address[$key];
            }
            if ($row['type']==3 || $row['type']==1) {
                $address['billing'] = $address[$key];
                break;
            }
        }
        $productIds = [];
        foreach ($orderShipmentData as $shipment) {
            $price_total = $vat_total = $promo_discount = $total = 0;
            if ($shipment->shipment_num == 0) {
                $shipmentNum = static::COLLECTION;
            } else {
                $shipmentNum = static::SHIPMENT . ' ' . $shipment->shipment_num;
            }
            $productListHtml .= '<tr><td colspan="3" height="15"><table width="546" border="0" align="center" cellpadding="0" cellspacing="0"><tr><td colspan="3"><table width="546" border="0" align="center" cellpadding="0" cellspacing="0"><tr><td colspan="3" style="font-family:Arial, Helvetica, sans-serif; font-size: 14px; color: #333333;" valign="top"><p style="font-family:Arial, Helvetica, sans-serif; font-size: 14px; color: #333333; margin-bottom: 5px;"><strong>We are sending your order to:</strong></p><p style="font-family:Arial, Helvetica, sans-serif; font-size: 14px; color: #333333;">' . $address['shipping'] . '</p></td> <td colspan="3" style="font-family:Arial, Helvetica, sans-serif; font-size: 14px; color: #333333;" valign="top"><p style="font-family:Arial, Helvetica, sans-serif; font-size: 14px; color: #333333; margin-bottom: 5px;"><strong>The estimated delivery is:</strong></p><p style="font-family:Arial, Helvetica, sans-serif; font-size: 14px; color: #333333;">27/10/2020</p><p style="font-family:Arial, Helvetica, sans-serif; font-size: 14px; color: #333333; margin-top: 20px; margin-bottom: 5px;"><strong>Delivery Option</strong></p><p style="font-family:Arial, Helvetica, sans-serif; font-size: 14px; color: #333333;">' . $shipment->carriage_code . '</p></td></tr></table></td></tr><tr><td colspan="3" height="25"></td></tr><tr><td colspan="3" style="font-family:Arial, Helvetica, sans-serif; font-size: 16px; color: #333333;"><strong>Your order ' . $shipmentNum . ' Summary</strong></td></tr></table></td></tr><tr><td colspan="3" height="15"></td></tr><tr><td colspan="3"><table width="546" border="0" align="center" style="table-layout:fixed;" cellpadding="0" cellspacing="0"><tr><td width="10"></td><td width="68" style="font-family:Arial, Helvetica, sans-serif; font-size: 12px; font-weight:bold; color: #343434;"> Order Items</td><td width="200" style="font-family:Arial, Helvetica, sans-serif; font-size: 12px; font-weight:bold; color: #343434;"></td><td align="center"style="font-family:Arial, Helvetica, sans-serif; font-size: 12px; font-weight:bold; color: #343434;"> Qty.</td><td align="right"style="font-family:Arial, Helvetica, sans-serif; font-size: 12px; font-weight:bold; color: #343434;"> Total</td><td width="20"></td></tr>';
            foreach ($shipment->orderlines as $products) {

                $productListHtml .= static::getProductHTML($products, $orderData->symbol_entity);
                $productIds[] = $products->product_id;
                $price_total += $products['total_price'];
                $vat_total += $products['vat'];
                $promo_discount += $products['promo_discount'];

            }
            //shipping vat code start
            $vatPer= app('CartHelper')->calculateVatInPrice($shipment->carriage_vat_percentage, $shipment->carriage_price);
            $finalVat = round(($vat_total+$vatPer),2);
            //shipping vat code end
            $shipping = $shipment->carriage_price;
            $codCharges = $shipment->cod_charge;

            $total = ($price_total + $shipping + $codCharges) - $promo_discount;
            $productListHtml .= '</table></td></tr><tr><td colspan="3"><table width="546" border="0" align="center" cellpadding="0" cellspacing="0" style="table-layout:fixed;"><tr><td align="right"  style="font-family:Arial, Helvetica, sans-serif; font-size: 14px; color: #343434; line-height: 20px;font-weight:bold;"> Shipping</td>  <td align="right" width="60" style="font-family:Arial, Helvetica, sans-serif; font-weight:bold; font-size: 14px; color: #343434;">' . $orderData->symbol_entity . $shipping . '</td><td width="20"></td></tr><tr><td align="right"  style="font-family:Arial, Helvetica, sans-serif; font-size: 14px; color: #343434; line-height: 20px;font-weight:bold;"> VAT</td><td align="right" width="60" style="font-family:Arial, Helvetica, sans-serif; font-weight:bold; font-size: 14px; color: #343434;"> ' . $finalVat . '</td><td width="20"></td></tr><tr><td align="right" style="font-family:Arial, Helvetica, sans-serif; font-size: 14px; color: #343434; line-height: 20px;font-weight:bold;"> Grand Total</td><td align="right" width="60" style="font-family:Arial, Helvetica, sans-serif; font-weight:bold; font-size: 14px; color: #343434;">' . $orderData->symbol_entity . $total . '</td><td width="20"></td></tr></table></td></tr><tr><td colspan="3" height="10"></td></tr>';

        }

        $relatedProduct = '';
        if (!empty($productIds)) {
            $relatedProduct = app('CommonHelper')->relatedProductHTML($productIds, ['product_url' => $product_url, 'currency' => $orderData->symbol_entity]);
        }

        $replace_by['{customer_name}'] = $orderData->first_name . ' ' . $orderData->last_name;
        $replace_by['{customer_email}'] = $orderData->email;
        $replace_by['{order_number}'] = $order['order_data']['prefix'] . $order['order_data']['order_id'];
        $replace_by['{order_pay_method}'] = $orderData->type;
        $replace_by['{order_billing_address}'] = $address['billing'];
        $replace_by['{order_shipping_address}'] = $address['shipping'];
        $replace_by['{amount_spent_order}'] = $orderData->symbol_entity . $order['cart']['total'];
        $replace_by['{product_list_html}'] = $productListHtml;
        $replace_by['{related_product}'] = $relatedProduct;
        $replace_by['{order_detail_url}'] = $orderDetailUrl;
        $onlyTemplateShortCodes = array_intersect_key($replace_by, array_flip($shortCodes));
        return strtr($template, $onlyTemplateShortCodes);
    }

    public static function quoteEmailSend($emailData = array())
    {
        $template = (object) array(
            'content' => 'Wemoto has shared the quote with you. <a href = "' . $emailData['link'] . '">click here </a>',
            'subject' => 'Quote',
        );
        Mail::to($emailData['email'])->send(new MessageCentreMail($template));
    }

    public static function getProductHTML($product, $currency)
    {
        $bike_model = (!empty($product->make_name)) ? $product->make_name . ' ' . $product->model_name . ' ' . $product->year : '';
        $productImgSrc = "";
        if (isset($product->product_image) && !empty($product->product_image)) {
            $productImgSrc .= config('constant.PRODUCT_PATH') . $product->product_image;
        } else {
            $productImgSrc .= config('constant.PRODUCT_NO_IMG');
        }
        return '<tr bgcolor="#fdfdd8">
            <td colspan="6" height="7"></td>
        </tr>
        <tr bgcolor="#fdfdd8">
            <td width="10"></td>
            <td width="68"><span style="width: 52px; height: 52px; display: table-cell; text-align: center; border: solid 1px #eeeeee; vertical-align:middle; background: #ffffff;"> <img width="41" src="' . $productImgSrc . '" alt="item" /> </span></td>
            <td width="200">
                <span style="font-family:Arial, Helvetica, sans-serif; font-size: 13px; color: #343434;">[' . $product->code . ']
                ' . $bike_model . '<br/>
                ' . $product->customer_description . '</span>
            </td>
            <td align="center" style="font-family:Arial, Helvetica, sans-serif; font-size: 13px; color: #343434;">' . $product->quantity . '</td>
            <td align="right" style="font-family:Arial, Helvetica, sans-serif; font-size: 13px; font-weight:bold; color: #343434;">
                <strong>' . $currency . $product->total_price . '</strong>
            </td>
            <td width="20"></td>
        </tr>
        <tr bgcolor="#fdfdd8">
            <td colspan="6" height="7"></td>
        </tr>
        <tr>
            <td colspan="6" height="6"></td>
        </tr>';

    }

}
