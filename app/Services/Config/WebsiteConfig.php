<?php
namespace App\Services\Config;

use App\Models\CMS\Page;
use App\Models\CMS\Website;
use App\Models\CMS\WebsiteMakeAssociation;
use App\Models\CMS\Website\WebsiteDeliveryLocation;
use App\Models\CMS\Website\WebsiteInvoice;
use App\Models\Country;
use App\Models\Ipnation;
use App\Services\Cart\Cart;
use App\Services\Config\Cache\Contracts\CacheContracts;
use App\Services\Localshop\Localshop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use App\Services\Product\FeaturedProduct;
use CurrencyConversionAPI;
use App\Models\CMS\Website\CarriageRule;

use Cache;
class WebsiteConfig
{
    private $__cache;
    public $items;
    const WEBSITE_KEY_PREFIX = 'website';
    const WEBSITE_VERSION = 1;

    private $__httpHost;
    private $__requestUri;
    private $__websiteRedisKey;
    private $__hostName;
    protected $localshop;

    /**
     * CartRepository constructor.
     * @param RedisCache $cache
     */
    public function __construct(CacheContracts $cache, Request $request, Localshop $localshop, FeaturedProduct $featuredProductService)
    {
        $this->__localshop = $localshop;
        $this->__featuredProductService = $featuredProductService;
        $this->__cache = $cache;
        $this->__request = $request;
        $this->__httpHost = $this->getHttpHost();
        $this->__requestUri = $this->getRequestUri();
        $this->setWebsiteHost();
        $this->setWebsiteDetails();
    }

    public function setWebsiteHost()
    {
        $request_uri = $this->__requestUri;
        if (!empty($request_uri)) {
            if (preg_match('/^[a-zA-Z]{2}[_][a-zA-Z]{2}$/', $request_uri) || preg_match('/^[a-zA-Z]{2}[_][a-zA-Z]{2}[\/]{1}/', $request_uri)) {
                if (preg_match('/^[a-zA-Z]{2}[_][a-zA-Z]{2}[\/]{1}/', $request_uri)) {
                    $url_data = explode("/", $request_uri);
                    $request_uri = $url_data[0];
                }
                // Get Country Language from request uri
                $country_lang_array = explode("_", $request_uri);
                $country = $country_lang_array[0];
                $key = $this->__httpHost . '_' . $country;

                $hostname = $this->__httpHost . '/' . $request_uri;
            } else {
                // Get Host based Redis Key
                $hostname = $key = $this->__httpHost;
            }
        } else {
            // Get Host based Redis Key
            $hostname = $key = $this->__httpHost;
        }
        $key = static::WEBSITE_KEY_PREFIX . '_' . $key . '_' . static::WEBSITE_VERSION;
        $this->__websiteRedisKey = $key;
        $this->__hostName = $hostname;
    }
    protected function guard()
    {
        return Auth::guard();
    }
    public function getRequestUri()
    {
        return ltrim($this->__request->server('REQUEST_URI'), '/');
    }
    public function getHttpHost()
    {
        return $this->__request->server('HTTP_HOST');
    }

    public function setWebsiteDetails()
    {
        // Get all Website settings based on website code OR website host
       
        $websiteSettings = $this->__cache->getRedisValue($this->__websiteRedisKey);
        if (empty($websiteSettings)) {
            $websiteSettings = Website::getWebsiteDetails($this->__hostName);
            if (empty($websiteSettings)) {
                abort(404, 'Not Found');
            } else {
                $this->__cache->setRedisValue($this->__websiteRedisKey, $websiteSettings);
            }
        }
        if (is_object($websiteSettings)) {
            $websiteSettings = (array) $websiteSettings;
        }

        // Set Language Locale
        if (!empty($websiteSettings['website_details'])) {
            if (is_object($websiteSettings['website_details'])) {
                $websiteSettings['website_details'] = (array) $websiteSettings['website_details'];
            }

            // Set website languagecode in locale
            $primeLanguage = array(
                'id' => $websiteSettings['website_details']['lang_id'],
                'language' => $websiteSettings['website_details']['language'],
                'languagecode' => $websiteSettings['website_details']['languagecode'],
                'flag' => $websiteSettings['website_details']['flag'],
            );

            $websiteId = $websiteSettings['website_details']['website_id'];
            $makeKey = $this->__websiteRedisKey . '_make_list';
           
            $navigation_settings = array(
                'brand' => !empty($websiteSettings['brands']) ? $websiteSettings['brands'] : 0,
                'clearance' => !empty($websiteSettings['website_details']['clearance']) ? $websiteSettings['website_details']['clearance'] : 0,
                'shop_all' => !empty($websiteSettings['website_details']['shop_all']) ? $websiteSettings['website_details']['shop_all'] : 0,
                'nav_categories' => !empty($websiteSettings['website_navigations']) ? $websiteSettings['website_navigations'] : array(),
            );

            //get default country and currency
            $localeKey = $this->__cache->getLocaleKey($websiteId);
            
            $websiteSettings['get_default_locale_details'] = $this->__cache->getRedisValue($localeKey);
            if (empty($websiteSettings['get_default_locale_details'])) {
                $websiteObj = new Website();
                $websiteSettings['get_default_locale_details'] = $websiteObj->getDefaultLocaleDetails($websiteSettings['website_details']['website_id']);
                $this->__cache->setRedisValue($localeKey, $websiteSettings['get_default_locale_details']);
            }

            $getDefaultLocaleDetails = $websiteSettings['get_default_locale_details'];

            //set default currency
            $primeCurrency = $websiteSettings['website_details']['prime_currency'] ?? null;

            $currency = isset($getDefaultLocaleDetails['currency']) ? $getDefaultLocaleDetails['currency'] : $primeCurrency;
            $language = isset($getDefaultLocaleDetails['language']) ? $getDefaultLocaleDetails['language'] : $primeLanguage;

            $countryData = $getDefaultLocaleDetails['countryData'] ? (object) $getDefaultLocaleDetails['countryData'] : $getDefaultLocaleDetails['countryData'];
            $vatText = '';
            if (!empty($countryData)) {
                $vatText = trans('product.inc_vat');
            }

            if (session('language')) {
                $language = session('language');
            }

            $locale = $language['languagecode'];
            // GET front message
            $front_message = $websiteSettings['front_message'];
            $product_delivery_message = $websiteSettings['product_delivery_message'];

            $cPrimeCurrency = $this->getPrimeCurrencyExhangeRate($websiteSettings['website_details']['prime_currency']);
            config([
                'wmo_website.assets_version' => $websiteSettings['website_details']['assets_version'], 'wmo_website.cdn_path' => $websiteSettings['website_details']['cdn_path'], 'wmo_website.theme_code' => $websiteSettings['website_details']['theme_code'], 'wmo_website.website_code' => $websiteSettings['website_details']['website_code'], 'wmo_website.invoice' => $websiteSettings['invoice'], 'wmo_website.type' => $websiteSettings['website_details']['type'], 'wmo_website.site_base_url' => $this->__requestUri, 'wmo_website.base_url' => $this->__httpHost, 'wmo_website.website_id' => $websiteId, 'wmo_website.locale' => $locale, 'wmo_website.helpline_number' => $websiteSettings['website_details']['helpline_number'], 'wmo_website.make_key' => $makeKey, 'wmo_website.navigation_settings' => $navigation_settings, 'wmo_website.front_message' => $front_message, 'wmo_website.product_delivery_message' => $product_delivery_message, 'wmo_website.franchise' => $websiteSettings['franchise'] ?? null, 'wmo_website.currency' => $currency, 'wmo_website.currencies' => $websiteSettings['website_details']['currencies'] ?? null, 'wmo_website.prime_currency' => $cPrimeCurrency, 'wmo_website.language' => $language, 'wmo_website.languages' => $websiteSettings['website_details']['languages'] ?? null, 'wmo_website.website_franchise' => !empty($websiteSettings['website_details']['franchise_id']) ? $websiteSettings['website_details']['franchise_id'] : '', 'wmo_website.desktop_logo' => $websiteSettings['website_details']['website_logo'] ?? '', 'wmo_website.mobile_logo' => $websiteSettings['website_details']['mobile_logo'] ?? '', 'wmo_website.fraud_params' => $websiteSettings['website_details']['fraud_params'] ?? [], 'wmo_website.countryData' => $countryData, 'wmo_website.vat_text' => $vatText, 'wmo_website.payment_gateways' => $websiteSettings['payment_gateways'], 'wmo_website.watermark' => $websiteSettings['website_details']['watermark'] ?? '', 'wmo_website.wemoto_watermark' => $websiteSettings['website_details'][' '] ?? '', 'wmo_website.website_key' => $this->__websiteRedisKey, 'wmo_website.timezone' => $websiteSettings['website_details']['abbreviation'], 'wmo_website.trade_site_link' => $websiteSettings['trade_site_link'], 'wmo_website.user_ip' => $getDefaultLocaleDetails['ip'], 'wmo_website.carriage_rules' => $websiteSettings['carriage_rules'], 'wmo_website.holidays' => $websiteSettings['holidays'],
            ]);

            config(['mail.markdown.paths.0' => str_replace('resources/views/vendor', 'resources/views/'. $websiteSettings['website_details']['theme_code'] .'/vendor', config('mail.markdown.paths.0'))]);
            app()->setLocale($language['languagecode']);
            // Get Make list from redis and in case not present create same
            $make_data = $this->__cache->getRedisValue($makeKey);
            if (empty($make_data)) {
                $make_data = WebsiteMakeAssociation::getWebsiteAsscociationMake($websiteId);
                $this->__cache->setRedisValue($makeKey, $make_data);
            }
            //set website page content data
            $pageDataArr = $websiteSettings['all_getpage_content_data'];
            $pageData = array();
            foreach ($pageDataArr as $value) {
                $pageData[$value['section_id']][] = (object) $value;
            }
            config([
                'wmo_website.website_content_page' => $pageData,
                'wmo_website.get_website_pages' => $websiteSettings['get_website_pages'],
            ]);
        }
        $localShop = request()->all();
        if (isset($localShop['ls']) && isset($localShop['token'])) {
            $this->__localshop->localShopeUserexist($localShop['ls']);
        }
        //get featured product from redis if not then set in redis
        $fProductKey = $this->__websiteRedisKey . '_f_products';
        $getfeaturedProducts = $this->__cache->getRedisValue($fProductKey);
        if (empty($getfeaturedProducts)) {
            $getfeaturedProducts = $this->__featuredProductService->getfeaturedProducts();
            $this->__cache->setRedisValue($fProductKey, $getfeaturedProducts);
        }
        config([
            'wmo_website.f_product_key' => $fProductKey,
            'wmo_website.featured_products' => $getfeaturedProducts,
        ]);
    }

    public function getPrimeCurrencyExhangeRate($cPrimeCurrency)
    {
        $paramsAPI = [
            'currency_from' => config('wmo_website.base_currency_code'),
            'currency_to' => $cPrimeCurrency['code']
        ];
        $rParamsAPI = [
            'currency_from' => $cPrimeCurrency['code'],
            'currency_to' => config('wmo_website.base_currency_code'),
        ];
        $cPrimeCurrency['currency_exchange'] = CurrencyConversionAPI::getExchangeRate($paramsAPI) ?? $cPrimeCurrency['currency_exchange'];
        $cPrimeCurrency['reverse_currency_exchange'] = CurrencyConversionAPI::getExchangeRate($rParamsAPI) ?? $cPrimeCurrency['reverse_currency_exchange'];
        return $cPrimeCurrency;
    }
}
