<?php

namespace App\Widgets;

use Arrilot\Widgets\AbstractWidget;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;
use App\Models\CMS\WebsiteMakeAssociation;

class MakeSearch extends AbstractWidget
{
    /**
     * The configuration array.
     *
     * @var array
     */
    protected $config = [];

    /**
     * Treat this method as a controller action.
     * Return view() or other content to display.
     */
    public function run(Request $request)
    {
        // Get Make list from redis and in case not present create same
        if (!empty(config('wmo_website.make_key'))) {
            $make_data = json_decode(Redis::connection('site')->get(config('wmo_website.make_key')));
            
            if (empty($make_data)) {
                $make_data = WebsiteMakeAssociation::getWebsiteAsscociationMake(config('wmo_website.website_id'));
            }
        }
        return view('widgets.make_search', [
            'make_data' => $make_data,
        ]);
    }
}
