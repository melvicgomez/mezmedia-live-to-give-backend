<?php

namespace App\Http\Controllers;

use App\Models\Charity;
use App\Models\CharityResponse;
use App\Models\FCMNotification;
use App\Models\Notifications;
use App\Models\User;
use App\PusherEvents\AdminMessage;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CharityResponseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $deadline = Carbon::parse('2021-07-18 23:59:00', "+08:00");
        $current_time = now("+08:00");
        if ($current_time->isBefore($deadline)) {
            $user_id = auth()->user()->user_id;
            $privilege = auth()->user()->privilege;
            $country_code = auth()->user()->country_code;
            $charities = Charity::where(
                'country_code',
                $country_code == "China" || $country_code == "Hong Kong SAR"  ?
                    "Hong Kong SAR" : $country_code
            )->get();
            if ($privilege == 'user') {
                if (count($charities) >= 1) {
                    // check if user if submitted a charity or not
                    if (count(CharityResponse::where('user_id', $user_id)->get()) === 0)
                        return [
                            'show_modal' => true,
                            'charities' => $charities
                        ];
                }
            }
        }
        return [
            'show_modal' => false,
            'charities' => []
        ];
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
        $charity_id = $request->charity_id;
        $user_id = auth()->user()->user_id;

        $user_found = CharityResponse::where('user_id', $user_id)->get();
        if (count($user_found) == 0) {
            $charityResponse = new CharityResponse();
            $charityResponse->charity_id = (int) $charity_id;
            $charityResponse->user_id = $user_id;
            $charityResponse->save();

            $country_code = auth()->user()->country_code;
            $charities = Charity::whereIn(
                'country_code',
                $country_code == "China" || $country_code == "Hong Kong SAR"
                    ? ["China", "Hong Kong SAR"] : [$country_code]
            )->get();
            return [
                'show_modal' => false,
                'charities' =>  $charities
            ];
        }
        abort(400);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\CharityResponse  $charityResponse
     * @return \Illuminate\Http\Response
     */
    public function show(CharityResponse $charityResponse)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\CharityResponse  $charityResponse
     * @return \Illuminate\Http\Response
     */
    public function edit(CharityResponse $charityResponse)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\CharityResponse  $charityResponse
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CharityResponse $charityResponse)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CharityResponse  $charityResponse
     * @return \Illuminate\Http\Response
     */
    public function destroy(CharityResponse $charityResponse)
    {
        //
    }

    public function checkCharityNotification()
    {
        $deadline = '2021-07-18 23:59';
        $current_time = now("+08:00")->format('Y-m-d H:i');
        $charityCountries = Charity::groupBy('country_code')
            ->selectRaw('count(*) as charity_count,country_code')
            ->get()
            ->filter(function ($value, $key) {
                return $value->charity_count >= 1;
            })
            ->values()
            ->pluck('country_code');
        // return $charityCountries;
        $users = User::whereDoesntHave('charityResponse')
            ->whereIn('country_code', $charityCountries)
            ->where('is_verified', 1)
            ->where('privilege', 'user')
            ->get();


        $deadline2W = Carbon::parse($deadline)->subWeeks(2);
        $deadline1W = Carbon::parse($deadline)->subWeeks(1);
        $deadline2D = Carbon::parse($deadline)->subDays(2);


        if (
            $deadline2W->format('Y-m-d H:i') === $current_time ||
            $deadline1W->format('Y-m-d H:i') === $current_time ||
            $deadline2D->format('Y-m-d H:i') === $current_time
        ) {
            foreach ($users as $user) {
                $notif = new Notifications();
                $notif->title = 'CHARITY FORM REMINDER';
                $notif->message = 'CHARITY REMINDER DESCRIPTION';


                if ($deadline2W->format('Y-m-d H:i') === $current_time) {
                    $notif->title = 'CHARITY FORM REMINDER 2W';
                    $notif->message = 'CHARITY REMINDER DESCRIPTION';
                }
                if ($deadline1W->format('Y-m-d H:i') === $current_time) {
                    $notif->title = 'CHARITY FORM REMINDER 1W';
                    $notif->message = 'CHARITY REMINDER DESCRIPTION';
                }
                if ($deadline2D->format('Y-m-d H:i') === $current_time) {
                    $notif->title = 'CHARITY FORM REMINDER 2D';
                    $notif->message = 'CHARITY REMINDER DESCRIPTION';
                }

                $notif->deep_link = '';
                $notif->user_id = (int) $user->user_id;
                $notif->save();

                event(new AdminMessage(
                    $notif->notification_id,
                    $notif->title,
                    $notif->message,
                    (int)$user->user_id,
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
                    ["url" => $notif->deep_link]
                );
            }
            return response(null, 200);
        }
        return response(null, 204);
    }

    public function getCharityResponse()
    {
        if (auth()->user()->privilege == "moderator") {
            $charityCountries = Charity::groupBy('country_code')
                ->selectRaw('count(*) as charity_count,country_code')
                ->get()
                ->filter(function ($value) {
                    return $value->charity_count >= 1;
                })
                ->values()
                ->pluck('country_code');

            $finalCharityResponses = [];
            foreach ($charityCountries as $country) {
                $countryCharity = Charity::select('charity_id', 'charity_name', 'description', 'country_code')
                    ->with(['images'])
                    ->where("country_code", $country);

                $totalUsers = User::where("is_verified", 1)
                    ->where("privilege", "user")
                    ->whereIn(
                        "country_code",
                        $country == "Hong Kong SAR" ? ["China", "Hong Kong SAR"] : [$country]
                    );

                $usersId = $totalUsers->pluck('user_id');
                $totalUsersRespond = CharityResponse::orWhereIn('charity_id', $countryCharity->pluck('charity_id'))
                    ->whereIn('user_id', $usersId);

                $totalUsersRespondToBoth = CharityResponse::where('charity_id', 0)
                    ->whereIn('user_id', $usersId);

                $finalCountryCharity = $countryCharity->get()
                    ->map(
                        function ($item) use ($usersId) {
                            $item->response_count =
                                CharityResponse::where('charity_id', $item->charity_id)
                                ->whereIn('user_id', $usersId)
                                ->count();
                            return $item;
                        }
                    );

                array_push($finalCharityResponses, [
                    "country_code" => $country,
                    "total_users" => $totalUsers->count(),
                    "total_users_respond" => $totalUsersRespond->count(),
                    "total_users_havent_respond" => $totalUsers->count() - ($totalUsersRespond->count() + $totalUsersRespondToBoth->count()),
                    "total_users_respond_both" => $totalUsersRespondToBoth->count(),
                    "charities" => $finalCountryCharity,
                ]);
            }
            return $finalCharityResponses;
        }
        abort(404);
    }
}
