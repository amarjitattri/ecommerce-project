<?php

namespace App\Http\Controllers\MyAccount;

use Auth;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\Myaccount\Interfaces\UserEmailPreferenceRepositoryInterface;

class MyPreferenceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {   
        $sidebar = 8;

        return view('myaccount.my-preference.index', compact('sidebar'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $language = collect(config('wmo_website.languages'))->where('id', $request->input('language_id'))->first();
        if ($language) {
            User::where('id', auth()->id())->update(['language_id' => $language['id']]);
        }
        

        return redirect('user/my-preference')->with('message', __('messages.my_preferences_saved'));
    }

    
}
