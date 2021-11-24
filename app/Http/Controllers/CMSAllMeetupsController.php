<?php

namespace App\Http\Controllers;

use App\Models\Meetup;
use App\Models\ActivityFeed;
use App\Models\FCMNotification;
use App\Models\Notifications;
use App\Models\UserClubInterest;
use App\PusherEvents\NewMeetupPosted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CMSAllMeetupsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (auth()->user()->privilege == "moderator") {

            $showByStatus = $request->show_status ?: 'all'; // all | flagged | deleted
            $order_by = $request->order_by ?: 'meetup_id'; // column name
            $sort_by = $request->sort_by ?: 'desc'; // asc | desc

            $meetups = Meetup::whereNull('deleted_at')
                ->withCount([
                    'participants',
                ]);

            if (!is_null($request->search)) {
                $meetups->where(function ($q) use ($request) {
                    $q->where('title', 'like', "%" . $request->search . "%")
                        ->orWhere('description', 'like', "%" . $request->search . "%")
                        ->orWhere('html_content', 'like', "%" . $request->search . "%")
                        ->orWhere('host_name', 'like', "%" . $request->search . "%")
                        ->orWhere('additional_details', 'like', "%" . $request->search . "%");
                });
            }

            $meetups->orderBy($order_by, $sort_by);

            return $meetups->paginate(200);
        };
        abort(400);
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
            if ($request->meetup_id) {
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

            $meetup->started_at = $request->started_at;
            $meetup->ended_at = $request->ended_at;
            $meetup->registration_ended_at = $request->registration_ended_at;

            $meetup->is_featured = $request->is_featured;
            $meetup->slots = $request->slots;
            $meetup->host_name = $request->host_name;
            $meetup->host_email = $request->host_email;
            $meetup->additional_details = $request->additional_details;
            $meetup->virtual_room_link = $request->virtual_room_link;
            $meetup->venue = $request->venue;
            $meetup->recording_link = $request->recording_link;
            $meetup->user_id = auth()->user()->user_id;

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

            return ["data" => $meetup];
        };
        abort(400);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (auth()->user()->privilege == "moderator") {
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
                $meetupDetail->participants_count =  $meetupDetail->participants->count();
                unset($meetupDetail->participants);

                return ["data" => $meetupDetail];
            }
            return ["error" => ["message" => "No meetup found."]];
        };
        abort(400);
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
        $fieldsToUpdate = $request->only([
            "interest_id",
            "user_id",
            "title",
            "description",
            'notification_message',
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
            'registration_ended_at',
            'started_at',
            'ended_at',
            "recording_link",
        ]);

        $meetup = Meetup::where('meetup_id', $id)->first();

        if (!is_null($meetup)) {
            $meetup->update($fieldsToUpdate);

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
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (auth()->user()->privilege == "moderator") {

            $meetup = Meetup::find($id);
            if ($meetup) {
                if ($meetup->published_at) {
                    // delete all post in activity feed
                    ActivityFeed::where('feed_type', 'feed')->where('meetup_id', $meetup->meetup_id)->delete();

                    // delete all activity feed related to meetup_id
                    $meetupFeed = ActivityFeed::where('feed_type', 'meetup')->where('meetup_id', $meetup->meetup_id);
                    $meetupFeed->delete();

                    // delete all activity feed related to meetup_id
                    $deleteNotif = Notifications::where('meetup_id', $meetup->meetup_id);
                    $deleteNotif->delete();
                }

                $meetup->delete();
                return response()->json(["data" => ["meetup" => $meetup]]);
            }

            return response()->json(["data" =>
            [
                "meetup" => "No meetup deleted."
            ]]);
        };
        abort(400);
    }

    public function publishMeetup(Request $request, $id)
    {
        if (auth()->user()->privilege == "moderator") {
            $meetup = Meetup::find($id);
            if (!is_null($meetup)) {
                if ($request->action == 'publish') {
                    $meetup->update(["published_at" => now()]);

                    $existingActivityFeed = ActivityFeed::where('meetup_id', $meetup->challenge_id)
                        ->where('feed_type', 'meetup')
                        ->where('title', $meetup->title)
                        ->where('content', $meetup->description)
                        ->first();

                    if (is_null($existingActivityFeed)) {

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
                } else {
                    $meetup->update(["published_at" => null]);

                    // delete all post in activity feed
                    ActivityFeed::where('feed_type', 'feed')->where('meetup_id', $meetup->meetup_id)->delete();

                    // delete all activity feed related to meetup_id
                    $meetupFeed = ActivityFeed::where('feed_type', 'meetup')->where('meetup_id', $meetup->meetup_id);
                    $meetupFeed->delete();

                    // delete all activity feed related to meetup_id
                    $deleteNotif = Notifications::where('meetup_id', $meetup->meetup_id);
                    $deleteNotif->delete();

                    return ["data" => $meetup];
                }
            }
        }
        abort(404);
    }
}
