<?php

namespace App\Http\Controllers;

use App\Models\BcoinLog;
use App\Models\FCMNotification;
use App\Models\Notifications;
use App\PusherEvents\BcoinAwarded;
use Illuminate\Http\Request;

class BcoinLogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $per_page = !is_null($request->per_page) ? (int) $request->per_page : 10;
        if ($request->user_id) {
            $bcoinLogs = BcoinLog::with(['user'])
                ->where('user_id', $request->user_id)
                ->orderBy('created_at', 'DESC');
            $responseObject = collect(['total_bcoin' => $bcoinLogs->sum('amount')]);
            return $responseObject->merge($bcoinLogs->simplePaginate($per_page));
        }

        return BcoinLog::with(['user'])->orderBy('created_at', 'DESC')->simplePaginate($per_page);
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
        if (auth()->user()->privilege == "moderator") {
            $bcoinLog = new BcoinLog();
            $bcoinLog->user_id = $request->user_id;
            $bcoinLog->amount = $request->amount;
            $bcoinLog->description = $request->description;
            $bcoinLog->challenge_id = $request->challenge_id ?: 0;
            $bcoinLog->meetup_id = $request->meetup_id ?: 0;
            $bcoinLog->live_id = $request->live_id ?: 0;
            $bcoinLog->save();


            // NOTIFICATION RECORD
            $notif = new Notifications();
            $notif->user_id = $bcoinLog->user_id;
            $notif->title = 'B Coins Awarded';
            $notif->message = $bcoinLog->description;
            $notif->deep_link = 'bcoin-history';
            $notif->transaction_id = $bcoinLog->transaction_id;
            $notif->save();

            // EVENT NOTIFICATION
            event(new BcoinAwarded(
                $notif->notification_id,
                'B Coins Awarded',
                $bcoinLog->description,
                $bcoinLog->user_id
            ));


            $tokens = FCMNotification::where('user_id',  $notif->user_id)
                ->pluck('fcm_token')
                ->all();
            $fcm = new FCMNotificationController();
            $fcm->sendNotification(
                $tokens,
                $notif->title,
                $notif->message,
                ["url" =>  $notif->deep_link]
            );
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\BcoinLog  $bcoinLog
     * @return \Illuminate\Http\Response
     */
    public function show(BcoinLog $bcoinLog)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\BcoinLog  $bcoinLog
     * @return \Illuminate\Http\Response
     */
    public function edit(BcoinLog $bcoinLog)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\BcoinLog  $bcoinLog
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (auth()->user()->privilege == "moderator") {
            $fieldsToUpdate = $request->only([
                'user_id',
                'amount',
                'description',
                'challenge_id',
                'meetup_id',
                'live_id',
                'deleted_at',
            ]);
            $bcoinLog = BcoinLog::withTrashed()->find($id);
            if ($bcoinLog) {
                $bcoinLog->update($fieldsToUpdate);
                $bcoinLog->user;
                return $bcoinLog;
            }
            return response(["error" => "Bcoin transaction not found."], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\BcoinLog  $bcoinLog
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (auth()->user()->privilege == "moderator") {
            $bcoinLog = BcoinLog::find($id);
            if ($bcoinLog) {
                // delete the bcoin transaction
                $bcoinLog->delete();

                // delete the related notification
                $notifToDelete = Notifications::where('transaction_id', $bcoinLog->transaction_id);
                $notifToDelete->delete();
                return $bcoinLog;
            }
        }
        return response(["message" => "Transaction not found."], 400);
    }
}
