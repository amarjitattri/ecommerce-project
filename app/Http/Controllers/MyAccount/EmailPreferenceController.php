<?php

namespace App\Http\Controllers\MyAccount;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Myaccount\Interfaces\UserEmailPreferenceRepositoryInterface;
use Auth;

class EmailPreferenceController extends Controller
{

    private $__userEmailPreference;

    public function __construct(UserEmailPreferenceRepositoryInterface $userEmailPreference)
    {
        $this->__userEmailPreference = $userEmailPreference;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {   
        
        $sidebar = 7;
        $events = $this->__userEmailPreference->getEvents();
        //check user visit first time on website or new event
        $userEmailPreferences = $this->__userEmailPreference->getUserEmailPreferences()->toArray();
        if (!$userEmailPreferences) {
            foreach ($events as $key => $event) {
                $data['status'] = 1;
                $data['where'] = [
                    'user_id' => Auth::user()->id, 
                    'message_event_id' => $event['id'],
                    'website_id' => config('wmo_website.website_id')
                ];
                $userEmailPreferences[$key] = $event;
                $userEmailPreferences[$key]['pivot'] = $this->__userEmailPreference->setEmailPreferences($data);
            } 
        }

        return view('myaccount.email-preference.index', compact('sidebar', 'events', 'userEmailPreferences'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $events = $this->__userEmailPreference->getEvents();
        foreach ($events as $event) {
            $data['status'] = (int) isset($request->events) && array_key_exists($event['id'], $request->events);
            $data['where'] = [
                'user_id' => Auth::user()->id, 
                'message_event_id' => $event['id'],
                'website_id' => config('wmo_website.website_id')
            ];
            $this->__userEmailPreference->setEmailPreferences($data); 
        }

        return redirect('user/user-preference')->with('message', __('messages.email_preferences_saved'));
    }
}
