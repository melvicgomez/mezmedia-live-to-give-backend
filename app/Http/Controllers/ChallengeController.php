<?php

namespace App\Http\Controllers;

use App\Helpers\CollectionHelper;
use App\Models\ActivityFeed;
use App\Models\BcoinLog;
use App\Models\Challenge;
use App\Models\ChallengeOpenLogsModel;
use App\Models\ChallengeParticipant;
use App\Models\ChallengeParticipantProgress;
use App\Models\ChallengeTeam;
use App\Models\FavoriteUsers;
use App\Models\FCMNotification;
use App\Models\Notifications;
use App\Models\User;
use App\Models\UserClubInterest;
use App\PusherEvents\BcoinAwarded;
use App\PusherEvents\ChallengeReminder;
use App\PusherEvents\NewChallengePosted;
use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ChallengeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $tz = $request->tz ?: "+08:00";
        $per_page = !is_null($request->per_page) ? (int) $request->per_page : 80;

        $challenges = Challenge::with(['clubInterest' => function ($query) {
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
                'participants as is_joined_challenge' => function ($query) {
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
            $challenges->whereIn('interest_id', $clubs);
        }

        // filter challenges where user is part of
        if ($request->is_part_of == 1) {
            $challenges->whereHas('participants', function ($query) {
                $query->where('user_id', auth()->user()->user_id);
            });
        }

        if (!is_null($request->search)) {
            $keywords = explode(" ", $request->search);
            $challenges->where(function ($q) use ($keywords, $request) {
                $q->where('title', 'like', "%" . $request->search . "%");
                foreach ($keywords as $keyword) {
                    $q->orWhere('title', 'like', "%" . $keyword . "%")
                        ->orWhere('description', 'like', "%" . $keyword . "%");
                }
            });
        }

        // clone challenges and get all ended challenges
        $endedChallenges = clone $challenges;
        $endedChallenges
            ->orderBy('ended_at', 'desc')
            ->where('ended_at', '<=', now($tz)->format('Y-m-d H:i'));

        // clone challenges and get all active challenges
        $activeChallenges = clone $challenges;
        $activeChallenges
            ->orderBy('ended_at', 'asc')
            ->where('ended_at', '>=', now($tz)->format('Y-m-d H:i'));
        $responseObject = collect(
            ['ongoing_activity' => $activeChallenges->get()]
        );

        return $responseObject->merge($endedChallenges->simplePaginate($per_page));
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

        $challenge->published_at = now();

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

        if (!is_null($challenge->published_at)) {
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

        return array_merge(["data" => $challenge]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Challenge  $challenge
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
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
                }])
                    ->where('user_id', auth()->user()->user_id);
            },
            "participants.stravaProgress",
            "participants.googleFitProgress",
            "participants.healthkitProgress",
            "participants.fitbitProgress"
        ])->find($id);

        if ($challenge) {
            $challenge->first();

            $challenge->participants_count =  $challenge->countParticipants->count();
            $challenge->is_joined_challenge =  $challenge->participants->where('user_id', auth()->user()->user_id)->count();

            $target_unit = $challenge->target_unit;

            if (count($challenge->participants) > 0) {
                $userParticipant = $challenge->participants[0];
                $progress = 0;

                if ($target_unit == 'distance') {
                    $progress = $userParticipant->stravaProgress->sum('distance') +
                        $userParticipant->googleFitProgress->sum('distance') +
                        $userParticipant->healthkitProgress->sum('distance') +
                        $userParticipant->fitbitProgress->sum('distance');
                }
                if ($target_unit == 'calories') {
                    $progress = $userParticipant->stravaProgress->sum('calories_burnt') +
                        $userParticipant->googleFitProgress->sum('calories_burnt') +
                        $userParticipant->healthkitProgress->sum('calories_burnt') +
                        $userParticipant->fitbitProgress->sum('calories_burnt');
                }
                if ($target_unit == 'duration') {
                    $progress = $userParticipant->stravaProgress->sum('duration_activity') +
                        $userParticipant->googleFitProgress->sum('duration_activity') +
                        $userParticipant->healthkitProgress->sum('duration_activity') +
                        $userParticipant->fitbitProgress->sum('duration_activity');
                }

                $userParticipant->progress = $progress;

                unset($userParticipant->stravaProgress);
                unset($userParticipant->googleFitProgress);
                unset($userParticipant->healthkitProgress);
                unset($userParticipant->fitbitProgress);

                $tempParticipants = $challenge->participants[0]->team;

                if (isset($tempParticipants)) {
                    $challenge->participants[0]->team->progress = $tempParticipants->participants->map(function ($member)
                    use ($target_unit) {
                        $progress = 0;
                        if ($target_unit == 'distance') {
                            $progress = $member->stravaProgress->sum('distance') +
                                $member->googleFitProgress->sum('distance') +
                                $member->healthkitProgress->sum('distance') +
                                $member->fitbitProgress->sum('distance');
                        }
                        if ($target_unit == 'calories') {
                            $progress = $member->stravaProgress->sum('calories_burnt') +
                                $member->googleFitProgress->sum('calories_burnt') +
                                $member->healthkitProgress->sum('calories_burnt') +
                                $member->fitbitProgress->sum('calories_burnt');
                        }
                        if ($target_unit == 'duration') {
                            $progress = $member->stravaProgress->sum('duration_activity') +
                                $member->googleFitProgress->sum('duration_activity') +
                                $member->healthkitProgress->sum('duration_activity') +
                                $member->fitbitProgress->sum('duration_activity');
                        }
                        $member->progress = $progress;

                        unset($member->stravaProgress);
                        unset($member->googleFitProgress);
                        unset($member->healthkitProgress);
                        unset($member->fitbitProgress);

                        return $member;
                    })->sum('progress');
                    unset($challenge->participants[0]->team->participants);
                }
            }

            unset($challenge->countParticipants);

            // check if user opened the challenge
            $checkIsOpen = ChallengeOpenLogsModel::where('user_id', auth()->user()->user_id)
                ->where('challenge_id', $challenge->challenge_id)
                ->first();

            if (is_null($checkIsOpen)) {
                // save that user_id open this challenge
                $userOpen = new ChallengeOpenLogsModel();
                $userOpen->user_id = auth()->user()->user_id;
                $userOpen->challenge_id = $challenge->challenge_id;
                $userOpen->save();
            }

            return ["data" => $challenge];
        }
        return ["error" => ["message" => "No challenge found."]];
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Challenge  $challenge
     * @return \Illuminate\Http\Response
     */
    public function edit(Challenge $challenge)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Challenge  $challenge
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
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
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Challenge  $challenge
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $challenge = Challenge::find($id);
        if ($challenge) {
            // delete all post in activity feed
            ActivityFeed::where('feed_type', 'feed')->where('challenge_id', $challenge->challenge_id)->delete();

            // delete all activity feed related to challenge_id
            $challengeFeed = ActivityFeed::where('feed_type', 'challenge')->where('challenge_id', $challenge->challenge_id);
            $challengeFeed->delete();

            // delete all activity feed related to challenge_id
            $deleteNotif = Notifications::where('challenge_id', $challenge->challenge_id);
            $deleteNotif->delete();

            $challenge->delete();
            return response()->json(["data" => ["challenge" => $challenge]]);
        }

        return response()->json(["data" =>
        [
            "challenge" => "No challenge deleted."
        ]]);
    }

    public function challengeParticipants(Request $request, $id)
    {
        $per_page = !is_null($request->per_page) ? (int) $request->per_page : 50;

        $user_id = auth()->user()->user_id;
        $challengeParticipants = ChallengeParticipant::with([
            'user' => function ($query) use ($user_id) {
                $query
                    ->select(
                        'user_id',
                        'first_name',
                        'last_name',
                        'photo_url',
                        'country_code',
                        'privilege',
                        'is_verified',
                        'created_at',
                        'updated_at',
                    )
                    ->withCount(['favoriteUsers as is_favorite' => function ($query) use ($user_id) {
                        $query->where('user_id', $user_id);
                    }])
                    ->withSum(['bcoinTotal' => function ($query) {
                        $query->where('amount', '>', 0);
                    }], 'amount');
            },
            'team',
            'challenge:challenge_id,target_unit',
        ])
            // comment if need to filter moderator and is_verified
            ->whereHas('user', function ($query) {
                $query
                    // ->where('users.privilege', 'user')
                    ->where('users.is_verified', 1);
            })
            ->where('challenge_id', intVal($id))
            ->get();

        $challengeParticipants->map(function ($item) {
            $target_unit = $item->challenge->target_unit;
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

        // challenge details
        $challenge = Challenge::where('challenge_id', intVal($id))->first();

        $sortedParticipants = [];
        if ($challenge) {
            if ($challenge->is_trackable == 1)
                // individual trackable challenge sort by progress
                $sortedParticipants = collect($challengeParticipants)->sortBy([
                    ['progress', 'desc'],
                    ['user.first_name', 'asc'],
                    ['user.last_name', 'desc'],
                    ['updated_at', 'asc'],
                ]);
            else {
                // non-trackable challenge sort alphabetically
                $sortedParticipants = collect($challengeParticipants)->sortBy([
                    ['user.first_name', 'asc'],
                    ['user.last_name', 'desc'],
                    ['updated_at', 'asc'],
                ]);
            }
        }

        $usersWithRanking = $sortedParticipants->map(function ($user, $key) {
            $user->ranking = $key + 1;
            return $user;
        });


        // participants count
        $challengeParticipantsCount = count($usersWithRanking);

        $alreadyFavoriteIn  = $challengeParticipants
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
            $temp = $item['favorite_user'];
            $temp['ranking'] = null;
            $temp['is_ghost'] = 1;
            array_push($tempFave, [
                "participant_id" => null,
                "challenge_id" => null,
                "status" => null,
                "team_id" => null,
                "progress" => null,
                "ranking" => null,
                "team" => null,
                "is_ghost" => 1,
                "user_id" => $item['favorite_user_id'],
                "user" => $temp,
            ]);
        }

        $favoriteUsers =  collect(array_merge(
            $alreadyFavoriteIn
                ->sortBy([
                    ['ranking', 'asc'],
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

        if ($request->is_team == 1) {
            // Team Challenge
            $teamParticipants =  ChallengeTeam::with([
                'participants' => function ($query) use ($user_id) {
                    $query->with(['user' => function ($query) use ($user_id) {
                        $query->select(
                            'user_id',
                            'first_name',
                            'last_name',
                            'photo_url',
                            'country_code',
                            'privilege',
                            'is_verified',
                            'created_at',
                            'updated_at'
                        )
                            ->withCount(['favoriteUsers as is_favorite' => function ($query) use ($user_id) {
                                $query->where('user_id', $user_id);
                            }])

                            ->withSum(['bcoinTotal' => function ($query) {
                                $query->where('amount', '>', 0);
                            }], 'amount');
                    }])->whereHas('user', function ($query) {
                        $query
                            // ->where('privilege', 'user')
                            ->where('is_verified', 1);
                    });
                },
            ])
                ->withCount(['participants'])
                ->where('challenge_id', $id)
                ->get();


            $teamParticipants->map(function ($team, $key) use ($usersWithRanking) {
                $team->ranking = $key + 1;
                $team->team_progress = 0;
                $tempParticipants = $team
                    ->participants
                    ->map(function ($item) use ($team) {
                        $target_unit = $item->challenge->target_unit;
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
                        $team->team_progress = round($team->team_progress + $item->progress, 2);



                        return $item;
                    });

                unset($team->participants);
                $team->participants = collect($tempParticipants)
                    ->sortBy([
                        ['progress', 'desc'],
                        ['first_name', 'asc'],
                        ['last_name', 'asc'],
                    ])
                    // add ranking index after sorting
                    ->map(function ($user) use ($usersWithRanking) {
                        $user->ranking = $usersWithRanking->first(function ($u) use ($user) {
                            return $u->user_id == $user->user_id;
                        })->ranking;
                        return $user;
                    });
                return $team;
            });


            $teamParticipants = $teamParticipants->sortBy([
                ['team_progress', 'desc'],
                ['team_name', 'asc'],
            ])
                ->map(function ($user, $key) { // add ranking index after sorting
                    $user->ranking = $key + 1;
                    return $user;
                });
            $teamParticipantsCount = count($teamParticipants);

            // filter teams (combined team name and all participants first_name and last_name)
            // comment if not need to add search team
            if (!is_null($request->search)) {
                $teamParticipants = $teamParticipants
                    ->filter(function ($team) use ($request) {
                        $tempNames = "";
                        $tempNames = $team->team_name;
                        $team->participants->map(function ($user) use (&$tempNames) {
                            $tempNames = $tempNames . " " . $user->user->first_name . " " . $user->user->last_name;
                            return $user;
                        });
                        $found = false;
                        $keywords = explode(" ", $request->search);
                        $combinedNames = strtolower($tempNames);
                        foreach ($keywords as $keyword) {
                            // check each word from keywords if it exists in the $combinedNames
                            if (strpos($combinedNames, strtolower($keyword)) !== false) {
                                // break the loop if keyword found a match
                                $found = true;
                                break;
                            }
                        }
                        return $found;
                    });

                // filter team participants by their first_name and last_name
                $teamParticipants = $teamParticipants->map(function ($team, $key) use (&$teamParticipants, $request) {
                    $teamParticipants[$key]->participants = $team->participants->filter(function ($user) use ($request) {
                        $userFound = false;
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
                                $userFound = true;
                                break;
                            }
                        }
                        return $userFound;
                    })->values();
                    return $team;
                });
            }

            $teamCounts = collect([
                "favorite_users" => $favoriteUsers->values(),
                "team_count" => $teamParticipantsCount,
            ]);
            return $teamCounts->merge(
                CollectionHelper::paginate(
                    $teamParticipants,
                    $per_page
                )
            );
        }

        // apply search
        if (!is_null($request->search)) {
            $usersWithRanking = $usersWithRanking->filter(function ($user) use ($request) {
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
        // create favorite collection to add in the response
        $favorites = collect([
            'participants_count' =>  $challengeParticipantsCount,
            'favorite_users' => $favoriteUsers->values(),
        ]);

        return $favorites->merge(
            CollectionHelper::paginate(
                $usersWithRanking,
                $per_page
            )
        );
    }

    public function joinOrLeaveChallenge($id, $status)
    {
        $isParticipant = ChallengeParticipant::where('user_id', auth()->user()->user_id)
            ->where('challenge_id', intVal($id))->withTrashed();

        if ($status == "join") {
            if (!$isParticipant->first()) {
                $challengeParticipant = new ChallengeParticipant();
                $challengeParticipant->user_id = auth()->user()->user_id;
                $challengeParticipant->challenge_id = $id;
                $challengeParticipant->status = "PARTICIPATED";
                $challengeParticipant->participated_at = now();
                $challengeParticipant->save();
                // $challengeParticipant->participant_id;
                // delete all record in participant_id
                return $challengeParticipant;
            } else {
                return ["data" => [
                    "rows_changed" =>  $isParticipant->update([
                        "status" => "PARTICIPATED",
                        'participated_at' => now(),
                        "deleted_at" => null
                    ])
                ]];
            }
        } else if ($status == "leave") {
            $userParticipant = ChallengeParticipant::where('user_id', auth()->user()->user_id)
                ->where('challenge_id', intVal($id))->first();

            if ($userParticipant) {
                ChallengeParticipantProgress::where(
                    'participant_id',
                    $userParticipant->participant_id
                )->delete();
            }

            ActivityFeed::where('user_id', auth()->user()->user_id)
                ->where('challenge_id', intVal($id))
                ->where('is_challenge_entry', '1')
                ->delete();

            return ["data" => [
                "rows_changed" =>  $isParticipant->update([
                    "status" => "QUIT",
                    'participated_at' => null,
                    "deleted_at" => now()
                ])
            ]];
        }

        return ["error" => $isParticipant ? "User participated in this challenge." : "Join status is undefined."];
    }

    public function challengeTeams($id)
    {
        $challengeId = intVal($id);
        $challengeTeams = ChallengeTeam::where("challenge_id", $challengeId);
        return  $challengeTeams->simplePaginate(10);
    }

    public function listOfChallengeEntry(Request $request)
    {
        $per_page = !is_null($request->per_page) ? (int) $request->per_page : 10;

        $challenge_entry = ActivityFeed::with(['challenge'])
            ->where('is_challenge_entry', 1)
            ->where('challenge_id', $request->challenge_id);

        return $challenge_entry->simplePaginate($per_page);
    }

    public function endingReminder3Days()
    {
        $challenges = Challenge::with(['participants'])
            ->whereDate(
                'ended_at',
                '=',
                now()
                    ->timezone('Asia/Hong_Kong')
                    ->addDays(3)
                    ->toDateString()
            )->get();

        foreach ($challenges as $challenge) {
            foreach ($challenge->participants as $participant) {
                $ended_at = Carbon::parse($challenge->ended_at);
                // NOTIFICATION RECORD
                $notif = new Notifications();
                $notif->user_id = $participant->user_id;
                $notif->title = 'Challenge is ending soon';
                $notif->message = $challenge->title
                    . ' ends on '
                    . $ended_at->format('d M')
                    . ' at '
                    . $ended_at->format('H:i') . '.';
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

                // EVENT NOTIFICATION
                event(new ChallengeReminder(
                    $notif->notification_id,
                    $notif->title,
                    $notif->message,
                    $notif->user_id,
                    $notif->challenge_id,
                    $challenge->is_trackable,
                    $challenge->is_team_challenge
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
    }

    public function endedReminderToday()
    {
        $challenges = Challenge::with(['participants'])
            ->whereDate(
                'ended_at',
                '=',
                now()
                    ->timezone('Asia/Hong_Kong')
                    ->toDateString()
            )
            ->where(
                'ended_at',
                '>=',
                now()
                    ->timezone('Asia/Hong_Kong')
            )
            ->get();

        foreach ($challenges as $challenge) {
            foreach ($challenge->participants as $participant) {
                // NOTIFICATION RECORD
                $notif = new Notifications();
                $notif->user_id = $participant->user_id;
                $notif->title = 'Challenge is ending soon';
                $notif->message = $challenge->title
                    . ' is ending today.';
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

                // EVENT NOTIFICATION
                event(new ChallengeReminder(
                    $notif->notification_id,
                    $notif->title,
                    $notif->message,
                    $notif->user_id,
                    $notif->challenge_id,
                    $challenge->is_trackable,
                    $challenge->is_team_challenge
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
    }

    public function eventEnded()
    {
        $current_time = now()->timezone('Asia/Hong_Kong')->format('Y-m-d H:i');
        // $current_time = Carbon::parse('2021-04-16 16:23')->format('Y-m-d H:i');
        $current_time_2days = now()->timezone('Asia/Hong_Kong')->subDays(2)->format('Y-m-d H:i');
        $challenges = Challenge::with(['participants', 'teams' => function ($q) {
            $q->with(['participants'])->withCount(['participants']);
        }])
            ->whereBetween('ended_at', [$current_time_2days, $current_time])
            ->get();

        foreach ($challenges as $challenge) {
            if ($challenge->is_trackable == 0) {
                // award bcoin to users who submitted an entry
                foreach ($challenge->participants as $participant) {
                    if ($participant->status == "DONE") {
                        $receivedBcoin = BcoinLog::where('user_id', $participant->user_id)
                            ->where('challenge_id', $challenge->challenge_id)
                            ->first();
                        if (is_null($receivedBcoin)) {
                            // status DONE it means user submitted an entry
                            // bcoin award
                            $bcoinAward = new BcoinLog();
                            $bcoinAward->user_id = $participant->user_id;
                            $bcoinAward->amount = $challenge->bcoin_reward;
                            $bcoinAward->description = $challenge->bcoin_reward == 0 ?
                                "Congratulations! You have successfully completed the " . $challenge->title . "." :
                                "Congratulations! You have been awarded " .
                                $challenge->bcoin_reward .
                                " B Coins for completing the " .
                                $challenge->title . ".";
                            $bcoinAward->challenge_id = $challenge->challenge_id;
                            $bcoinAward->save();

                            // save notification message in db
                            $notif = new Notifications();
                            $notif->user_id = $bcoinAward->user_id;
                            $notif->title = $challenge->bcoin_reward == 0 ? "Activity Completed" : 'B Coins Awarded';
                            $notif->message = $bcoinAward->description;
                            $notif->challenge_id = $bcoinAward->challenge_id;
                            $notif->deep_link = 'bcoin-history';
                            $notif->save();

                            // notify user_id
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
            } else {
                // trackable challenge
                if ($challenge->is_team_challenge == 0) {
                    // individual progress checking
                    foreach ($challenge->participants as $participant) {
                        // check progress if met the challenge target
                        // award bcoin
                        // notify user
                        $user_progress = 0;
                        $user_participant =  ChallengeParticipant::where('user_id', $participant->user_id)
                            ->where('challenge_id', $challenge->challenge_id)->first();
                        if ($user_participant) {
                            $strava_progress = ChallengeParticipantProgress::where('participant_id', $user_participant->participant_id)
                                ->where('source', 'strava')->get();

                            $healthkit_progress = ChallengeParticipantProgress::where('participant_id', $user_participant->participant_id)
                                ->where('source', 'healthkit')->get();

                            $google_fit_progress = ChallengeParticipantProgress::where('participant_id', $user_participant->participant_id)
                                ->where('source', 'google_fit')->get();

                            $fitbit_progress = ChallengeParticipantProgress::where('participant_id', $user_participant->participant_id)
                                ->where('source', 'fitbit')->get();

                            if ($challenge->target_unit == 'distance') {
                                $user_progress = $strava_progress->sum('distance') +
                                    $healthkit_progress->sum('distance') +
                                    $google_fit_progress->sum('distance') +
                                    $fitbit_progress->sum('distance');
                            }
                            if ($challenge->target_unit == 'calories') {
                                $user_progress = $strava_progress->sum('calories_burnt') +
                                    $healthkit_progress->sum('calories_burnt') +
                                    $google_fit_progress->sum('calories_burnt') +
                                    $fitbit_progress->sum('calories_burnt');
                            }
                            if ($challenge->target_unit == 'duration') {
                                $user_progress = $strava_progress->sum('duration_activity') +
                                    $healthkit_progress->sum('duration_activity') +
                                    $google_fit_progress->sum('duration_activity') +
                                    $fitbit_progress->sum('duration_activity');
                            }
                            if ($challenge->target_goal <= (float) $user_progress) {
                                if ($user_participant->status == "PARTICIPATED") {
                                    // bcoin award
                                    $bcoinAward = new BcoinLog();
                                    $bcoinAward->user_id = $user_participant->user_id;
                                    $bcoinAward->amount = $challenge->bcoin_reward;
                                    $bcoinAward->description =  $challenge->bcoin_reward == 0 ?
                                        "Congratulations! You have successfully completed the " . $challenge->title . "." :
                                        "Congratulations! You have been awarded " .
                                        $challenge->bcoin_reward .
                                        " B Coins for completing the " .
                                        $challenge->title . ".";
                                    $bcoinAward->challenge_id = $challenge->challenge_id;
                                    $bcoinAward->save();

                                    // save notification message in db
                                    $notif = new Notifications();
                                    $notif->user_id = $bcoinAward->user_id;
                                    $notif->title = $challenge->bcoin_reward == 0 ? "Activity Completed" : 'B Coins Awarded';
                                    $notif->message = $bcoinAward->description;
                                    $notif->challenge_id = $bcoinAward->challenge_id;
                                    $notif->deep_link = 'bcoin-history';
                                    $notif->transaction_id = $bcoinAward->transaction_id;
                                    $notif->save();

                                    // notify user_id
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
                                    $user_participant->update(["status" => "DONE"]);
                                }
                            }
                        }
                    }
                } else {
                    // team progress checking
                    foreach ($challenge->teams as $team) {
                        if ($team->participants_count > 1) {
                            $team_progress = 0;
                            foreach ($team->participants as $participant) {
                                $user_participant =  ChallengeParticipant::where('user_id', $participant->user_id)
                                    ->where('challenge_id', $challenge->challenge_id)->first();

                                if ($user_participant) {
                                    $strava_progress = ChallengeParticipantProgress::where('participant_id', $user_participant->participant_id)
                                        ->where('source', 'strava')->get();

                                    $healthkit_progress = ChallengeParticipantProgress::where('participant_id', $user_participant->participant_id)
                                        ->where('source', 'healthkit')->get();

                                    $google_fit_progress = ChallengeParticipantProgress::where('participant_id', $user_participant->participant_id)
                                        ->where('source', 'google_fit')->get();

                                    $fitbit_progress = ChallengeParticipantProgress::where('participant_id', $user_participant->participant_id)
                                        ->where('source', 'fitbit')->get();

                                    if ($challenge->target_unit == 'distance') {
                                        $team_progress += $strava_progress->sum('distance') +
                                            $healthkit_progress->sum('distance') +
                                            $google_fit_progress->sum('distance') +
                                            $fitbit_progress->sum('distance');
                                    }
                                    if ($challenge->target_unit == 'calories') {
                                        $team_progress += $strava_progress->sum('calories_burnt') +
                                            $healthkit_progress->sum('calories_burnt') +
                                            $google_fit_progress->sum('calories_burnt') +
                                            $fitbit_progress->sum('calories_burnt');
                                    }
                                    if ($challenge->target_unit == 'duration') {
                                        $team_progress += $strava_progress->sum('duration_activity') +
                                            $healthkit_progress->sum('duration_activity') +
                                            $google_fit_progress->sum('duration_activity') +
                                            $fitbit_progress->sum('duration_activity');
                                    }
                                }
                            }

                            if ($challenge->target_goal <= (float) $team_progress) {
                                foreach ($team->participants as $participant) {
                                    if ($participant->status == "PARTICIPATED") {
                                        // bcoin award
                                        $bcoinAward = new BcoinLog();
                                        $bcoinAward->user_id = $participant->user_id;
                                        $bcoinAward->amount = $challenge->bcoin_reward;
                                        $bcoinAward->description = $challenge->bcoin_reward == 0 ?
                                            "Congratulations! You have successfully completed the " . $challenge->title . "." :
                                            "Congratulations! You have been awarded " .
                                            $challenge->bcoin_reward .
                                            " B Coins for completing the " .
                                            $challenge->title . ".";
                                        $bcoinAward->challenge_id = $challenge->challenge_id;
                                        $bcoinAward->save();

                                        // save notification message in db
                                        $notif = new Notifications();
                                        $notif->user_id = $bcoinAward->user_id;
                                        $notif->title = $challenge->bcoin_reward == 0 ? "Activity Completed" : 'B Coins Awarded';
                                        $notif->message = $bcoinAward->description;
                                        $notif->challenge_id = $bcoinAward->challenge_id;
                                        $notif->deep_link = 'bcoin-history';
                                        $notif->transaction_id = $bcoinAward->transaction_id;
                                        $notif->save();

                                        // notify user_id
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
                                        $participant->update(['status' => 'DONE']);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function eventEndedSyncReminder()
    {
        $current_time = now()->timezone('Asia/Hong_Kong')->format('Y-m-d H:i');
        // $current_time = Carbon::parse('2021-04-16 16:23')->format('Y-m-d H:i');
        $challenges = Challenge::with(['participants', 'teams' => function ($q) {
            $q->with(['participants'])->withCount(['participants']);
        }])
            ->where('ended_at', $current_time)
            ->where('is_trackable', 1)
            ->get();

        foreach ($challenges as $challenge) {
            foreach ($challenge->participants as $participant) {
                // NOTIFICATION RECORD
                $notif = new Notifications();
                $notif->user_id = $participant->user_id;
                $notif->title = 'Challenge has ended';
                $notif->message = "Do not forget to sync your fitness app to save your progress in " . $challenge->title;
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

                // EVENT NOTIFICATION
                event(new ChallengeReminder(
                    $notif->notification_id,
                    $notif->title,
                    $notif->message,
                    $notif->user_id,
                    $notif->challenge_id,
                    $challenge->is_trackable,
                    $challenge->is_team_challenge
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
