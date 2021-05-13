<?php

namespace App\Models\Order;

use App\Models\CMS\Website\WebsiteDeliveryLocation;
use Illuminate\Database\Eloquent\Model;
use App\Models\Country;

class OrderAddress extends Model
{
    const SAME_AS_SHIPPING = 3;
    const SHIPPING = 1;
    const BILLING = 2;
    const ISVERIFIED = 0;

    protected $fillable = [
        'platformorderid', 'order_id', 'type', 'first_name', 'last_name', 'email', 'addressline1', 'addressline2', 'addressline3', 'phone', 'city', 'post_code', 'state', 'country_id', 'is_verified', 'ip_address'
    ];

    public function saveAddress($params)
    {
        $platformOrderId = $params['platformorderid'];
        $userId = $params['user_id'];
        $userInfo = session('checkout');
        $shippingAddress = $userInfo['user_addresses']['shipping'] ?? [];

        OrderAddress::where('platformorderid',$platformOrderId)->delete();

        $params = [
            'country_id' => $shippingAddress['country_id']
        ];
        $country = Country::getCountry($params)->first()->toArray();
        // sanitize
        $firstName = $this->sanitizeFirstname($shippingAddress['first_name']);
        $lastName = $this->sanitizeLastname($shippingAddress['last_name']);
        $address1 = $this->sanitizeAddress1($shippingAddress['addressline1']);
        $address2 = $this->sanitizeAddress2($shippingAddress['addressline2']);
        $city = $this->sanitizeCity($shippingAddress['city'], $country['country_2_code']);
        $state = $this->sanitizeState($shippingAddress['state'], $country['country_2_code']);
        $postcode = $this->sanitizePostcode($shippingAddress['postcode']);

        if(preg_match('/^ ?[0-9]+ *$/', $address1) && preg_match('/^[^0-9]*$/', $address2)) {
            $address1 = $address1 . " " . $address2;
            $address2= "";
        }

        //insert orderAddress data
        if (isset($shippingAddress['same_for_billing']) && $shippingAddress['same_for_billing'] == 1) {

            $orderAddress = [
              'platformorderid' => $platformOrderId,
              'user_id' => $userId,
              'type' => static::SAME_AS_SHIPPING,
              'first_name' => $firstName,
              'last_name' => $lastName,
              'email' => $shippingAddress['email'],
              'addressline1' => $address1,
              'addressline2' => $address2,
              'addressline3' => '',
              'phone' => $shippingAddress['phone'],
              'city' => $city,
              'post_code' => $postcode,
              'state' => $state,
              'country_id' => $shippingAddress['country_id'],
              'is_verified' => static::ISVERIFIED,
              'ip_address' => config('wmo_website.user_ip')
            ];
        } else {

            $orderAddress[] = [
                'platformorderid' => $platformOrderId,
                'user_id' => $userId,
                'type' => static::SHIPPING,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $shippingAddress['email'],
                'addressline1' => $address1,
                'addressline2' => $address2,
                'addressline3' => '',
                'phone' => $shippingAddress['phone'],
                'city' => $city,
                'post_code' => $postcode,
                'state' => $state,
                'country_id' => $shippingAddress['country_id'],
                'is_verified' => static::ISVERIFIED,
                'ip_address' => config('wmo_website.user_ip')
            ];

            $billingAddress = $userInfo['user_addresses']['billing'] ?? [];
            if (!isTradeSite() && $billingAddress) {

                $params = [
                    'country_id' => $billingAddress['country_id']
                ];
                $country = Country::getCountry($params)->first()->toArray();
                $firstName = $this->sanitizeFirstname($billingAddress['first_name']);
                $lastName = $this->sanitizeLastname($billingAddress['last_name']);
                $address1 = $this->sanitizeAddress1($billingAddress['addressline1']);
                $address2 = $this->sanitizeAddress2($billingAddress['addressline2']);
                $city = $this->sanitizeCity($billingAddress['city'], $country['country_2_code']);
                $state = $this->sanitizeState($billingAddress['state'], $country['country_2_code']);
                $postcode = $this->sanitizePostcode($billingAddress['postcode']);
    
                if(preg_match('/^ ?[0-9]+ *$/', $address1) && preg_match('/^[^0-9]*$/', $address2)) {
                    $address1 = $address1 . " " . $address2;
                    $address2= "";
                }

                $orderAddress[] = [
                    'platformorderid' => $platformOrderId,
                    'user_id' => $userId,
                    'type' => static::BILLING,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $billingAddress['email'],
                    'addressline1' => $address1,
                    'addressline2' => $address2,
                    'addressline3' => '',
                    'phone' => $billingAddress['phone'],
                    'city' => $city,
                    'post_code' => $postcode,
                    'state' => $state,
                    'country_id' => $billingAddress['country_id'],
                    'is_verified' => static::ISVERIFIED,
                    'ip_address' => config('wmo_website.user_ip')
                ];
            }
        }

        OrderAddress::insert($orderAddress);
    }

    public function deliverylocation()
    {
        return $this->belongsTo(WebsiteDeliveryLocation::class, 'country_id', 'country_id');
    }

    public function sanitizeFirstname($firstName)
    {
        $firstName = preg_replace("/\./",". ",$firstName);
        $firstName = preg_replace("/-/","~-# ",$firstName);
        $firstName = stripslashes(ucwords(strtolower($firstName)));
        $firstName = preg_replace("/~-# /","-",$firstName);
        return preg_replace("/ +/"," ",$firstName);
    }

    public function sanitizeLastname($lastName)
    {
        $lastName = preg_replace("/\./",". ",$lastName);
        $lastName = preg_replace("/\'/","~# ",$lastName);
        $lastName = preg_replace("/^[Mm][Cc]/","~mc# ",$lastName);
        $lastName = preg_replace("/^[Mm][Aa][Cc]/","~mac# ",$lastName);
        $lastName = preg_replace("/-/","~-# ",$lastName);
        $lastName = stripslashes(ucwords(strtolower($lastName)));
        $lastName = preg_replace("/~mc# /","Mc",$lastName);
        $lastName = preg_replace("/~mac# /","Mac",$lastName);
        $lastName = preg_replace("/~-# /","-",$lastName);
        $lastName = preg_replace("/~# /","'",$lastName);
        return preg_replace("/ +/"," ",$lastName);
    }

    public function sanitizeAddress1($address1)
    {
        $address1 = preg_replace("/\./",". ",$address1);
        $address1 = preg_replace("/,/",", ",$address1);
        $address1 = stripslashes(ucwords(strtolower($address1)));
        return preg_replace("/ +/"," ",$address1);
    }

    public function sanitizeAddress2($address2)
    {
        $address2 = preg_replace("/\./",". ",$address2);
        $address2 = preg_replace("/,/",", ",$address2);
        $address2 = stripslashes(ucwords(strtolower($address2)));
        return preg_replace("/ +/"," ",$address2);
    }

    public function sanitizeCity($city, $countryCode)
    {
        $city = preg_replace("/\./",". ",$city);
        $city = preg_replace("/,/",", ",$city);
        if ( $countryCode == 'UK' ) {
            $city = preg_replace("/-([A-Za-z]{2,6})-/"," $1 ",$city);
        }
        $city = stripslashes(ucwords(strtolower($city)));
        if ( $countryCode == 'UK' ) {
            $city = preg_replace("/ On /"," on ",$city);
            $city = preg_replace("/ Of /"," of ",$city);
            $city = preg_replace("/ Under /"," under ",$city);
            $city = preg_replace("/ Upon /"," upon ",$city);
            $city = preg_replace("/ By /"," by ",$city);
            $city = preg_replace("/ In /"," in ",$city);
            $city = preg_replace("/ Over /"," over ",$city);
            $city = preg_replace("/ The /"," the ",$city);
            $city = preg_replace("/ Le /"," le ",$city);
        }
        return preg_replace("/ +/"," ",$city);
    }

    public function sanitizeState($state, $countryCode)
    {
        $state = preg_replace("/\./",". ",$state);
        $state = stripslashes(ucwords(strtolower($state)));
        $state = preg_replace("/ +/"," ",$state);
        if ( $countryCode == 'UK' ) {
            $state = preg_replace("/ Of /"," of ",$state);
        }
        return $state;
    }

    public function sanitizePostcode($postcode)
    {
        $postcode = stripslashes(strtoupper($postcode));
        return preg_replace('/^([A-Z]{1,2}[0-9]{1,3}[A-Z]?) ?([0-9][A-Z]{2})$/',"\\1 \\2",$postcode);
    }

}
