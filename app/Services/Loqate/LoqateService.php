<?php

namespace App\Services\Loqate;

use App\Models\ApiCall;
use GuzzleHttp\Client;

class LoqateService
{
    //The key used to authenticate with the service.
    private $Key;
    //The search text to find. Ideally a postcode or the start of the address.
    private $Text;
    //Whether the API is being called from a middleware implementation (and therefore the calling IP address should not be used for biasing).
    private $IsMiddleware;
    //A container for the search. This should only be another Id previously returned from this service when the Type of the result was not 'Address'.
    private $Container;
    //A starting location for the search. This can be the name or ISO 2 or 3 character code of a country, WGS84 coordinates (comma separated) or IP address to search from.
    private $Origin;
    //A comma separated list of ISO 2 or 3 character country codes to limit the search within.
    private $Countries;
    //The maximum number of results to return.
    private $Limit;
    //The preferred language for results. This should be a 2 or 4 character language code e.g. (en, fr, en-gb, en-us etc).
    private $Language;
    //Holds the results of the query
    private $Data;

   function __construct()
   {
        $this->Key = config('loqate.api_key');
   }

   function searchAddress($Text, $Countries = "", $IsMiddleware = "True", $Container = "", $Origin = "", $Limit = 100, $Language = "en-gb")
   {
        $this->Text = $Text;
        $this->Countries = $Countries;
        $this->IsMiddleware = $IsMiddleware;
        $this->Container = $Container;
        $this->Origin = $Origin;
        $this->Limit = $Limit;
        $this->Language = $Language;
   }

   function MakeRequest()
   {
        $apiCall = ApiCall::where(['type' => '1']);
        if (!empty($apiCall->get()->toArray())) {
            $apiCall->increment('calls');
        } else {
            ApiCall::create(['type' => '1', 'calls' => 1]);
        }

        $url = "https://api.addressy.com/Capture/Interactive/Find/v1.10/json3.ws?";
        $url .= "&Key=" . urlencode($this->Key);
        $url .= "&Text=" . urlencode($this->Text);
        $url .= "&IsMiddleware=" . urlencode($this->IsMiddleware);
        $url .= "&Container=" . urlencode($this->Container);
        $url .= "&Origin=" . urlencode($this->Origin);
        $url .= "&Countries=" . urlencode($this->Countries);
        $url .= "&Limit=" . urlencode($this->Limit);
        $url .= "&Language=" . urlencode($this->Language);

        $client = new Client();
        $response = $client->request('GET', $url);
        $data = json_decode($response->getBody()->getContents());
        if (!isset($data->Items[0]->Error)) {
            $this->Data = $data;
        }
   }

   function HasData()
   {
        if ( !empty($this->Data) )
        {
            return $this->Data;
        }
        return false;
   }

    public function addressDetail($request)
    {
        $apiCall = ApiCall::where(['type' => '2']);
        if (!empty($apiCall->get()->toArray())) {
            $apiCall->increment('calls');
        } else {
            ApiCall::create(['type' => '2', 'calls' => 1]);
        }

        $url = "https://api.addressy.com/Capture/Interactive/Retrieve/v1.00/json3.ws?";
        $url .= "&Key=" . urlencode($this->Key);
        $url .= "&Id=" . urlencode($request->id);

        $client = new Client();
        $response = $client->request('GET', $url);
        return $response->getBody()->getContents();
    }
}

