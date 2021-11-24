<?php

namespace App\Http\Controllers;

use App\Helpers\CollectionHelper;
use App\Models\ActivityFeed;
use App\Models\BcoinLog;
use App\Models\FavoriteUsers;
use App\Models\FCMNotification;
use App\Models\Meetup;
use App\Models\MeetupOpenLogsModel;
use App\Models\MeetupParticipant;
use App\Models\Notifications;
use App\Models\User;
use App\Models\UserClubInterest;
use App\PusherEvents\BcoinAwarded;
use App\PusherEvents\MeetupReminder;
use App\PusherEvents\NewMeetupPosted;
use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MeetupController extends Controller
{
    /**
     * Display a wsting of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $per_page = !is_null($request->per_page) ? (int) $request->per_page : 80;

        $meetups = Meetup::with(['clubInterest' => function ($query) use ($request) {
            $query->withCount(['members as is_club_member' => function ($query) use ($request) {
                $query->where('user_id', auth()->user()->user_id);
            }]);
            $query->withCount(['participatedChallenges as challenges_done_count' => function ($query) use ($request) {
                $query->where('challenge_participants.user_id', auth()->user()->user_id)->where('status', 'DONE');
            }]);

            $query->withCount(['participatedMeetups as meetups_done_count' => function ($query) use ($request) {
                $query->where('meetup_participants.user_id', auth()->user()->user_id)->where('status', 'DONE');
            }]);

            $query->withCount(['participatedLiveSessions as live_session_done_count' => function ($query) use ($request) {
                $query->where('live_session_participants.user_id', auth()->user()->user_id)->where('status', 'DONE');
            }]);
        }])
            ->whereNull('deleted_at')
            ->whereNotNull('published_at')
            ->withCount([
                'countParticipants as participants_count',
                // 'participants',
                'participants as is_joined_meetup' => function ($query) {
                    $query->where('user_id', auth()->user()->user_id);
                },
                'isOpen as is_open' => function ($query) {
                    $query->where('user_id', auth()->user()->user_id);
                }
            ]);

        // filter based on user's my_interest
        if ($request->my_interest == 1) {
            $clubs = UserClubInterest::where("user_id", auth()->user()->user_id)
                ->pluck('interest_id');
            $meetups->whereIn('interest_id', $clubs);
        }

        // filter user's post based
        if ($request->is_part_of == 1) {
            $meetups->whereHas('participants', function ($query) use ($request) {
                $query->where('user_id', auth()->user()->user_id);
            });
        }

        if (!is_null($request->search)) {
            $keywords = explode(" ", $request->search);
            $meetups->where(function ($q) use ($keywords, $request) {
                $q->where('title', 'like', "%" . $request->search . "%");
                foreach ($keywords as $keyword) {
                    $q->orWhere('title', 'like', "%" . $keyword . "%")
                        ->orWhere('description', 'like', "%" . $keyword . "%");
                }
            });
        }

        // clone meetup and get all ended meetups
        $endedMeetups = clone $meetups;
        $endedMeetups
            ->orderBy('ended_at', 'desc')
            ->where('ended_at', '<=', now());

        // clone meetup and get all active meetups
        $activeMeetups = clone $meetups;
        $activeMeetups
            ->orderBy('ended_at', 'asc')
            ->where('ended_at', '>=', now());
        $responseObject = collect(
            ['ongoing_activity' => $activeMeetups->get()]
        );
        return $responseObject->merge($endedMeetups->simplePaginate($per_page));
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

        if ($request->meetup_id) {
            // call update function
            return $this->update($request, $request->meetup_id);
        }

        $meetup = new Meetup();
        $meetup->interest_id = $request->interest_id;
        $meetup->user_id = auth()->user()->user_id;
        $meetup->title = $request->title;
        $meetup->notification_message = $request->notification_message;
        $meetup->description = $request->description;
        $meetup->html_content = $request->html_content;
        $meetup->bcoin_reward = $request->bcoin_reward;
        // $meetup->started_at = $request->started_at;
        // $meetup->ended_at = $request->ended_at;
        // $meetup->registration_ended_at = $request->registration_ended_at;
        if (!is_null($request->registration_ended_at)) {
            $registration_ended_at = Carbon::createFromFormat('Y-m-d H:i', $request->registration_ended_at)
                ->tz('UTC')
                ->subHours(8);
            $meetup->registration_ended_at = $registration_ended_at;
        }

        if (!is_null($request->started_at)) {
            $started_at = Carbon::createFromFormat('Y-m-d H:i', $request->started_at)
                ->tz('UTC')
                ->subHours(8);
            $meetup->started_at = $started_at;
        }

        if (!is_null($request->ended_at)) {
            $ended_at = Carbon::createFromFormat('Y-m-d H:i', $request->ended_at)
                ->tz('UTC')
                ->subHours(8);
            $meetup->ended_at = $ended_at;
        }

        $meetup->is_featured = $request->is_featured;
        $meetup->slots = $request->slots;
        $meetup->host_name = $request->host_name;
        $meetup->host_email = $request->host_email;
        $meetup->additional_details = $request->additional_details;
        $meetup->virtual_room_link = $request->virtual_room_link;
        $meetup->venue = $request->venue;
        $meetup->recording_link = $request->recording_link;
        $meetup->user_id = auth()->user()->user_id;
        $meetup->published_at = now();
        $meetup->save();


        if (!is_null($meetup->meetup_id))
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
                        $request->image_cover->storeAs('/public/images/meetup/' . $meetup->meetup_id, $newFileName);
                        $meetup->update(["image_cover" => $newFileName]);
                    } else {
                        return response(["error" => ["image" => $validator->errors()->get('image_cover')]], 400);
                    }
                }
            }

        // create record in news feed
        $newActivityFeed = new Request([
            "meetup_id" => $meetup->meetup_id,
            "feed_type" => "meetup",
            "title" => $meetup->title,
            "content" => $meetup->description,
            "published_at" => 1,
            "user_id" => $meetup->user_id
        ]);

        $activityFeed = new ActivityFeedController();
        $activityFeed->store($newActivityFeed);

        if (!is_null($meetup->published_at)) {
            $users = UserClubInterest::where("interest_id", $meetup->interest_id)->get();
            foreach ($users as $user) {
                $notif = new Notifications();
                $notif->title = $meetup->title;
                $notif->message = $meetup->notification_message;
                $notif->user_id = $user->user_id;
                $notif->meetup_id = $meetup->meetup_id;
                $notif->deep_link = "meetup/" . $meetup->meetup_id;
                $notif->save();
                event(new NewMeetupPosted(
                    $notif->notification_id,
                    $notif->title,
                    $notif->message,
                    $notif->user_id,
                    $notif->meetup_id,
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
        return ["data" => $meetup];
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Meetup  $meetup
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $meetupDetail = Meetup::with([
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
            },
        ])
            ->find($id);

        if ($meetupDetail) {
            $meetupDetail->first();
            $meetupDetail->participants_count = $meetupDetail
                ->countParticipants
                ->count();

            $meetupDetail->is_joined_meetup =  $meetupDetail->participants->where('user_id', auth()->user()->user_id)->count();
            $meetupDetail->participant =  $meetupDetail->participants->where('user_id', auth()->user()->user_id)->first();

            // check if user opened the meetup
            $checkIsOpen = MeetupOpenLogsModel::where('user_id', auth()->user()->user_id)
                ->where('meetup_id', $meetupDetail->meetup_id)
                ->first();

            if (is_null($checkIsOpen)) {
                // save that user_id open this meetup
                $userOpen = new MeetupOpenLogsModel();
                $userOpen->user_id = auth()->user()->user_id;
                $userOpen->meetup_id = $meetupDetail->meetup_id;
                $userOpen->save();
            }

            unset($meetupDetail->participants);
            unset($meetupDetail->countParticipants);
            return ["data" => $meetupDetail];
        }
        return ["error" => ["message" => "No meetup found."]];
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Meetup  $meetup
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
     * @param  \App\Models\Meetup  $meetup
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
            "is_editor_pick",
            "is_featured",
            "host_name",
            "host_email",
            "additional_details",
            "virtual_room_link",
            "venue",
            "recording_link",
        ]);

        // $request->images

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

        $meetup = Meetup::where('meetup_id', $id)->first();

        if (!is_null($meetup)) {
            $meetup->update(array_merge(
                (array) $fieldsToUpdate,
                (array) [
                    "started_at" => $started_at,
                    "registration_ended_at" => $registration_ended_at,
                    "ended_at" => $ended_at,
                ]
            ));

            $updateActivityFeed = ActivityFeed::where('meetup_id', $meetup->meetup_id)
                ->where('feed_type', 'meetup')->first();
            if (!is_null($updateActivityFeed)) {
                $updateActivityFeed->update(
                    [
                        "title" => $meetup->title,
                        "content" => $meetup->description
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
                        $request->image_cover->storeAs('/public/images/meetup/' .  $id, $newFileName);
                        $meetup->update(["image_cover" => $newFileName]);
                    } else {
                        return response(["error" => ["image" => $validator->errors()->get('image_cover')]], 400);
                    }
                }
            }
        }

        return ["data" => $meetup];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Meetup  $meetup
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $meetup = Meetup::find($id);
        if ($meetup) {

            // delete all post in activity feed
            ActivityFeed::where('feed_type', 'feed')->where('meetup_id', $meetup->meetup_id)->delete();

            // delete all activity feed related to meetup_id
            $meetupFeed = ActivityFeed::where('feed_type', 'meetup')->where('meetup_id', $meetup->meetup_id);
            $meetupFeed->delete();

            // delete all activity feed related to meetup_id
            $deleteNotif = Notifications::where('meetup_id', $meetup->meetup_id);
            $deleteNotif->delete();

            $meetup->delete();
            return response()->json(["data" =>
            [
                "meetup" => $meetup
            ]]);
        }

        return response()->json(["data" =>
        [
            "meetup" => "No meetup deleted."
        ]]);
    }


    public function meetupParticipants(Request $request, $id)
    {
        $user_id = auth()->user()->user_id;
        $per_page = !is_null($request->per_page) ? (int) $request->per_page : 50;

        $meetupParticipants = MeetupParticipant::join('users', 'users.user_id', '=', 'meetup_participants.user_id')
            ->orderBy('users.first_name', 'asc')
            ->orderBy('users.last_name', 'asc')
            ->with(['user' => function ($query) use ($user_id) {
                $query
                    ->withCount(['favoriteUsers as is_favorite' => function ($query) use ($user_id) {
                        $query->where('user_id', $user_id);
                    }])
                    ->withSum(['bcoinTotal' => function ($query) {
                        $query->where('amount', '>', 0);
                    }], 'amount');
            }])
            ->select(['meetup_participants.user_id', 'meetup_participants.meetup_id', 'meetup_participants.status', 'meetup_participants.created_at'])
            ->where('meetup_id', intVal($id))
            // ->where('users.privilege', 'user')
            ->where('users.is_verified', 1)
            ->get();

        $meetupParticipantsCount = count($meetupParticipants);

        // favorite users
        $alreadyFavoriteInMeetup = $meetupParticipants
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
            ->whereNotIn('favorite_user_id', $alreadyFavoriteInMeetup->pluck('user_id'))
            ->get()
            ->toArray();

        $tempFave = [];

        foreach ($extraFavorites as $item) {
            array_push($tempFave, [
                "meetup_id" => null,
                "status" => null,
                "user_id" => $item['favorite_user_id'],
                "user" => $item['favorite_user'],
                "is_ghost" => 1,
            ]);
        }

        $favoriteUsers = collect(array_merge(
            $alreadyFavoriteInMeetup->toArray(),
            $tempFave
        ))->sortBy([
            ['user.first_name', 'asc'],
            ['user.last_name', 'asc']
        ]);

        // apply search
        if (!is_null($request->search)) {
            $meetupParticipants = $meetupParticipants->filter(function ($user) use ($request) {
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
            'participants_count' => $meetupParticipantsCount,
            'favorite_users' => $favoriteUsers->values(),
        ]);
        return $participantsCount->merge(
            CollectionHelper::paginate(
                $meetupParticipants,
                $per_page
            )
        );
    }


    public function joinOrLeaveMeetup($id, $status)
    {
        $isParticipant = MeetupParticipant::where('user_id', auth()->user()->user_id)
            ->where('meetup_id', intVal($id))->withTrashed();

        if ($status == "join") {
            $meetup = Meetup::withCount(['countParticipants as participants_count'])
                ->where('meetup_id', intVal($id))
                ->first();
            if ($meetup->slots - $meetup->participants_count > 0) {
                if (!$isParticipant->first()) {
                    // first time participant
                    $meetupParticipant = new MeetupParticipant();
                    $meetupParticipant->user_id = auth()->user()->user_id;
                    $meetupParticipant->meetup_id = $id;
                    $meetupParticipant->status = "PARTICIPATED";
                    $meetupParticipant->participated_at = now();
                    $meetupParticipant->save();
                    return $meetupParticipant;
                } else {
                    // participant before
                    return ["data" => [
                        "rows_changed" =>  $isParticipant->update([
                            "status" => "PARTICIPATED",
                            'participated_at' => now(),
                            "deleted_at" => null
                        ])
                    ]];
                }
            } else {
                return response(["error" => "No more slots available in this meetup."], 400);
            }
        } else if ($status == "leave") {
            $meetupParticipant = MeetupParticipant::where('user_id', auth()->user()->user_id)
                ->where('meetup_id', intVal($id))
                ->update([
                    "status" => "QUIT",
                    'participated_at' => null,
                    "deleted_at" => now()
                ]);
            return ["data" => [
                "rows_changed" => $meetupParticipant
            ]];
        }

        return ["error" => $isParticipant ? "User participated in this meetup." : "Join status is undefined."];
    }

    public function joinRoomLink(Request $request)
    {
        $meetupParticipant = MeetupParticipant::where('participant_id', $request->participant_id)->first();
        if ($meetupParticipant) {
            $meetupParticipant->update(["status" => 'DONE']);
            return $meetupParticipant;
        }
        return response(["error" => "No participation record found."], 400);
    }


    public function startingReminder()
    {
        // every 15 minutes
        $meetups = Meetup::with(['participants'])
            ->where('started_at', '=', now()->addHours(1)->format('Y-m-d H:i'))->get();
        foreach ($meetups as $meetup) {
            foreach ($meetup->participants as $participant) {
                // NOTIFICATION RECORD
                $notif = new Notifications();
                $notif->user_id = $participant->user_id;
                $notif->title = 'Meetup Starts in 1 hour';
                $notif->message = $meetup->title
                    . ' starts in 1 hour. See you there!';
                $notif->meetup_id = $meetup->meetup_id;
                $notif->deep_link = "meetup/" . $meetup->meetup_id;
                $notif->save();

                // EVENT NOTIFICATION
                event(new MeetupReminder(
                    $notif->notification_id,
                    $notif->title,
                    $notif->message,
                    $notif->user_id,
                    $notif->meetup_id,
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
        // every  24hr
        $meetups = Meetup::with(['participants'])
            ->where('started_at', '=', now()->addHours(24)->format('Y-m-d H:i'))->get();
        foreach ($meetups as $meetup) {
            foreach ($meetup->participants as $participant) {
                // NOTIFICATION RECORD
                $notif = new Notifications();
                $notif->user_id = $participant->user_id;
                $notif->title = 'Meetup Starts in 24 hours';
                $notif->message = $meetup->title
                    . ' starts in 24 hours. See you there!';
                $notif->meetup_id = $meetup->meetup_id;
                $notif->deep_link = "meetup/" . $meetup->meetup_id;
                $notif->save();

                // EVENT NOTIFICATION
                event(new MeetupReminder(
                    $notif->notification_id,
                    $notif->title,
                    $notif->message,
                    $notif->user_id,
                    $notif->meetup_id,
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


    public function eventEnded()
    {

        $current_time = now()->format('Y-m-d H:i');

        $meetups = Meetup::with('participants')
            ->where('ended_at', $current_time)->get();

        foreach ($meetups as $meetup) {
            foreach ($meetup->participants as $participant) {
                if (!is_null($meetup->venue)) {
                    if ($participant->status == "PARTICIPATED") {
                        continue;
                    }
                }

                // BCOIN AWARD
                $bcoinAward = new BcoinLog();
                $bcoinAward->user_id =  $participant->user_id;
                $bcoinAward->amount =  $meetup->bcoin_reward;
                $bcoinAward->description =
                    $meetup->bcoin_reward == 0 ?
                    "Congratulations! You have successfully completed the " . $meetup->title . "."
                    : "Congratulations! You have been awarded " . $meetup->bcoin_reward . " B Coins for completing the " . $meetup->title . ".";
                $bcoinAward->meetup_id =  $meetup->meetup_id;
                $bcoinAward->save();

                // NOTIFICATION RECORD
                $notif = new Notifications();
                $notif->user_id = $bcoinAward->user_id;
                $notif->title = $meetup->bcoin_reward == 0 ?  'Activity Completed' : 'B Coins Awarded';
                $notif->message = $bcoinAward->description;
                $notif->meetup_id = $bcoinAward->meetup_id;
                $notif->deep_link = 'bcoin-history';
                $notif->transaction_id = $bcoinAward->transaction_id;
                $notif->save();

                // EVENT NOTIFICATION
                event(new BcoinAwarded(
                    $notif->notification_id,
                    'B Coins Awarded',
                    $bcoinAward->description,
                    $bcoinAward->user_id
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
