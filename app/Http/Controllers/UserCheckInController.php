<?php

namespace App\Http\Controllers;

use App\Models\BcoinLog;
use App\Models\FCMNotification;
use App\Models\Notifications;
use App\Models\User;
use App\Models\UserCheckInModel;
use App\PusherEvents\AdminMessage;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class UserCheckInController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
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
        $tz = $request->tz ?: "+08:00";
        $consecutiveDaysCount  = 0;
        $user_id = auth()->user()->user_id;
        $checkInDate = now();
        $checkInDateLocal = now($tz);
        $checkInToday = UserCheckInModel::withTrashed()
            ->where('user_id', $user_id)
            ->whereDate('check_in_date_local', $checkInDateLocal->format('Y-m-d'))
            ->first();

        // check if user check-in already today
        $checkIn = new UserCheckInModel();
        if (is_null($checkInToday)) {
            $checkIn->user_id = $user_id;
            $checkIn->check_in_date = $checkInDate->format('Y-m-d H:i');
            $checkIn->check_in_date_local = $checkInDateLocal->format('Y-m-d H:i');
            $checkIn->save();
        }

        // get the last 7 days check-in
        $checkInStatus = UserCheckInModel::where('user_id', $user_id)
            ->orderBy('check_in_date_local', 'desc')
            ->get()
            ->take(7)
            ->map(function ($checkIn) {
                return (new Carbon($checkIn->check_in_date_local))->format('Y-m-d');
            })
            ->toArray();

        // get the last 7 days from today
        $streakD7 = array_reverse(collect(new CarbonPeriod(
            now('+08:00')->subDays(6),
            '1 days',
            now('+08:00')
        ))
            ->map
            ->toDateString()
            ->toArray());

        // check user's check-in's consecutive days
        foreach ($checkInStatus as $key => $value) {
            if ($value == $streakD7[$key]) {
                $consecutiveDaysCount++;
                continue;
            }
            break;
        }
        if ($checkIn->id) {
            // $bcoinLog = new BcoinLog();
            if ($consecutiveDaysCount <= 6) {
                // $bcoinLog->amount = 50;
                // $bcoinLog->description = "Live to Give Daily Check-in Reward.";
            } else if ($consecutiveDaysCount == 7) {
                // $bcoinLog->amount = 100;
                // $bcoinLog->description = "7 DAY Bonus! Live to Give Daily Check-in Reward.";
                // delete the record when user's finished 7 days streak
                UserCheckInModel::where('user_id', $user_id)->delete();
            }
            // $bcoinLog->user_id = $user_id;
            // $bcoinLog->save();
        }

        // return response(["days_count" => $consecutiveDaysCount, "check_in_today" => !is_null($checkIn->id)], 200);
        return response(["days_count" => $consecutiveDaysCount, "check_in_today" => false], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $tz = $request->tz ?: "+08:00";
        $consecutiveDaysCount  = 0;
        $user_id = (int) $id;

        $checkInToday = UserCheckInModel::withTrashed()
            ->where('user_id', $user_id)
            ->whereDate('check_in_date_local', now($tz)->format('Y-m-d'))
            ->first();


        // get the last 7 days check-in
        $checkInStatus = UserCheckInModel::withTrashed()
            ->where('user_id', $user_id)
            ->orderBy('check_in_date_local', 'desc')
            ->get()
            ->take(7)
            ->map(function ($checkIn) {
                return (new Carbon($checkIn->check_in_date_local))->format('Y-m-d');
            })
            ->toArray();

        // get the last 7 days from today
        $streakD7 = array_reverse(collect(new CarbonPeriod(
            now('+08:00')->subDays(6),
            '1 days',
            now('+08:00')->subDays(is_null($checkInToday) ? 1 : 0)
        ))
            ->map
            ->toDateString()
            ->toArray());

        // check user's check-in's consecutive days
        foreach ($checkInStatus as $key => $value) {
            if ($value == $streakD7[$key]) {
                $consecutiveDaysCount++;
                continue;
            }
            break;
        }
        return response(["days_count" => $consecutiveDaysCount, "check_in_today" => !is_null($checkInToday)], 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function checkInStreak()
    {
        $users = User::where("is_verified", 1)->get();
        foreach ($users as $user) {
            $consecutiveDaysCount = 0;
            $user_id = $user->user_id;

            $checkInToday = UserCheckInModel::withTrashed()
                ->where('user_id', $user_id)
                ->whereDate('check_in_date_local', now('+08:00')->format('Y-m-d'))
                ->first();

            $streakD7 = array_reverse(
                collect(new CarbonPeriod(
                    now('+08:00')->subDays(7),
                    '1 days',
                    now('+08:00')->subDays(1)
                ))
                    ->map
                    ->toDateString()
                    ->toArray()
            );

            if (is_null($checkInToday)) {
                $checkInStatus = UserCheckInModel::where('user_id', $user_id)
                    ->whereDate('check_in_date_local', "<", now('+08:00')->format('Y-m-d'))
                    ->orderBy('check_in_date_local', 'desc')
                    ->get()
                    ->take(7)
                    ->map(function ($checkIn) {
                        return (new Carbon($checkIn->check_in_date_local))->format('Y-m-d');
                    })
                    ->toArray();

                foreach ($checkInStatus as $key => $value) {
                    if ($value == $streakD7[$key]) {
                        $consecutiveDaysCount++;
                        continue;
                    }
                    break;
                }

                $shouldNotif = false;
                $notif = new Notifications();

                if ($consecutiveDaysCount == 5) {
                    // $shouldNotif = true;
                    $notif->title = "Live to Give Daily Check-in Reward is ready!";
                    $notif->message = "Don’t lose your streak! Check-in now to get your B Coins!";
                } else if ($consecutiveDaysCount == 6) {
                    // $shouldNotif = true;
                    $notif->title = "Live to Give Daily Check-in Reward is ready!";
                    $notif->message = "Don’t lose your streak! Check-in now to get your B Coins!";
                } else if ($consecutiveDaysCount == 7) {
                    UserCheckInModel::where('user_id', $user_id)->delete();
                }

                if ($shouldNotif) {
                    $notif->deep_link = '';
                    $notif->user_id = $user_id;
                    $notif->save();

                    event(new AdminMessage(
                        $notif->notification_id,
                        $notif->title,
                        $notif->message,
                        $notif->user_id,
                        $notif->deep_link
                    ));

                    $tokens = FCMNotification::where('user_id', $notif->user_id)
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
        }
    }
}
