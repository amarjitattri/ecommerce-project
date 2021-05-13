<?php 
namespace App\Http\View\Composers;
use Session;

use Illuminate\View\View;
use App;
class ConfigComposer {

    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $request = request()->all();
        $taxStatus = '';
        if (isset($request['status'])) {
            $taxStatus = $request['status'];
        }

        $view->with('assetsVersion', config('wmo_website.assets_version'))
            ->with('cdnPath', config('wmo_website.cdn_path'))
            ->with('siteBaseUrl', config('wmo_website.site_base_url'))
            ->with('baseUrl', config('wmo_website.base_url'))
            ->with('websiteId', config('wmo_website.website_id'))
            ->with('locale', config('wmo_website.locale'))
            ->with('websiteContentData', config('wmo_website.website_content_page'))
            ->with('shopAllCategories', config('wmo_website.shop_all_categories'))
            ->with('helpline_number', config('wmo_website.helpline_number'))
            ->with('currency', session('currency'))
            ->with('language', session('language'))
            ->with('currencies', config('wmo_website.currencies'))
            ->with('languages', config('wmo_website.languages'))
            ->with('localShopUser', session('local_shop_user'))
            ->with('desktopLogo', config('wmo_website.desktop_logo'))
            ->with('mobileLogo', config('wmo_website.mobile_logo'))
            ->with('countryData', config('wmo_website.countryData'))
            ->with('vat_text', session('shipping_country_id') ? trans('product.inc_vat') : config('wmo_website.vat_text'))
            ->with('tax_status', $taxStatus)
            ->with('tradeLocalShopUser',session('trade_local_shop_user'));
    }
}
