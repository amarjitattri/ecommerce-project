<?php

namespace App\Http\Controllers\MyAccount;

use Response;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\MyAccount\MessageTarget;
use App\Models\MyAccount\CustomUserWishlist;

class MessageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $messages = MessageTarget::select('id', 'template_id', 'is_seen', 'created_at')
        ->with('template:id,subject')->whereAuthUser()
        ->when($request->input('search'), function ($qr) use ($request){
            return $qr->whereHas('template', function ($q) use ($request) {
                return $q->where('subject', 'like', '%'. $request->input('search') .'%');
            });
        })->orderBy('id', $request->input('filter') == 'Oldest' ? 'ASC' : 'DESC')
        ->paginate(15)->appends($request->except('page'));

        return view('myaccount.messages.index', compact('messages'));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $message = MessageTarget::select('id', 'template_id', 'is_seen', 'created_at','content as mail_content')
        ->with('template:id,subject,content')->whereAuthUser()->findOrFail($id);
         
        if (!$message->is_seen) {
            $message->update(['is_seen' => 1]);
        }

        return view('myaccount.messages.show', compact('message'));
    }

}
