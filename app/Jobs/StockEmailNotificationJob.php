<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use App\Mail\StockNotificationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class StockEmailNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;
    protected $description = [
        1 => 'Stock Zero',
        2 => 'Stock One',
        3 => 'Stock Threshold',
        6 => 'Retail',
        8 => 'Global',
        5 => 'Trade',
    ];
    const STOCK_0 = 1;
    const STOCK_1 = 2;
    const STOCK_THRESHOLD = 3;
    const STOCK_RETAIL = 6;
    const STOCK_GLOBAL = 8;
    const TRADE = 5;

    const B_TO_C = 'BC';
    const B_TO_B = 'BB';
    const LOCALSHOP = 'LS';
    const TRADE_B2B = 'Trade';

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $product_ids = [];
        $products = [];
        foreach ($this->data['products'] as $product) {
            $products[$product['product_id']] = $product;
            $product_ids[] = $product['product_id'];
        }

        $results = DB::table('websites as w')->select('pn.id', 'pn.product_id', 'pn.events',
            'pn.email', 'pn.email_content', 'f.name', 'w.website_name', 's.threshold', 'p.code')
            ->join('wmbackend_franchises as f', 'f.id', '=', 'w.franchise_id')
            ->join('product_user_notifications as pun', 'pun.franchise_id', '=', 'f.id')
            ->join('product_notifications as pn', 'pun.notification_id', '=', 'pn.id')
            ->join('products as p', 'pn.product_id', '=', 'p.id')
            ->join('product_flat_stocks as s', function ($q) {
                $q->on('s.product_id', '=', 'pn.product_id')->on('s.website_id', '=', 'w.id');
            })->where('w.id', $this->data['website_id'])->whereIn('pn.product_id', $product_ids)
            ->whereIn('pn.events', [
                static::STOCK_0, static::STOCK_1, static::STOCK_THRESHOLD, static::STOCK_RETAIL, static::STOCK_GLOBAL,static::TRADE
            ])->get()->toArray();

        $to_be_replace = ['{orderid}', '{ordersource}', '{franchise}', '{productcode}', '{orderqty}', '{stockqty}'];
        $website = '';
        if ($this->data['order_data']['prefix'] == static::B_TO_C) {
            $website = 'B2C Website - ';
        } elseif ($this->data['order_data']['prefix'] == static::LOCALSHOP) {
            $website = 'Local Shop - ';
        } elseif ($this->data['order_data']['prefix'] == static::B_TO_B) {
            $website = 'Global - ';
        } elseif ($this->data['order_data']['prefix'] == static::TRADE_B2B) {
            $website = 'Trade - ';
        }

        foreach ($results as $row) {
            if (!$this->isElgible($row, $products)) {
                continue;
            }

            $replace_by = [];
            $replace_by[] = $this->data['order_data']['order_id'];
            $replace_by[] = $website . $row->website_name;
            $replace_by[] = $row->name;
            $replace_by[] = $row->code;
            $replace_by[] = $products[$row->product_id]['qty'];
            $replace_by[] = $products[$row->product_id]['stock'] ?? 0;
            $row->email_content = str_replace($to_be_replace, $replace_by, $row->email_content);
            
            $row->subject = 'Stock Notification - ' . $this->description[$row->events];
            Mail::to(explode(',', $row->email))->send(new StockNotificationMail($row));
        }
    }

    private function isElgible($row, $products)
    {
        switch ($row->events) {
            case static::STOCK_0:
                $flag = !is_null($products[$row->product_id]['stock']) && $products[$row->product_id]['stock'] == 0;
                break;
            case static::STOCK_1:
                $flag = $products[$row->product_id]['stock'] == 1;
                break;
            case static::STOCK_THRESHOLD:
                $flag = !is_null($products[$row->product_id]['stock']) && $products[$row->product_id]['stock'] < $row->threshold;
                break;
            case static::STOCK_RETAIL:
                $flag = in_array($this->data['order_data']['prefix'], [static::B_TO_C, static::LOCALSHOP]);
                break;
            case static::STOCK_GLOBAL:
                $flag = in_array($this->data['order_data']['prefix'], [static::B_TO_C, static::LOCALSHOP, static::B_TO_B]);
                break;
            case static::TRADE:
                $flag = in_array($this->data['order_data']['prefix'], [static::LOCALSHOP, static::B_TO_B]);
                break;
            default:
                $flag = false;
        }

        return $flag;
    }

}
