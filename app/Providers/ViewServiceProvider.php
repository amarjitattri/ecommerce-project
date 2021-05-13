<?php

namespace App\Providers;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\View\FileViewFinder;
use Illuminate\View\ViewServiceProvider as OriginalViewServiceProvider;

class ViewServiceProvider extends OriginalViewServiceProvider
{
    /**
     * Register the view finder implementation.
     *
     * @return void
     */
    public function registerViewFinder()
    {
        $http_host = request()->server('HTTP_HOST');
        $request_uri = request()->server('REQUEST_URI');
        if ($request_uri && (preg_match('/^[a-zA-Z]{2}[_][a-zA-Z]{2}$/', $request_uri) || preg_match('/^[a-zA-Z]{2}[_][a-zA-Z]{2}[\/]{1}/', $request_uri))) {
            if (preg_match('/^[a-zA-Z]{2}[_][a-zA-Z]{2}[\/]{1}/', $request_uri)) {
                $url_data = explode("/", $request_uri);
                $request_uri = $url_data[0];
            }
            // Get Country Language from request uri
            $country_lang_array = explode("_", $request_uri);
            $country = $country_lang_array[0];
            $key = $http_host . '_' . $country;

            $hostname = $http_host . '/' . $request_uri;
        } else {
            // Get Host based Redis Key
            $hostname = $key = $http_host;
        }
        try {
            $website = DB::table('websites')->select('theme_code')->where('hostname', 'http://' . $hostname)
                ->orWhere('hostname', 'https://' . $hostname)
                ->join('website_theme', 'website_theme.id', '=', 'websites.theme_id')->first();

            $theme_code = $website->theme_code ?? config('wmo_website.theme_code');

            $this->app->bind('view.finder', function ($app) use ($theme_code) {
                $paths = array_map(function ($path) use ($theme_code) {
                    return $path . '/' . $theme_code;
                }, $app['config']['view.paths']);
                return new FileViewFinder($app['files'], $paths);
            });

        } catch (\Exception $e) {
            logger()->error('ViewServiceProvider:' . $e->getMessage());
            $title = 'DB Connectivity Issue';
            $message = 'Something went wrong, please try again.';
            include base_path() . '/resources/views/error.php';
            die;
        }
    }
}
