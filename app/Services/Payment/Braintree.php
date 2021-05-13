<?php
namespace App\Services\Payment;

use Braintree_Transaction;
use App\Models\Order\OrderPaymentDetail;
use App\Models\Order\Order;
use App\Models\Order\OrderLocalShopUser;
use App\Models\Order\OrderLocalShopOtherThings;
use Currency;
use Session;
use App\Services\Payment\Contracts\PaymentContracts;

class Braintree implements PaymentContracts
{
    const UNPROCESSED = 'UP';

    public function processPayment($orderData)
    {
        $request = request()->all();
        $nonceFromTheClient = $request['payment_method_nonce'];
        $currency = session('currency');
        $order = $orderData['order_data'];

        $cart = $orderData['cart'];

        $userInfo = session('checkout');
        $shippingAddress = $userInfo['user_addresses']['shipping'] ?? [];
        $orderFirstName = $shippingAddress['first_name'] ?? '';
        $orderLastName = $shippingAddress['last_name'] ?? '';
        $orderEmail = $userInfo['user_info']['email'];
        $orderPhone = $shippingAddress['phone'] ?? '';
        $orderId = $order['order_id'];

        $result = Braintree_Transaction::sale([
            'amount' => Currency::convertCurrencyRaw($cart['total'], $currency),
            'orderId' =>$orderId,
            'merchantAccountId' => '',
            'paymentMethodNonce' => $nonceFromTheClient,
            'deviceData' => '',
            'customer' => [
              'firstName' => $orderFirstName,
              'lastName' => $orderLastName,
              'company' => '',
              'phone' => $orderPhone,
              'fax' => '',
              'website' => '',
              'email' => $orderEmail
            ],
            'options' => [
              'submitForSettlement' => true
            ]
        ]);

        if ($result->success || !is_null($result->transaction)) {
            $transaction = $result->transaction;

            //update payment status
            Order::where('platformorderid', $order['platformorderid'])->update(
                [
                    'order_status' => static::UNPROCESSED
                ]
            );

            //check if payment already done

            //insert data in payment details page
            $orderPaymentDetails = [
              'platformorderid' => $order['platformorderid'],
              'transaction_id' =>  $transaction->id,
              'token' =>  $transaction->processorAuthorizationCode,
              'type' =>  config('constant.payment.braintree.'.$transaction->paymentInstrumentType),
              'data_capture' => json_encode($transaction),
              'capture_date' => $transaction->updatedAt,
              'status' => $transaction->status
            ];

            Session::put('payment', array_merge(array('order_id' => $orderId), $orderPaymentDetails));
            OrderPaymentDetail::create($orderPaymentDetails);
            return true;
        }
    }
}
