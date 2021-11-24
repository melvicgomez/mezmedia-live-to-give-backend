<?php

namespace App\Http\Controllers;

use App\Models\ActivityFeed;
use App\Models\LiveSession;
use App\Models\FCMNotification;
use App\Models\Notifications;
use App\Models\UserClubInterest;
use App\PusherEvents\NewLiveSessionPosted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CMSAllLiveSessionsController extends Controller
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
            $order_by = $request->order_by ?: 'live_id'; // column name
            $sort_by = $request->sort_by ?: 'desc'; // asc | desc

            $liveSessions = LiveSession::whereNull('deleted_at')
                ->withCount([
                    'participants',
                ]);

            if (!is_null($request->search)) {
                $liveSessions->where(function ($q) use ($request) {
                    $q->where('title', 'like', "%" . $request->search . "%")
                        ->orWhere('description', 'like', "%" . $request->search . "%")
                        ->orWhere('html_content', 'like', "%" . $request->search . "%")
                        ->orWhere('host_name', 'like', "%" . $request->search . "%")
                        ->orWhere('additional_details', 'like', "%" . $request->search . "%");
                });
            }

            $liveSessions->orderBy($order_by, $sort_by);

            return $liveSessions->paginate(200);
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
            if ($request->live_id) {
                return $this->update($request, $request->live_id);
            }

            $liveSession = new LiveSession();
            $liveSession->interest_id = $request->interest_id;
            $liveSession->title = $request->title;
            $liveSession->notification_message = $request->notification_message;
            $liveSession->description = $request->description;
            $liveSession->html_content = $request->html_content;
            $liveSession->bcoin_reward = $request->bcoin_reward;

            $liveSession->registration_ended_at = $request->registration_ended_at;
            $liveSession->started_at = $request->started_at;
            $liveSession->ended_at = $request->ended_at;
            $liveSession->scheduled_at =  $request->scheduled_at ?: null;

            $liveSession->is_featured = $request->is_featured;
            $liveSession->is_editor_pick = $request->is_editor_pick;
            $liveSession->slots = $request->slots;
            $liveSession->host_name = $request->host_name;
            $liveSession->host_email = $request->host_email;
            $liveSession->additional_details = $request->additional_details;
            $liveSession->virtual_room_link = $request->virtual_room_link;
            $liveSession->recording_link = $request->recording_link;
            $liveSession->user_id = auth()->user()->user_id;

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

            return ["data" => $liveSession];
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
                $liveSessionDetail->participants_count =  $liveSessionDetail->participants->count();
                unset($liveSessionDetail->participants);

                return ["data" => $liveSessionDetail];
            }
            return ["error" => ["message" => "No live session found."]];
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
        if (auth()->user()->privilege == "moderator") {
            $fieldsToUpdate = $request->only([
                "interest_id",
                "user_id",
                "title",
                "description",
                'notification_message',
                "html_content",
                "bcoin_reward",
                "notification_message",
                "slots",
                "is_featured",
                "is_editor_pick",
                "host_name",
                "host_email",
                "additional_details",
                "virtual_room_link",
                'registration_ended_at',
                'started_at',
                'ended_at',
                'scheduled_at',
                "recording_link",
            ]);

            $liveSession = LiveSession::where('live_id', $id)->first();

            if (!is_null($liveSession)) {
                $liveSession->update($fieldsToUpdate);

                if (!is_null($liveSession->published_at)) {
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
        };
        abort(400);
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
            $liveSession = LiveSession::find($id);
            if ($liveSession) {
                if ($liveSession->published_at) {
                    // delete all post in activity feed
                    ActivityFeed::where('feed_type', 'feed')->where('live_id', $liveSession->live_id)->delete();

                    // delete all activity feed related to live_id
                    $liveFeed = ActivityFeed::where('feed_type', 'live session')->where('live_id', $liveSession->live_id);
                    $liveFeed->delete();

                    // delete all activity feed related to meetup_id
                    $deleteNotif = Notifications::where('live_id', $liveSession->live_id);
                    $deleteNotif->delete();
                }
                $liveSession->delete();
                return response()->json(["data" => ["live_session" => $liveSession]]);
            }

            return response()->json(["data" =>
            [
                "live_session" => "No live session deleted."
            ]]);
        };
        abort(400);
    }

    public function publishLiveSession(Request $request, $id)
    {
        if (auth()->user()->privilege == "moderator") {
            $liveSession = LiveSession::find($id);
            if (!is_null($liveSession)) {
                if ($request->action == 'publish') {
                    $liveSession->update([
                        "published_at" => now(),
                        "scheduled_at" => null
                    ]);

                    $existingActivityFeed = ActivityFeed::where('live_id', $liveSession->live_id)
                        ->where('feed_type', 'live session')
                        ->where('title', $liveSession->title)
                        ->where('content', $liveSession->description)
                        ->first();

                    if (is_null($existingActivityFeed)) {

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
                } else {
                    $liveSession->update(["published_at" => null]);

                    // delete all post in activity feed
                    ActivityFeed::where('feed_type', 'feed')->where('live_id', $liveSession->live_id)->delete();

                    // delete all activity feed related to live_id
                    $liveFeed = ActivityFeed::where('feed_type', 'live session')->where('live_id', $liveSession->live_id);
                    $liveFeed->delete();

                    // delete all activity feed related to live_id
                    $deleteNotif = Notifications::where('live_id', $liveSession->live_id);
                    $deleteNotif->delete();

                    return ["data" => $liveSession];
                }
            }
        }
        abort(404);
    }


    public function publishLiveSessionUnAuth(Request $request, $id)
    {
        $liveSession = LiveSession::find($id);
        if (!is_null($liveSession)) {
            if ($request->action == 'publish') {
                $liveSession->update([
                    "published_at" => now(),
                    "scheduled_at" => null
                ]);

                // create record in news feed
                $newActivityFeed = new Request([
                    "live_id" => $liveSession->live_id,
                    "feed_type" => "live session",
                    "title" => $liveSession->title,
                    "content" => $liveSession->description,
                    "published_at" => 1,
                    "user_id" => $liveSession->user_id,
                    "scheduled_post" => 1
                ]);

                $activityFeed = new ActivityFeedController();
                $activityFeed->store($newActivityFeed);

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
                return ["data" => $liveSession];
            }
        }
    }


    public function scheduleLiveSession()
    {
        $tempNow = now()->timezone('Asia/Hong_Kong')->format('Y-m-d H:i');
        $scheduledLiveSessions = LiveSession::whereNotNull('scheduled_at')
            ->where('scheduled_at', $tempNow)
            ->get();

        foreach ($scheduledLiveSessions as $liveSession) {
            $request = new Request(["action" => 'publish']);
            $this->publishLiveSessionUnAuth($request, $liveSession->live_id);
        }
    }
}
