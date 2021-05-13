<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UserAddress extends Model
{
    const APPROVED_STATUS = 1;
    const PENDING_STATUS = 2;
    const TYPE_SHIPPING = 1;
    const TYPE_BILLING = 2;
    const TYPE_SAME = 3;
    const IS_PRIME_YES = 1;
    const IS_PRIME_NO = 0;
    const SHIPPING_ADDRESS_TYPE = 1;
    const BILLING_ADDRESS_TYPE = 2;
    const TYPE_SHIPPING_TXT = 'shipping';
    const TYPE_BILLING_TXT = 'billing';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'type', 'is_prime', 'first_name', 'last_name', 'email', 'phone', 'addressline1', 'addressline2', 'state', 'city', 'country_id', 'postcode', 'trade_name', 'note_to_courier', 'status',
    ];

    public function country()
    {
        return $this->belongsTo('App\Models\Country')->select('id', 'country_name', 'country_3_code', 'country_2_code');
    }
    public static function defaultAddressExists($userId)
    {
        return static::where([
            'is_prime' => static::IS_PRIME_YES,
            'user_id' => $userId,
        ])->exists();
    }

    public function scopeTradeAddresses($query, $alias = 'user_addresses')
    {
        $query->when(config('wmo_website.type') == config('constant.trade_type'), function ($q) {
            $q->where('status', static::APPROVED_STATUS);
        });
    }

    public static function ifZipBlocked($address)
    {
        if (empty($address['shipping'])) {
            return false;
        }

        $where = [
            'country_id' => $address['shipping']['country_id'],
        ];

        $same_billing = $address['shipping']['same_for_billing'] ?? 0;
        $or_where = [];
        if (!isTradeSite() && $same_billing != 1 && !empty($address['shipping'])) {
            $or_where = [
                'country_id' => $address['billing']['country_id'],
            ];
        }

        $matchedResults = DB::table('block_zips')->where($where)->when($or_where, function ($q) use ($or_where) {
            $q->orWhere($or_where);
        })->get();

        $found = false;

        if (!empty($matchedResults)) {
            foreach ($matchedResults as $value) {

                $shippingBlocked = (strtoupper(preg_replace("/[^[:alnum:]]/u", '', $address['shipping']['addressline1'])) == strtoupper(preg_replace("/[^[:alnum:]]/u", '', $value->flat_no)) && strtoupper(preg_replace("/[^[:alnum:]]/u", '', $address['shipping']['postcode'])) == strtoupper(preg_replace("/[^[:alnum:]]/u", '', $value->block_zip)));

                $billingBlocked = (!isTradeSite() && strtoupper(preg_replace("/[^[:alnum:]]/u", '', $address['billing']['postcode'])) == strtoupper(preg_replace("/[^[:alnum:]]/u", '', $value->block_zip)) && strtoupper(preg_replace("/[^[:alnum:]]/u", '', $address['billing']['addressline1'])) == strtoupper(preg_replace("/[^[:alnum:]]/u", '', $value->flat_no)));

                if ($shippingBlocked || $billingBlocked) {
                    $found = true;
                    break;
                }
            }
        }
        return $found;
    }

    public static function saveAddress($data)
    {
        if ($data['addressType'] == UserAddress::TYPE_SHIPPING_TXT) {
            $data['type'] = UserAddress::TYPE_SHIPPING;
        } else {
            $data['type'] = UserAddress::TYPE_BILLING;
        }

        //update user address
        if ((int) $data['defaultAddress'] == 1 && (isset($data['is_prime']) && (int) $data['is_prime'] == UserAddress::IS_PRIME_YES)) {
            UserAddress::where([
                'user_id' => $data['user_id'],
                'type' => $data['type'],
            ])->update(['is_prime' => UserAddress::IS_PRIME_NO]);
        }
        $exists = UserAddress::where([
            'user_id' => $data['user_id'],
            'type' => $data['type'],
        ])->exists();
        if (empty($exists)) {
            $data['is_prime'] = UserAddress::IS_PRIME_YES;
        }
        UserAddress::create($data);
    }
}
