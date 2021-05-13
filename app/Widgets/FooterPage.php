<?php

namespace App\Widgets;

use Arrilot\Widgets\AbstractWidget;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;
use App\Models\CMS\{
    Page,
};

class FooterPage extends AbstractWidget
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
        $data = array();
        // Get Make list from redis and in case not present create same
        if (!empty(config('wmo_website.website_id'))) {
            $data['pages_content'] = config('wmo_website.get_website_pages');
        }

        return view('widgets.footer_pages', [
            'data' => $data,
        ]);
    }
}
