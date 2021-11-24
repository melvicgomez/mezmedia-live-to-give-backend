<?php

namespace App\Http\Controllers;

use App\Helpers\CollectionHelper;
use App\Models\ActivityFeed;
use App\Models\BcoinLog;
use App\Models\FavoriteUsers;
use App\Models\FCMNotification;
use App\Models\LiveSession;
use App\Models\LiveSessionOpenLogsModel;
use App\Models\LiveSessionParticipant;
use App\Models\Notifications;
use App\Models\User;
use App\Models\UserClubInterest;
use App\PusherEvents\BcoinAwarded;
use App\PusherEvents\LiveSessionReminder;
use App\PusherEvents\NewLiveSessionPosted;
use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LiveSessionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $per_page = !is_null($request->per_page) ? (int) $request->per_page : 80;

        $liveSessions = LiveSession::with(['clubInterest' => function ($query) {
            $query->withCount(['members as is_club_member' => function ($query) {
                $query->where('user_id', auth()->user()->user_id);
            }]);
            $query->withCount(['participatedChallenges as challenges_done_count' => function ($query) {
                $query->where('challenge_participants.user_id', auth()->user()->user_id)->where('status', 'DONE');
            }]);

            $query->withCount(['participatedMeetups as meetups_done_count' => function ($query) {
                $query->where('meetup_participants.user_id', auth()->user()->user_id)->where('status', 'DONE');
            }]);

            $query->withCount(['participatedLiveSessions as live_session_done_count' => function ($query) {
                $query->where('live_session_participants.user_id', auth()->user()->user_id)->where('status', 'DONE');
            }]);
        }])
            ->whereNull('deleted_at')
            ->whereNotNull('published_at')
            ->withCount([
                'countParticipants as participants_count',
                // 'participants',
                'participants as is_joined_live_session' => function ($query) {
                    $query->where('user_id', auth()->user()->user_id);
                },
                'isOpen as is_open' => function ($query) {
                    $query->where('user_id', auth()->user()->user_id);
                }
            ]);


        if ($request->my_interest == 1) {
            $clubs = UserClubInterest::where("user_id", auth()->user()->user_id)
                ->pluck('interest_id');
            $liveSessions->whereIn('interest_id', $clubs);
        }

        // filter user's post based
        if ($request->is_part_of == 1) {
            $liveSessions->whereHas('participants', function ($query) {
                $query->where('user_id', auth()->user()->user_id);
            });
        }

        if (!is_null($request->search)) {
            $keywords = explode(" ", $request->search);
            $liveSessions->where(function ($q) use ($keywords, $request) {
                $q->where('title', 'like', "%" . $request->search . "%");
                foreach ($keywords as $keyword) {
                    $q->orWhere('title', 'like', "%" . $keyword . "%")
                        ->orWhere('description', 'like', "%" . $keyword . "%");
                }
            });
        }

        // clone meetup and get all ended liveSessions
        $endedLiveSessions = clone $liveSessions;
        $endedLiveSessions
            ->orderBy('ended_at', 'desc')
            ->where('ended_at', '<=', now());

        // clone meetup and get all active liveSessions
        $activeLiveSessions = clone $liveSessions;
        $activeLiveSessions
            ->orderBy('ended_at', 'asc')
            ->where('ended_at', '>=', now());
        $responseObject = collect(
            ['ongoing_activity' => $activeLiveSessions->get()]
        );

        return $responseObject->merge($endedLiveSessions->simplePaginate($per_page));
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

        if ($request->live_id) {
            // call update function
            return $this->update($request, $request->live_id);
        }

        $liveSession = new LiveSession();
        $liveSession->interest_id = $request->interest_id;
        $liveSession->title = $request->title;
        $liveSession->notification_message = $request->notification_message;
        $liveSession->description = $request->description;
        $liveSession->html_content = $request->html_content;
        $liveSession->bcoin_reward = $request->bcoin_reward;

        if (!is_null($request->registration_ended_at)) {
            $registration_ended_at = Carbon::createFromFormat('Y-m-d H:i', $request->registration_ended_at)
                ->tz('UTC')
                ->subHours(8);
            $liveSession->registration_ended_at = $registration_ended_at;
        }

        if (!is_null($request->started_at)) {
            $started_at = Carbon::createFromFormat('Y-m-d H:i', $request->started_at)
                ->tz('UTC')
                ->subHours(8);
            $liveSession->started_at = $started_at;
        }

        if (!is_null($request->ended_at)) {
            $ended_at = Carbon::createFromFormat('Y-m-d H:i', $request->ended_at)
                ->tz('UTC')
                ->subHours(8);
            $liveSession->ended_at = $ended_at;
        }

        $liveSession->is_featured = $request->is_featured;
        $liveSession->is_editor_pick = $request->is_editor_pick;
        $liveSession->slots = $request->slots;
        $liveSession->host_name = $request->host_name;
        $liveSession->host_email = $request->host_email;
        $liveSession->additional_details = $request->additional_details;
        $liveSession->virtual_room_link = $request->virtual_room_link;
        $liveSession->recording_link = $request->recording_link;
        $liveSession->user_id = auth()->user()->user_id;

        $liveSession->published_at = now();
        $liveSession->save();

        if (!is_null($liveSession->live_id))
            if ($request->hasFile('image_cover')) {
                if ($request->file('image_cover')->isValid()) {
                    $validator = Validator::make($request->all(), [
                        'image_cover' => 'mimes:jpg,jpeg,png|max:10240'
                    ], [
                        'image_cover.mimes' => 'Only jpeg, png, and jpg images are allowed',
                        'image_cover.max' => 'Sorry! Maximum allowed size for an image is 10MB',
                    ]);

                    if (!$validator->fails()) {
                        $randomHex1 = bin2hex(random_bytes(6));
                        $randomHex2 = bin2hex(random_bytes(6));
                        $uploadDate = now()->year . "-" . now()->month . "-" . now()->day;
                        $extension = $request->image_cover->extension();
                        $newFileName = $uploadDate . '-' . $randomHex1 . '-' . $randomHex2 . '.'  . $extension;
                        $request->image_cover->storeAs('/public/images/live-session/' . $liveSession->live_id, $newFileName);
                        $liveSession->update(["image_cover" => $newFileName]);
                    } else {
                        return response(["error" => ["image" => $validator->errors()->get('image_cover')]], 400);
                    }
                }
            }

        // create record in news feed
        $newActivityFeed = new Request([
            "live_id" => $liveSession->live_id,
            "feed_type" => "live session",
            "title" => $liveSession->title,
            "content" => $liveSession->description,
            "published_at" => 1,
            "user_id" => $liveSession->user_id
        ]);

        $activityFeed = new ActivityFeedController();
        $activityFeed->store($newActivityFeed);


        if (!is_null($liveSession->published_at)) {
            $users = UserClubInterest::where("interest_id", $liveSession->interest_id)->get();
            foreach ($users as $user) {
                $notif = new Notifications();
                $notif->title = $liveSession->title;
                $notif->message = $liveSession->notification_message;
                $notif->user_id = $user->user_id;
                $notif->live_id = $liveSession->live_id;
                $notif->deep_link = "live-session/" . $liveSession->live_id;
                $notif->save();

                event(new NewLiveSessionPosted(
                    $notif->notification_id,
                    $notif->title,
                    $notif->message,
                    $user->user_id,
                    $liveSession->live_id,
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
        return ["data" => $liveSession];
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\LiveSession  $liveSession
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $liveSessionDetail = LiveSession::with([
            'clubInterest.club',
            'clubInterest' => function ($query) {
                $query->withCount(['members as is_club_member' => function ($query) {
                    $query->where('user_id', auth()->user()->user_id);
                }]);

                $query->withCount(['participatedChallenges as challenges_done_count' => function ($query) {
                    $query->where('challenge_participants.user_id', auth()->user()->user_id)
                        ->where('status', 'DONE');
                }]);

                $query->withCount(['participatedMeetups as meetups_done_count' => function ($query) {
                    $query->where('meetup_participants.user_id', auth()->user()->user_id)
                        ->where('status', 'DONE');
                }]);

                $query->withCount(['participatedLiveSessions as live_session_done_count' => function ($query) {
                    $query->where('live_session_participants.user_id', auth()->user()->user_id)
                        ->where('status', 'DONE');
                }]);
            }
        ])
            ->find($id);

        if ($liveSessionDetail) {
            $liveSessionDetail->first();
            $liveSessionDetail->participants_count =  $liveSessionDetail->participants->count();
            $liveSessionDetail->participants_count =  $liveSessionDetail->countParticipants->count();

            $liveSessionDetail->is_joined_live_session =  $liveSessionDetail->participants->where('user_id', auth()->user()->user_id)->count();
            $liveSessionDetail->participant =  $liveSessionDetail->participants->where('user_id', auth()->user()->user_id)->first();
            // unset($liveSessionDetail->participants);

            // check if user opened the live session
            $checkIsOpen = LiveSessionOpenLogsModel::where('user_id', auth()->user()->user_id)
                ->where('live_id', $liveSessionDetail->live_id)
                ->first();

            if (is_null($checkIsOpen)) {
                // save that user_id open this live session
                $userOpen = new LiveSessionOpenLogsModel();
                $userOpen->user_id = auth()->user()->user_id;
                $userOpen->live_id = $liveSessionDetail->live_id;
                $userOpen->save();
            }
            unset($liveSessionDetail->participants);
            unset($liveSessionDetail->countParticipants);
            return ["data" => $liveSessionDetail];
        }
        return ["error" => ["message" => "No live session found."]];
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\LiveSession  $liveSession
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
     * @param  \App\Models\LiveSession  $liveSession
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

        $fieldsToUpdate = $request->only([
            "interest_id",
            "user_id",
            "title",
            "description",
            "html_content",
            "bcoin_reward",
            "slots",
            "is_featured",
            "is_editor_pick",
            "host_name",
            "host_email",
            "additional_details",
            "virtual_room_link",
            "recording_link",
        ]);


        $registration_ended_at = NULL;
        $started_at = NULL;
        $ended_at = NULL;

        if (!is_null($request->registration_ended_at)) {
            $registration_ended_at = Carbon::createFromFormat('Y-m-d H:i', $request->registration_ended_at)
                ->tz('UTC')
                ->subHours(8);
        }

        if (!is_null($request->started_at)) {
            $started_at = Carbon::createFromFormat('Y-m-d H:i', $request->started_at)
                ->tz('UTC')
                ->subHours(8);
        }

        if (!is_null($request->ended_at)) {
            $ended_at = Carbon::createFromFormat('Y-m-d H:i', $request->ended_at)
                ->tz('UTC')
                ->subHours(8);
        };

        $liveSession = LiveSession::where('live_id', $id)->first();

        if (!is_null($liveSession)) {
            $liveSession->update(
                array_merge(
                    (array) $fieldsToUpdate,
                    (array) [
                        "started_at" => $started_at,
                        "registration_ended_at" => $registration_ended_at,
                        "ended_at" => $ended_at,
                    ]
                )
            );
            $updateActivityFeed = ActivityFeed::where('live_id', $liveSession->live_id)
                ->where('feed_type', 'live session')->first();

            if (!is_null($updateActivityFeed)) {
                $updateActivityFeed->update(
                    [
                        "title" => $liveSession->title,
                        "content" => $liveSession->description
                    ]
                );
            }

            if ($request->hasFile('image_cover')) {
                if ($request->file('image_cover')->isValid()) {
                    $validator = Validator::make($request->all(), [
                        'image_cover' => 'mimes:jpg,jpeg,png|max:10240'
                    ], [
                        'image_cover.mimes' => 'Only jpeg, png, and jpg images are allowed',
                        'image_cover.max' => 'Sorry! Maximum allowed size for an image is 10MB',
                    ]);

                    if (!$validator->fails()) {
                        $randomHex1 = bin2hex(random_bytes(6));
                        $randomHex2 = bin2hex(random_bytes(6));
                        $uploadDate = now()->year . "-" . now()->month . "-" . now()->day;
                        $extension = $request->image_cover->extension();
                        $newFileName = $uploadDate . '-' . $randomHex1 . '-' . $randomHex2 . '.'  . $extension;
                        $request->image_cover->storeAs('/public/images/live-session/' .  $id, $newFileName);
                        $liveSession->update(["image_cover" => $newFileName]);
                    } else {
                        return response(["error" => ["image" => $validator->errors()->get('image_cover')]], 400);
                    }
                }
            }
        }
        return ["data" => $liveSession];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\LiveSession  $liveSession
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $liveSession = LiveSession::find($id);
        if ($liveSession) {

            // delete all post in activity feed
            ActivityFeed::where('feed_type', 'feed')->where('live_id', $liveSession->live_id)->delete();

            // delete all activity feed related to live_id
            $liveFeed = ActivityFeed::where('feed_type', 'live session')->where('live_id', $liveSession->live_id);
            $liveFeed->delete();

            // delete all activity feed related to meetup_id
            $deleteNotif = Notifications::where('live_id', $liveSession->live_id);
            $deleteNotif->delete();

            $liveSession->delete();
            return response()->json(["data" =>
            [
                "live_session" => $liveSession
            ]]);
        }

        return response()->json(["data" =>
        [
            "live_session" => "No live session deleted."
        ]]);
    }

    public function liveSessionParticipants(Request $request, $id)
    {
        $user_id = auth()->user()->user_id;
        $per_page = !is_null($request->per_page) ? (int) $request->per_page : 50;

        $liveSessionParticipants = LiveSessionParticipant::join('users', 'users.user_id', '=', 'live_session_participants.user_id')
            ->orderBy('users.first_name', 'asc')
            ->orderBy('users.last_name', 'asc')
            ->select(['live_session_participants.user_id', 'live_session_participants.live_id', 'live_session_participants.status', 'live_session_participants.created_at'])
            ->with(['user' => function ($query) use ($user_id) {
                $query
                    ->withCount(['favoriteUsers as is_favorite' => function ($query) use ($user_id) {
                        $query->where('user_id', $user_id);
                    }])
                    ->withSum(['bcoinTotal' => function ($query) {
                        $query->where('amount', '>', 0);
                    }], 'amount');
            }])
            ->where('live_id', intVal($id))
            // ->where('users.privilege', 'user')
            ->where('users.is_verified', 1)
            ->get();


        $liveSessionParticipantsCount = count($liveSessionParticipants);

        // favorite users
        $alreadyFavoriteIn = $liveSessionParticipants
            ->filter(function ($user) {
                return $user->user->is_favorite == 1;
            });


        $extraFavorites = FavoriteUsers::with(['favoriteUser' => function ($query) use ($user_id) {
            $query
                ->withCount(['favoriteUsers as is_favorite' => function ($query) use ($user_id) {
                    $query->where('user_id', $user_id);
                }])
                ->withSum(['bcoinTotal' => function ($query) {
                    $query->where('amount', '>', 0);
                }], 'amount');
        }])
            ->where('user_id', $user_id)
            ->whereNotIn('favorite_user_id', $alreadyFavoriteIn->pluck('user_id'))
            ->get()
            ->toArray();

        $tempFave = [];
        foreach ($extraFavorites as $item) {
            array_push($tempFave, [
                "live_id" => null,
                "status" => null,
                "user_id" => $item['favorite_user_id'],
                "user" => $item['favorite_user'],
                "is_ghost" => 1,
            ]);
        }

        $favoriteUsers = collect(array_merge(
            $alreadyFavoriteIn
                ->sortBy([
                    ['user.first_name', 'asc'],
                    ['user.last_name', 'asc']
                ])
                ->toArray(),
            collect($tempFave)
                ->sortBy([
                    ['user.first_name', 'asc'],
                    ['user.last_name', 'asc']
                ])
                ->toArray()
        ));

        // apply search
        if (!is_null($request->search)) {
            $liveSessionParticipants = $liveSessionParticipants->filter(function ($user) use ($request) {
                $found = false;
                $keywords = explode(" ", $request->search);
                $user_name = strtolower($user->user->first_name . " " . $user->user->last_name);
                // uncomment if want to include country in search
                // $user_name = strtolower(
                //     $user->user->first_name . " " .
                //         $user->user->last_name . " " .
                //         $user->user->country_code
                // );
                foreach ($keywords as $keyword) {
                    // check each word from keywords if it exists in the $user_name
                    if (strpos($user_name, strtolower($keyword)) !== false) {
                        // break the loop if keyword found a match
                        $found = true;
                        break;
                    }
                }
                return $found;
            });
        }

        $participantsCount = collect([
            'participants_count' => $liveSessionParticipantsCount,
            'favorite_users' => $favoriteUsers->values(),
        ]);
        return $participantsCount->merge(
            CollectionHelper::paginate(
                $liveSessionParticipants,
                $per_page
            )
        );
    }


    public function joinOrLeaveLiveSession($id, $status)
    {
        $isParticipant = LiveSessionParticipant::where('user_id', auth()->user()->user_id)
            ->where('live_id', intVal($id))->withTrashed();

        if ($status == "join") {
            $liveSession = LiveSession::withCount(['countParticipants as participants_count'])->where('live_id', intVal($id))->first();
            if ($liveSession->slots - $liveSession->participants_count > 0) {
                if (!$isParticipant->first()) {
                    $sessionParticipant = new LiveSessionParticipant();
                    $sessionParticipant->user_id = auth()->user()->user_id;;
                    $sessionParticipant->live_id = $id;
                    $sessionParticipant->status = "PARTICIPATED";
                    $sessionParticipant->participated_at = now();
                    $sessionParticipant->save();
                    return $sessionParticipant;
                } else {
                    return ["data" => [
                        "rows_changed" =>  $isParticipant->update([
                            "status" => "PARTICIPATED",
                            "participated_at" => now(),
                            "deleted_at" => null
                        ])
                    ]];
                }
            } else {
                return response(["error" => "No more slots available in this live session."], 400);
            }
        } else if ($status == "leave") {
            $sessionParticipant = LiveSessionParticipant::where('user_id', auth()->user()->user_id)
                ->where('live_id', intVal($id));
            $sessionParticipant
                ->update([
                    "status" => "QUIT",
                    "participated_at" => null,
                    "deleted_at" => now()
                ]);
            return ["data" => [
                "rows_changed" => $sessionParticipant
            ]];
        }

        return ["error" => $isParticipant ? "User participated in this live session." : "Join status is undefined."];
    }


    public function joinRoomLink(Request $request)
    {
        $liveSessionParticipant = LiveSessionParticipant::where('participant_id', $request->participant_id)->first();
        if ($liveSessionParticipant) {
            $liveSessionParticipant->update(["status" => 'DONE']);
            return $liveSessionParticipant;
        }
        return response(["error" => "No participation record found."], 400);
    }


    public function startingReminder()
    {
        // every 15 minutes
        $liveSessions = LiveSession::with(['participants'])
            ->where('started_at', '=', now()->addHours(1)->format('Y-m-d H:i'))->get();

        foreach ($liveSessions as $liveSession) {
            foreach ($liveSession->participants as $participant) {
                // NOTIFICATION RECORD
                $notif = new Notifications();
                $notif->user_id = $participant->user_id;
                $notif->title = 'Live Session Starts in 1 hour';
                $notif->message = $liveSession->title
                    . ' starts in 1 hour. See you there!';
                $notif->live_id = $liveSession->live_id;
                $notif->deep_link = "live-session/" . $liveSession->live_id;
                $notif->save();

                // EVENT NOTIFICATION
                event(new LiveSessionReminder(
                    $notif->notification_id,
                    $notif->title,
                    $notif->message,
                    $notif->user_id,
                    $notif->live_id,
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


    public function startingReminder24Hr()
    {
        // every 24hr
        $liveSessions = LiveSession::with(['participants'])
            ->where('started_at', '=', now()->addHours(24)->format('Y-m-d H:i'))->get();
        foreach ($liveSessions as $liveSession) {
            foreach ($liveSession->participants as $participant) {
                // NOTIFICATION RECORD
                $notif = new Notifications();
                $notif->user_id = $participant->user_id;
                $notif->title = 'Live Session Starts in 24 hours';
                $notif->message = $liveSession->title
                    . ' starts in 24 hours. See you there!';
                $notif->live_id = $liveSession->live_id;
                $notif->deep_link = "live-session/" . $liveSession->live_id;
                $notif->save();

                // EVENT NOTIFICATION
                event(new LiveSessionReminder(
                    $notif->notification_id,
                    $notif->title,
                    $notif->message,
                    $notif->user_id,
                    $notif->live_id,
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
