<?php
namespace App\Services\Search\Providers;

use App\Services\Search\Providers\Contracts\SearchContracts;
use GuzzleHttp\Client;
use Session;

class Solr implements SearchContracts
{
    public function processSearch($requestData)
    {
        $products = $this->productSearch($requestData);
        $models = $this->modelSearch($requestData);

        return array('products' => $products, 'models' => $models);
    }

    public function modelSearch($requestData)
    {
        $website_id = config('wmo_website.website_id');
        $searchStr = 'website_id%3A' . $website_id;
        $search = isset($requestData['search']) ? $requestData['search'] : '';
        $offset = isset($requestData['offset']) ? $requestData['offset'] : '';

        Session::put('search_key', $search);

        $searchReq = $this->cleanStr($search);
        if ($searchReq != '') {
            $searchStr = 'nomenclature%3A%2A' . $searchReq . '%2A';
            // $searchStr = 'nomenclature%3A%27%2A' . $searchReq . '%2A%27&defType=dismax&mm=100'
        }

        if ($offset > 0) {
            $start = $offset * 10;
            $searchStr .= '&rows=10&start=' . $start;
        } else {
            $searchStr .= '&rows=10&start=0';
        }

        $solrUrl = config('constant.solr_url');
        $solrUrl = $solrUrl . '/solr/we-moto-solr/select?q=' . $searchStr . '&fq=website_id%3A' . $website_id . '%20AND%20language_id%3A' . session('language.id');

        return $this->sendResponse($solrUrl, $search, $searchReq);
    }

    public function cleanStr($str)
    {
        return str_replace(array('#', '*', '$', '%', '^', '&', '(', ')', '<', '>', ':', '[', ']', '{', '}', '"', '!', '`', '~', '@'), '', $str);
    }
    public function productSearch($requestData)
    {
        $search = isset($requestData['search']) ? $requestData['search'] : '';
        $offset = isset($requestData['offset']) ? $requestData['offset'] : '';
        $search_click = isset($requestData['search_click']) ? $requestData['search_click'] : '';
        Session::put('search_key', $search);
        $website_id = config('wmo_website.website_id');
        $lang_id = session('language.id');
        $searchStr = 'website_id%3A' . $website_id . '%20AND%20lang_id%3A' . $lang_id . '%20';

        $searchReq = $this->cleanStr($search);
        if ($searchReq != '') {
            $adminId = session()->get('local_shop_user') ?: '';
            $searchFields = '&fq=(manufacture_code%3A"' . $searchReq . '"';
            $searchFields .= '%20OR%20product_code%3A"' . $searchReq . '"';
            $searchFields .= '%20OR%20category_title%3A"' . $searchReq . '"';
            $searchFields .= '%20OR%20brand%3A"' . $searchReq . '"';
            $searchFields .= '%20OR%20customer_description%3A"' . $searchReq . '"';
            $searchFields .= '%20OR%20product_description%3A"' . $searchReq . '"';
            $searchFields .= '%20OR%20old_product_code%3A"' . $searchReq . '"';
            $searchFields .= '%20OR%20commonsearch%3A"' . $searchReq . '"';
            $searchFields .= '%20OR%20ceder_value%3A"' . $searchReq . '"';
            if ($adminId > 0) {
                $searchFields .= '%20OR%20crossmatch_text%3A"' . $searchReq . '"';
                $searchFields .= '%20OR%20supplier_part_number%3A"' . $searchReq . '"';
            }
            $searchFields .= '%20OR%20system_notes%3A"' . $searchReq . '")';

            if ($adminId == '') {
                $searchFields .= '%20OR%20(crossmatch_text%3A"' . $searchReq . '"%20AND%20productgroup_crossmatch%3A1%20)';
            }

            $searchStr .= $searchFields;
            if ($search_click == 1) {
                $searchStr = $searchReq . '&defType=dismax&mm=100&fq=website_id%3A' . $website_id . '%20AND%20lang_id%3A' . $lang_id . '%20';
            }
        }
        if ($offset > 0) {
            $start = $offset * 10;
            $searchStr .= '&rows=10&start=' . $start;
        } else {
            $searchStr .= '&rows=10&start=0';
        }

        $solrUrlConst = config('constant.solr_url');
        $solrUrl = $solrUrlConst . '/solr/we-moto/select?q=' . $searchStr . '&sort=category_id%20asc';

        return $this->sendResponse($solrUrl, $search, $searchReq);
    }

    public function sendResponse($url, $search, $searchReq)
    {
        try {
            $client = new Client;
            $response = $client->request('GET', $url, [
                'headers' => [
                    'accept' => 'application/json',
                ],
            ]);

            $dataResponse = json_decode($response->getBody(), true);
            $responseVar = $dataResponse['response'];
        } catch (\Exception $e) {
            logger()->error('SOLR:' . $e->getResponse()->getBody()->getContents());
        }

        $docs = array();
        if (isset($responseVar['docs']) && count($responseVar['docs']) > 0) {
            $docs = $responseVar['docs'];
        }

        if ($search != '' && $searchReq == '' || $searchReq == '') {
            $docs = array();
            $responseVar['numFound'] = 0;
        }

        return array('result' => $docs, 'numFound' => $responseVar['numFound'] ?? 0);
    }

    public function getSuggestions($search)
    {
        $searchReq = $this->cleanStr($search);
        $website_id = config('wmo_website.website_id');
        $productsSuggest = config('constant.solr_url') . "/solr/we-moto/suggest?suggest=true&suggest.build=true&suggest.reload=true&suggest.dictionary=commonsearch&suggest.q=" . $searchReq . '&suggest.cfq=' . $website_id . '_' . session('language.id');
        $productData = $this->dataSuggestions($productsSuggest, 'commonsearch');

        $modelSuggest = config('constant.solr_url') . "/solr/we-moto-solr/suggest?suggest=true&suggest.build=true&suggest.reload=true&suggest.dictionary=nomenclature&suggest.q=" . $searchReq . '&suggest.cfq=' . $website_id . '_' . session('language.id');
        $modelData = $this->dataSuggestions($modelSuggest, 'nomenclature');
        return array('products' => $productData, 'model' => $modelData);
    }

    public static function dataSuggestions($url, $searchby)
    {
        $client = new Client;
        $response = $client->request('GET', $url, [
            'headers' => [
                'accept' => 'application/json',
            ],
        ]);

        $dataSuggestions = json_decode($response->getBody(), true);
        $responseHeader = $dataSuggestions['suggest'][$searchby];
        $suggestions = array();

        foreach ($responseHeader as $responseData) {
            $suggestions = $responseData['suggestions'];
        }
        $suggestArray = array();
        foreach ($suggestions as $suggest) {
            if (!in_array($suggest['term'], $suggestArray)) {
                $suggestArray[] = $suggest['term'];
            }
        }

        return array('suggestions' => $suggestArray, 'count' => count($suggestArray));
    }

    public function getmoreProducts($request)
    {
        return $this->productSearch($request);
    }

    public function getmoreModels($request)
    {
        return $this->modelSearch($request);
    }
}
