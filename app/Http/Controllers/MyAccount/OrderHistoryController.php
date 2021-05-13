<?php

namespace App\Http\Controllers\MyAccount;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Repositories\Myaccount\Interfaces\OrderHistoryRepositoryInterface;
use PDF;

class OrderHistoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(OrderHistoryRepositoryInterface $orderHistory)
    {
        $orders = $orderHistory->getOrders();
        $sidebar = 10;
        $websiteFranchise = config('wmo_website.franchise');
        if (request()->ajax()) {
            return [
                'posts' => view('myaccount.order-history.ajax.index')->with(compact('orders', 'websiteFranchise'))->render(),
                'next_page' => $orders['next_page_url']
            ];
        }
        return view('myaccount.order-history.index', compact('sidebar', 'orders', 'websiteFranchise'));
    }

    
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * This is used for download pdf
     */
    public function show($id, OrderHistoryRepositoryInterface $orderHistory)
    {
        $orderPdf = $orderHistory->getOrderByPlatformIdPDF($id);
        if (count($orderPdf['orderaddress']) == 1) {
            $shipping = isset($orderPdf['orderaddress'][0]) ? $orderPdf['orderaddress'][0] : null;
            $billing = isset($orderPdf['orderaddress'][0]) ? $orderPdf['orderaddress'][0] : null;
        } else {
            $shipping = isset($orderPdf['orderaddress'][0]) ? $orderPdf['orderaddress'][0] : null;
            $billing = isset($orderPdf['orderaddress'][1]) ? $orderPdf['orderaddress'][1] : null;
        }
        $pdf = PDF::loadView('myaccount.order-history.pdf.invoice', compact('orderPdf', 'shipping', 'billing'));
        return $pdf->download('invoice.pdf');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id, OrderHistoryRepositoryInterface $orderHistory)
    {
        $order = $orderHistory->getOrderByPlatformId($id);
        
        if (count($order['orderaddress']) == 1) {
            $shipping = isset($order['orderaddress'][0]) ? $order['orderaddress'][0] : null;
            $billing = isset($order['orderaddress'][0]) ? $order['orderaddress'][0] : null;
        } else {
            $shipping = isset($order['orderaddress'][0]) ? $order['orderaddress'][0] : null;
            $billing = isset($order['orderaddress'][1]) ? $order['orderaddress'][1] : null;
        }
       
        $productData = $this->modifyOrderlines($order);
       
        $statuses = [
            'UP' => 'Order Confirmed',
            'DP' => 'Dispatched',
            'DD' => 'Delivered',
        ];
        $cancelStatuses = [
            'UP' => 'Order Confirmed',
            'CL' => 'Cancelled',
        ];
        $applicableStatus = [
            'DP',
            'CL',
            'DD'
        ];
        
        return view('myaccount.order-history.details', compact('order', 'shipping', 'billing', 'productData', 'statuses', 'cancelStatuses', 'applicableStatus'));
    }

    public function modifyOrderlines($order)
    {
        foreach ($order['shipments'] as $shipment) {
            if ($shipment['added_at'] == 1) {
                foreach ($shipment['orderlines'] as $orderline) {
                    $products[] = $orderline['product'];
                    if ($shipment['shipment_num'] == 1 || $shipment['shipment_num'] == 2) {
                        $shipmentGroup[$shipment['shipment_num']][] = $orderline['product'];
                    } else {
                        $shipmentGroup = [];
                    }
                }
            }
        }
        
        return [
            'products' => $products,
            'shipment_group' => $shipmentGroup
        ];
    }
}
