<?php

if (!function_exists('themeAsset')) {
    /**
     * wrapping laravel asset add theme prefix in all assets
     *
     * @param  string  $path
     * @param  bool|null  $secure
     * @return string
     */
    function themeAsset($path, $secure = null)
    {
        return app('url')->asset('themes/' . config('wmo_website.theme_code') . '/' . $path, $secure);
    }
}

if (!function_exists('isTradeSite')) {
    function isTradeSite()
    {
        return config('wmo_website.type') == config('constant.trade_type');
    }
}

if (!function_exists('vehicleListingParams')) {
    function vehicleListingParams($params)
    {
        return [
            strtolower($params['make_assoc']['original_name']),
            strtolower($params['family_assoc']['original_name']),
            strtolower($params['cc_assoc']['title']),
            $params['year'],
            $params['id'],
        ];
    }
}

/**
 *  first param - product code (required)
 *  second param - category slug (for component url) (optional)
 *  third param - association description slug (for parts url) (optional)
 */
if (!function_exists('productDetailUrl')) {
    function productDetailUrl($product_code, $assoc_slug = false, $model_slug = false)
    {
        $route = 'product.details.code';
        $params = [$product_code];
        if ($assoc_slug) {
            $route = 'product.details.cat';
            array_unshift($params, $assoc_slug);
        } elseif ($model_slug) {
            $route = 'product.details';
            array_unshift($params, $model_slug);
        }
        return strtolower(route($route, $params));
    }
}

if (!function_exists('languageJoinSelect')) {
    function languageJoinSelect($select, $alias, $default = false)
    {
        $select_raw = '';
        foreach ($select as $field) {
            $select_raw .= 'IF(lc.' . $field . ' = "" OR lc.' . $field . ' IS NULL, ' . $alias . '.' . $field . ', lc.' . $field . ') as ' . $field . ',';
        }
        if ($default) {
            $select_raw .= 'IF(lc.' . $default . ' = "" OR lc.' . $default . ' IS NULL, ' . $alias . '.' . $default . ', lc.' . $default . ') as ' . $default;
        }

        return $select_raw;
    }
}
