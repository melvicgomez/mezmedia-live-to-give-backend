<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\ActivityFeed;
use App\Models\Notifications;
use App\Models\ChallengeTeam;
use App\Models\ChallengeParticipant;
use App\Models\UserClubInterest;
use App\Models\ChallengeParticipantProgress;
use App\Models\FCMNotification;
use App\PusherEvents\NewChallengePosted;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class CMSAllChallengesController extends Controller
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
            $order_by = $request->order_by ?: 'challenge_id'; // column name
            $sort_by = $request->sort_by ?: 'desc'; // asc | desc

            $challenges = Challenge::withCount([
                'participants',
            ])->whereNull('deleted_at');

            if (!is_null($request->search)) {
                $challenges->where(function ($q) use ($request) {
                    $q->where('title', 'like', "%" . $request->search . "%")
                        ->orWhere('description', 'like', "%" . $request->search . "%")
                        ->orWhere('html_content', 'like', "%" . $request->search . "%")
                        ->orWhere('type', 'like', "%" . $request->search . "%")
                        ->orWhere('target_unit', 'like', "%" . $request->search . "%");
                });
            }

            if ($showByStatus == 'team') {
                $challenges
                    ->where('is_team_challenge', 1);
            }

            if ($showByStatus == 'individual') {
                $challenges
                    ->where('is_team_challenge', 0)->where('is_trackable', 1);
            }

            if ($showByStatus == 'untrack') {
                $challenges->where('is_trackable', 0);
            }

            $challenges->orderBy($order_by, $sort_by);

            return $challenges->paginate(200);
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
        if ($request->challenge_id) {
            // call update function
            return $this->update($request, $request->challenge_id);
        }

        $challenge = new Challenge();
        $challenge->interest_id = $request->interest_id;
        $challenge->title = $request->title;
        $challenge->description = $request->description;
        $challenge->html_content = $request->html_content  ?: null;
        $challenge->target_goal = $request->target_goal;
        $challenge->type = $request->type;
        $challenge->target_unit = $request->target_unit;
        $challenge->bcoin_reward = $request->bcoin_reward;
        $challenge->is_team_challenge = $request->is_team_challenge;
        $challenge->is_trackable = $request->is_trackable;
        $challenge->is_featured = $request->is_featured;
        $challenge->is_editor_pick = $request->is_editor_pick;
        $challenge->duration = $request->duration;
        $challenge->notification_message = $request->notification_message;

        $challenge->registration_ended_at = $request->registration_ended_at;
        $challenge->started_at = $request->started_at;
        $challenge->ended_at = $request->ended_at;

        $challenge->user_id =  auth()->user()->user_id;

        $challenge->save();

        if (!is_null($challenge->challenge_id))
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
                        $request->image_cover->storeAs('/public/images/challenge/' . $challenge->challenge_id, $newFileName);
                        $challenge->update(["image_cover" => $newFileName]);
                    } else {
                        return response(["error" => ["image" => $validator->errors()->get('image_cover')]], 400);
                    }
                }
            }

        return ["data" => $challenge];
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
            $challenge = Challenge::with([
                'clubInterest.club',
                'clubInterest' => function ($query) {
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
                },
                "participants" => function ($q) {
                    $q->with(['team' => function ($q) {
                        $q->withCount(['participants']);
                    }])->where('user_id', auth()->user()->user_id);
                },
            ])->find($id);

            if ($challenge) {
                $challenge->first();

                $challenge->participants_count =  $challenge->countParticipants->count();
                $challenge->is_joined_challenge =  $challenge->participants->where('user_id', auth()->user()->user_id)->count();

                $target_unit = $challenge->target_unit;

                unset($challenge->countParticipants);

                if ($challenge->is_trackable == 0) {
                    $challenge->entries_count =  $challenge->entries->count();
                    $challenge->unique_entries_count =  $challenge->entries->groupBy('user_id')->count();
                    unset($challenge->entries);
                } else {
                    $challengeParticipants = ChallengeParticipant::whereHas('user', function ($query) {
                        $query
                            ->where('users.is_verified', 1);
                    })
                        ->where('challenge_id', intVal($id))
                        ->get();

                    $challengeParticipants->map(function ($item) use ($challenge) {
                        $target_unit = $challenge->target_unit;
                        $item->progress = 0;
                        if ($target_unit == 'distance') {
                            $item->progress = $item->stravaProgress->sum('distance') +
                                $item->googleFitProgress->sum('distance') +
                                $item->healthkitProgress->sum('distance') +
                                $item->fitbitProgress->sum('distance');
                        }
                        if ($target_unit == 'calories') {
                            $item->progress = $item->stravaProgress->sum('calories_burnt') +
                                $item->googleFitProgress->sum('calories_burnt') +
                                $item->healthkitProgress->sum('calories_burnt') +
                                $item->fitbitProgress->sum('calories_burnt');
                        }
                        if ($target_unit == 'duration') {
                            $item->progress = $item->stravaProgress->sum('duration_activity') +
                                $item->googleFitProgress->sum('duration_activity') +
                                $item->healthkitProgress->sum('duration_activity') +
                                $item->fitbitProgress->sum('duration_activity');
                        }

                        unset($item->stravaProgress);
                        unset($item->googleFitProgress);
                        unset($item->healthkitProgress);
                        unset($item->fitbitProgress);
                        unset($item->challenge);
                        return $item;
                    });
                    $challenge->user_with_progress_count =  $challengeParticipants->where('progress', '!=', 0)->count();
                }
                return ["data" => $challenge];
            }
            return ["error" => ["message" => "No challenge found."]];
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
                'title',
                'duration',
                'description',
                'notification_message',
                'html_content',
                'target_goal',
                'type',
                'is_team_challenge',
                'is_trackable',
                'is_editor_pick',
                'is_featured',
                'target_unit',
                'bcoin_reward',
                'registration_ended_at',
                'started_at',
                'ended_at',
            ]);

            $challenge = Challenge::where('challenge_id', $id)->first();

            if (!is_null($challenge)) {
                $challenge->update($fieldsToUpdate);

                if (!is_null($challenge->published_at)) {
                    $updateActivityFeed = ActivityFeed::where('challenge_id', $challenge->challenge_id)
                        ->where('feed_type', 'challenge')->first();
                    if (!is_null($updateActivityFeed)) {
                        $updateActivityFeed->update(
                            [
                                "title" => $challenge->title,
                                "content" => $challenge->description
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
                            $request->image_cover->storeAs('/public/images/challenge/' .  $id, $newFileName);
                            $challenge->update(["image_cover" => $newFileName]);
                        } else {
                            return response(["error" => ["image" => $validator->errors()->get('image_cover')]], 400);
                        }
                    }
                }
            }
            return ["data" => $challenge];
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
            $challenge = Challenge::find($id);
            if ($challenge) {
                // delete all post in activity feed
                if ($challenge->published_at) {
                    ActivityFeed::where('feed_type', 'feed')->where('challenge_id', $challenge->challenge_id)->delete();

                    // delete all activity feed related to challenge_id
                    $challengeFeed = ActivityFeed::where('feed_type', 'challenge')->where('challenge_id', $challenge->challenge_id);
                    $challengeFeed->delete();

                    // delete all activity feed related to challenge_id
                    $deleteNotif = Notifications::where('challenge_id', $challenge->challenge_id);
                    $deleteNotif->delete();
                }

                if ($challenge->is_team_challenge == 1) {
                    $teams = ChallengeTeam::where('challenge_id', $challenge->challenge_id)->get();

                    if (count($teams) >= 1) {
                        foreach ($teams as $team) {
                            $teamMembers = ChallengeParticipant::where('team_id', $team->team_id)->get();
                            foreach ($teamMembers as $member) {
                                $member->update(["team_id" =>  0]);
                            }
                        }

                        ChallengeTeam::where('challenge_id', $challenge->challenge_id)->delete();
                    }
                }
                $challenge->delete();
                return response()->json(["data" => ["challenge" => $challenge]]);
            }

            return response()->json(["data" =>
            [
                "challenge" => "No challenge deleted."
            ]]);
        };
        abort(400);
    }

    public function publishChallenge(Request $request, $id)
    {
        if (auth()->user()->privilege == "moderator") {
            $challenge = Challenge::find($id);

            if (!is_null($challenge)) {
                if ($request->action == 'publish') {
                    $challenge->update(["published_at" => now()]);

                    $existingActivityFeed = ActivityFeed::where('challenge_id', $challenge->challenge_id)
                        ->where('feed_type', 'challenge')
                        ->where('title', $challenge->title)
                        ->where('content', $challenge->description)
                        ->first();

                    if (is_null($existingActivityFeed)) {
                        // create record in news feed
                        $newActivityFeed = new Request([
                            "challenge_id" => $challenge->challenge_id,
                            "feed_type" => "challenge",
                            "title" => $challenge->title,
                            "content" => $challenge->description,
                            "published_at" => 1,
                            "user_id" => $challenge->user_id
                        ]);

                        $activityFeed = new ActivityFeedController();
                        $activityFeed->store($newActivityFeed);

                        $users = UserClubInterest::where("interest_id", $challenge->interest_id)->get();
                        foreach ($users as $user) {
                            $notif = new Notifications();
                            $notif->title = $challenge->title;
                            $notif->message = $challenge->notification_message;
                            $notif->user_id = $user->user_id;
                            $notif->challenge_id = $challenge->challenge_id;
                            if ($challenge->is_team_challenge == 1) {
                                $notif->deep_link = "team-challenge/" . $challenge->challenge_id;
                            } else {
                                if ($challenge->is_trackable) {
                                    $notif->deep_link = "individual-challenge/" . $challenge->challenge_id;
                                } else {
                                    $notif->deep_link = "nontrack-challenge/" . $challenge->challenge_id;
                                }
                            }

                            $notif->save();
                            event(new NewChallengePosted(
                                $notif->notification_id,
                                $notif->title,
                                $notif->message,
                                $notif->user_id,
                                $notif->challenge_id,
                                $challenge->is_trackable,
                                $challenge->is_team_challenge,
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
                    }

                    return ["data" => $challenge];
                } else {
                    $challenge->update(["published_at" => null]);

                    // delete all post in activity feed
                    ActivityFeed::where('feed_type', 'feed')->where('challenge_id', $challenge->challenge_id)->delete();

                    // delete all activity feed related to challenge_id
                    $challengeFeed = ActivityFeed::where('feed_type', 'challenge')->where('challenge_id', $challenge->challenge_id);
                    $challengeFeed->delete();

                    // delete all activity feed related to challenge_id
                    $deleteNotif = Notifications::where('challenge_id', $challenge->challenge_id);
                    $deleteNotif->delete();

                    return ["data" => $challenge];
                }
            }
        }
        abort(404);
    }

    public function quitChallenge(Request $request)
    {
        if (auth()->user()->privilege == "moderator") {
            $isParticipant = ChallengeParticipant::where('user_id', $request->user_id)
                ->where('challenge_id', intVal($request->challenge_id))->withTrashed();

            $userParticipant = ChallengeParticipant::where('user_id', $request->user_id)
                ->where('challenge_id', intVal($request->challenge_id))->first();

            if ($userParticipant) {
                ChallengeParticipantProgress::where(
                    'participant_id',
                    $userParticipant->participant_id
                )->delete();
            }

            if ($userParticipant->team_id != 0) {
                $team = ChallengeTeam::find($userParticipant->team_id);

                if ($team) {
                    if ($team->user_id == $request->user_id) {
                        $teamMembers = ChallengeParticipant::where('team_id', $team->team_id)->get();
                        foreach ($teamMembers as $member) {
                            $member->update(["team_id" =>  0]);
                        }
                        $team->delete();
                    }
                }
            }

            ActivityFeed::where('user_id', $request->user_id)
                ->where('challenge_id', intVal($request->challenge_id))
                ->where('is_challenge_entry', '1')
                ->delete();

            return ["data" => [
                "rows_changed" =>  $isParticipant->update([
                    "status" => "QUIT",
                    "team_id" => 0,
                    'participated_at' => null,
                    "deleted_at" => now()
                ])
            ]];
        }
        abort(400);
    }
}
