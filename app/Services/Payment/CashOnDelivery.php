<?php
namespace App\Services\Payment;

use App\Services\Payment\Contracts\PaymentContracts;
use App\Models\Order\Order;
use App\Models\Order\OrderPaymentDetail;
use Session;

class CashOnDelivery implements PaymentContracts
{
    const UNPROCESSED = 'UP';

    public function processPayment($orderData)
    {
        $order = $orderData['order_data'];
        $orderId = $order['order_id'];

        //update payment status
        Order::where('platformorderid', $order['platformorderid'])->update(
            [
                'order_status' => static::UNPROCESSED
            ]
        );

        //check if payment already done
        $params = [
            'platformorderid' => $order['platformorderid']
        ];
        
        if (OrderPaymentDetail::checkPaymentExists($params)) {
            return config('constant.payment_exists');
        }

        //insert data in payment details page
        $orderPaymentDetails = [
          'platformorderid' => $order['platformorderid'],
          'type' =>  config('constant.payment.cod'),
          'status' => 'pending'
        ];
        
        Session::put('payment', array_merge(array('order_id' => $orderId), $orderPaymentDetails));
        OrderPaymentDetail::create($orderPaymentDetails);
        return true;
    }
}
