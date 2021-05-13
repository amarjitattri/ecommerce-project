<?php

namespace App\Widgets;

use Arrilot\Widgets\AbstractWidget;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;
use App\Models\CMS\HeroMessage as HM;

class HeroMessage extends AbstractWidget
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
        if (!empty(config('wmo_website.website_id'))) {
            $website_id = config('wmo_website.website_id');
            // Get Website hero icons 
            $hero_icons = HM::getHeroIcons($website_id);
        }
        return view('widgets.hero_message', [
            'hero_icons' => $hero_icons,
        ]);
    }
}
