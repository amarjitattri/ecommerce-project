<?php

namespace App\Models;

use App\Models\Trade\TraderDetail;
use App\Models\Trade\TraderPersonnelDetail;
use App\Models\Trade\TraderReference;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use DB;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;
    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 0;
    const STATUS_PENDING = 2;
    const USER_TYPE = 0;
    const TRADER_TYPE = 1;
    const VERIFIED = 1;
    const RAGISTER_VIA_SITE = 1;
    const NOT_VERIFIED = 0;
    const EMAIL_EXIST='EMAIL_EXIST';
    const UNIQEID_EXIST='UNIQEID_EXIST';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name', 'last_name', 'email', 'dob', 'password', 'phone', 'addressline1', 'addressline2', 'post_code', 'city', 'state', 'country', 'register_via', 'type', 'remember_token', 'status', 'website_id', 'is_verified', 'is_subscribe', 'company_name', 'vat_number', 'company_number','tax_code','is_business', 'language_id','localshop_unique_id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public static function generatePassword()
    {
        // Generate random string and encrypt it.
        return bcrypt(md5(35));
    }

    public function getDiscountCategoryAttribute()
    {
        if (config('wmo_website.type') == config('constant.trade_type')) {
            return $this->trader->discount_category ?? null;
        }
        return null;
    }

    public function trader() {
        return $this->hasOne(TraderDetail::class);
    }

    public static function registerTrader($request)
    {

        $user_data = session('tradesignup.user');
        $user_data['is_subscribe'] = $request->input('user.is_subscribe');
        $user_data['type'] = static::TRADER_TYPE;
        $user_data['is_verified'] = static::NOT_VERIFIED;
        $user_data['register_via'] = 1;
        $user_data['status'] = static::STATUS_PENDING;
        $user_data['website_id'] = config('wmo_website.website_id');
        $user_data['language_id'] = session('language.id');

        $user = static::create($user_data);
        // $user = static::find(52)

        $trader_data = session('tradesignup.trade');
        $trader_data['years_trading'] = $request->input('trade.years_trading');
        $trader_data['other_information'] = $request->input('trade.other_information');
        $trader_data['purchase_from_other'] = $request->input('trade.purchase_from_other');
        $trader_data['purchase_from'] = implode(',', $request->input('trade.purchase_from'));
        $trader_data['business_activities'] = implode(',', $request->input('trade.business_activities'));
        $trader_data['vehicle_range'] = implode(',', $request->input('trade.vehicle_range'));
        $trader_data['user_id'] = $user->id;

        TraderDetail::create($trader_data);

        $created_at = Carbon::now()->toDateTimeString();

        $addresses = [];
        $address_data = [
            'is_prime' => UserAddress::IS_PRIME_YES,
            'status' => 1,
            'user_id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'created_at' => $created_at,
            'updated_at' => $created_at,
        ];

        foreach (session('tradesignup.address') as $key => $row) {
            $row['type'] = $key;
            if ($key == UserAddress::BILLING_ADDRESS_TYPE) {
                $row['note_to_courier'] = '';
            }
            $same_address = false;
            if (isset($row['same_as_billing'])) {
                if ($row['same_as_billing'] == 1) {
                    $same_address = true;
                }
                unset($row['same_as_billing']);
            }
            $addresses[] = array_merge($row, $address_data);
            if ($same_address) {
                $row['note_to_courier'] = '';
                $row['type'] = UserAddress::BILLING_ADDRESS_TYPE;
                $addresses[] = array_merge($row, $address_data);
                break;
            }
        }
        UserAddress::insert($addresses);

        $personnel_data = [];
        foreach (session('tradesignup.personnel') as $key => $row) {
            $personnel_data[] = array_merge($row, [
                'user_id' => $user->id,
                'type' => $key,
                'created_at' => $created_at,
                'updated_at' => $created_at,
            ]);
        }
        TraderPersonnelDetail::insert($personnel_data);

        $references_data = [];
        foreach (session('tradesignup.references') as $key => $row) {
            $references_data[] = array_merge($row, [
                'user_id' => $user->id,
                'reference_no' => $key,
                'created_at' => $created_at,
                'updated_at' => $created_at,
            ]);
        }
        TraderReference::insert($references_data);
    }

    public function scopefindUserData($query, $argv)
    {
        if(isset($argv['searchtxt']) && isset($argv['searchKey']))
        {   
            if($argv['searchKey']=='email')
            {
                $query->select('id as search_text','email');
                $query->where('email','like','%'. $argv['searchtxt'].'%');
            }
            elseif($argv['searchKey']=='name')
            {
                $query->select(DB::raw("CONCAT(first_name,' ',last_name) AS search_text"),'email');

                $query->where(function ($qr) use ($argv) {
                    $searchText = $argv['searchtxt'] ?? '';
                    $qr->whereRaw('CONCAT(first_name," ",last_name) like "%'. $searchText .'%"');
                });
            }
            elseif($argv['searchKey']=='post_code')
            {
                $query->select("ua.postcode as search_text",'users.email');
                $query->leftJoin('user_addresses as ua', 'ua.user_id',  '=', 'users.id');
                $query->where('ua.postcode','like','%'. $argv['searchtxt'].'%');
            }
            elseif($argv['searchKey']=='unique_id')
            {
                $query->select("localshop_unique_id as search_text",'email');
                $query->where('localshop_unique_id','like','%'. $argv['searchtxt'].'%');
            }
            $query->where(['is_verified'=>static::VERIFIED,'users.status'=>static::STATUS_ACTIVE,'users.type'=>static::USER_TYPE,'website_id'=>$argv['websiteId']]);
            return $query;
        }
        return false;
       
    }
}
