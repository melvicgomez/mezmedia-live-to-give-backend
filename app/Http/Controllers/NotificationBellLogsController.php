<?php

namespace App\Http\Controllers;

use App\Models\NotificationBellLogs;
use App\Models\Notifications;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NotificationBellLogsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //

        $lastOpened = NotificationBellLogs::where('user_id', auth()->user()->user_id)->orderBy('notif_log_id', 'desc')->first();
        $lastNotif = Notifications::where('user_id', auth()->user()->user_id)->orderBy('notification_id', 'desc')->first();

        if (is_null($lastOpened)) {
            if (is_null($lastNotif)) {
                return ["show_bell" => false];
            } else {
                return ["show_bell" => true];
            }
        }

        if (!is_null($lastOpened)) {
            if (is_null($lastNotif)) {
                return ["show_bell" => false];
            } else {
                return ["show_bell" => Carbon::parse($lastOpened->created_at)->isBefore($lastNotif->created_at)];
            }
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $bellLog = new NotificationBellLogs();
        $bellLog->opened_at = now();
        $bellLog->user_id = auth()->user()->user_id;
        $bellLog->save();

        return response(["show_bell" => false]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\NotificationBellLogs  $notificationBellLogs
     * @return \Illuminate\Http\Response
     */
    public function show(NotificationBellLogs $notificationBellLogs)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\NotificationBellLogs  $notificationBellLogs
     * @return \Illuminate\Http\Response
     */
    public function edit(NotificationBellLogs $notificationBellLogs)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\NotificationBellLogs  $notificationBellLogs
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, NotificationBellLogs $notificationBellLogs)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\NotificationBellLogs  $notificationBellLogs
     * @return \Illuminate\Http\Response
     */
    public function destroy(NotificationBellLogs $notificationBellLogs)
    {
        //
    }
}
