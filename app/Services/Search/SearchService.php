<?php
namespace App\Services\Search;
use Illuminate\Http\Request;
use App\Services\Search\Providers\Contracts\SearchContracts;
use Session;
class SearchService
{
    protected $__search;

    public static function  getSearchResult(Request $request, $searchObj)
    {
        $requestData = $request->all();
       
        return $searchObj->processSearch($requestData);
    }

    public static function getSuggestions($str,SearchContracts $searchObj)
    {
        return $searchObj->getSuggestions($str);
    }

    public static function getmoreProducts($request, SearchContracts $searchObj)
    {
        return $searchObj->getmoreProducts($request);
    }

    public static function getmoreModels($request, SearchContracts $searchObj)
    {
        return $searchObj->getmoreModels($request);
    }
}

?>