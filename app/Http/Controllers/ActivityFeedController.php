<?php

namespace App\Http\Controllers;

use App\Models\ActivityFeed;
use App\Models\ActivityFeedFlag;
use App\Models\ActivityFeedImage;
use App\Models\ActivityFeedLike;
use App\Models\Challenge;
use App\Models\ChallengeParticipant;
use App\Models\LiveSession;
use App\Models\Meetup;
use App\Models\Notifications;
use App\Models\User;
use App\Models\UserClubInterest;
use App\PusherEvents\NewActivityFeed;
use App\PusherEvents\NewFeedLike;
use App\PusherEvents\NewOfficialPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\FCMNotification;
use Illuminate\Support\Facades\Mail;

class ActivityFeedController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $per_page = !is_null($request->per_page) ? (int) $request->per_page : 80;
        $activityFeed = ActivityFeed::with([
            'images',
            'clubInterest',
            'charity.images',
            'challenge' => function ($query) {
                $query
                    ->withCount([
                        'participants',
                        'participants as is_joined' => function ($query) {
                            $query->where('user_id', auth()->user()->user_id);
                        },
                        'isOpen as is_open' => function ($query) {
                            $query->where('user_id', auth()->user()->user_id);
                        }
                    ])
                    ->with(['clubInterest' => function ($query) {
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
                    }]);
            },
            'liveSession' => function ($query) {
                $query
                    ->withCount([
                        'participants',
                        'participants as is_joined' => function ($query) {
                            $query->where('user_id', auth()->user()->user_id);
                        },
                        'isOpen as is_open' => function ($query) {
                            $query->where('user_id', auth()->user()->user_id);
                        }
                    ])
                    ->with(['clubInterest' => function ($query) {
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
                    }]);
            },
            'meetup' => function ($query) {
                $query
                    ->withCount([
                        'participants',
                        'participants as is_joined' => function ($query) {
                            $query->where('user_id', auth()->user()->user_id);
                        },
                        'isOpen as is_open' => function ($query) {
                            $query->where('user_id', auth()->user()->user_id);
                        }
                    ])
                    ->with(['clubInterest' => function ($query) {
                        $query->withCount(['members as is_club_member' => function ($query) {
                            $query->where('user_id', auth()->user()->user_id);
                        }]);

                        $query->withCount(['participatedChallenges as challenges_done_count' => function ($query) {
                            $query->where('challenge_participants.user_id', auth()->user()->user_id);
                        }]);

                        $query->withCount(['participatedMeetups as meetups_done_count' => function ($query) {
                            $query->where('meetup_participants.user_id', auth()->user()->user_id);
                        }]);

                        $query->withCount(['participatedLiveSessions as live_session_done_count' => function ($query) {
                            $query->where('live_session_participants.user_id', auth()->user()->user_id);
                        }]);
                    }]);
            },
            'user' => function ($query) {
                $query->withSum(['bcoinTotal' => function ($query) {
                    $query->where('amount', '>', 0);
                }], 'amount');
            },
        ])
            ->withCount([
                'challengeParticipants',
                'liveSessionParticipants',
                'meetupParticipants',
                'liveSessionParticipants as is_joined_live_session' => function ($query) {
                    $query->where('live_session_participants.user_id', auth()->user()->user_id);
                },
                'challengeParticipants as is_joined_challenge' => function ($query) {
                    $query->where('challenge_participants.user_id', auth()->user()->user_id);
                },
                'meetupParticipants as is_joined_meetup' => function ($query) {
                    $query->where('meetup_participants.user_id', auth()->user()->user_id);
                },
                'comments' => function ($query) {
                    $query->whereDoesntHave('flags');
                },
                'likes',
                'likes as is_like' => function ($query) {
                    $query->where('user_id', auth()->user()->user_id);
                },
            ])
            ->whereNull('deleted_at')
            ->whereNotNull('published_at')
            ->whereDoesntHave('flags')
            ->orderBy('pin_post', 'desc')
            ->orderBy('published_at', 'desc');

        // filter activity feed with challenge_id
        if (!is_null($request->challenge_id)) {
            $activityFeed
                ->where('feed_type', 'feed')
                ->where('challenge_id', $request->challenge_id);
        }

        // filter activity feed with live_id
        if (!is_null($request->live_id)) {
            $activityFeed
                ->where('feed_type', 'feed')
                ->where('live_id', $request->live_id);
        }

        // filter activity feed with meetup_id
        if (!is_null($request->meetup_id)) {
            $activityFeed
                ->where('feed_type', 'feed')
                ->where('meetup_id', $request->meetup_id);
        }

        // filter activity feed with interest_id
        if (!is_null($request->interest_id)) {
            // filter the activity feed based on filter_by
            if ($request->filter_by == "challenge")
                $activityFeed->where('feed_type', 'challenge')
                    ->whereHas('challenge', function ($query) use ($request) {
                        $query->where('interest_id', $request->interest_id);
                    });
            else if ($request->filter_by == "live_session")
                $activityFeed->where('feed_type', 'live session')
                    ->whereHas('liveSession', function ($query) use ($request) {
                        $query->where('interest_id', $request->interest_id);
                    });
            else if ($request->filter_by == "meetup")
                $activityFeed->where('feed_type', 'meetup')
                    ->whereHas('meetup', function ($query) use ($request) {
                        $query->where('interest_id', $request->interest_id);
                    });
            else if ($request->filter_by == "feed") {
                $activityFeed->where('feed_type', 'feed')
                    ->where('interest_id', $request->interest_id);
            } else {
                // complete activity feed
                $activityFeed->where('interest_id', $request->interest_id)
                    ->orWhereHas('challenge', function ($query) use ($request) {
                        $query->where('interest_id', $request->interest_id);
                    })->orWhereHas('liveSession', function ($query) use ($request) {
                        $query->where('interest_id', $request->interest_id);
                    })->orWhereHas('meetup', function ($query) use ($request) {
                        $query->where('interest_id', $request->interest_id);
                    });
            }
        }

        // filter user's post based
        if ($request->is_user_post == 1) {
            $activityFeed->where('user_id', auth()->user()->user_id)->where('feed_type', 'feed');
        }

        // filter official bwell post
        if ($request->is_official == 1) {
            $activityFeed->where('is_official', 1);
        }

        // filter activity feed

        if (!is_null($request->search)) {
            $keywords = explode(" ", $request->search);
            $activityFeed->where(function ($q) use ($keywords, $request) {
                $q->where('title', 'like', "%" . $request->search . "%");
                foreach ($keywords as $keyword) {
                    $q->orWhere('title', 'like', "%" . $keyword . "%")
                        ->orWhere('content', 'like', "%" . $keyword . "%")
                        ->orWhereHas('comments', function ($query) use ($keyword) {
                            $query->whereDoesntHave('flags')
                                ->where('comment', 'like', "%" . $keyword . "%")
                                ->orWhereHas('user', function ($query) use ($keyword) {
                                    $query
                                        ->where('first_name', 'like', "%" . $keyword . "%")
                                        ->orWhere('last_name', 'like', "%" . $keyword . "%");
                                });
                        })
                        ->orWhereHas('user', function ($query) use ($keyword) {
                            $query
                                ->where('first_name', 'like', "%" . $keyword . "%")
                                ->orWhere('last_name', 'like', "%" . $keyword . "%");
                        });
                }
            });
        }


        if ($request->my_interest == 1) {
            $clubs = UserClubInterest::where("user_id", auth()->user()->user_id)
                ->pluck('interest_id');

            $activityFeed->where(function ($query) use ($clubs) {
                $query->whereIn('interest_id', $clubs)
                    ->orWhereHas('challenge', function ($query) use ($clubs) {
                        $query->whereNotNull('published_at')->whereIn('interest_id', $clubs);
                    })->orWhereHas('liveSession', function ($query) use ($clubs) {
                        $query->whereNotNull('published_at')->whereIn('interest_id', $clubs);
                    })->orWhereHas('meetup', function ($query) use ($clubs) {
                        $query->whereNotNull('published_at')->whereIn('interest_id', $clubs);
                    });
            });

            if (is_null($request->search)) {
                $activityFeed->orWhere('is_announcement', 1);
            }
        }

        if ($request->featured == 1) {
            $challenges = Challenge::where('is_featured', 1)->whereNotNull('published_at')->orderBy('ended_at', 'desc')->get();
            $meetups = Meetup::where('is_featured', 1)->whereNotNull('published_at')->orderBy('ended_at', 'desc')->get();
            $liveSessions = LiveSession::where('is_featured', 1)->whereNotNull('published_at')->orderBy('ended_at', 'desc')->get();
            $responseObject = collect(
                ['featured_activities' => $challenges->merge($meetups->merge($liveSessions))->all()]
            );
            return $responseObject->merge($activityFeed->simplePaginate($per_page));
        }

        return $activityFeed->simplePaginate($per_page);
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
        $user_id = !is_null(auth()->user()) ? auth()->user()->user_id : 0;
        if ($request->scheduled_post == 1)
            $user_id = $request->user_id;

        $newActivityFeed = new ActivityFeed();

        $newActivityFeed->title = $request->title  ?: null;
        $newActivityFeed->content = $request->content  ?: null;
        $newActivityFeed->html_content = $request->html_content  ?: null;
        $newActivityFeed->feed_type = $request->feed_type ?: null;

        $newActivityFeed->user_id = $user_id ?: 0; // users
        $newActivityFeed->challenge_id = $request->challenge_id  ?: 0; // challenges
        $newActivityFeed->live_id = $request->live_id  ?: 0; // live session
        $newActivityFeed->interest_id = $request->interest_id ?: 0; // clubs
        $newActivityFeed->meetup_id = $request->meetup_id ?: 0; // meetups
        $newActivityFeed->pin_post = $request->pin_post ?: 0; // pin_post prioritize

        // Official BWell Post
        $newActivityFeed->is_official = $request->is_official ?: 0;
        $newActivityFeed->published_at = $request->published_at == 1 ? now() : null;


        // Challenge Entry as Activity Feed
        $newActivityFeed->is_challenge_entry = $request->is_challenge_entry  ?: 0;
        if ($request->is_challenge_entry == 1) {
            // update user's participation status to IN PROGRESS
            $userParticipant = ChallengeParticipant::where('challenge_id', $request->challenge_id)
                ->where('user_id', $user_id)
                ->first();
            if ($userParticipant) {
                $userParticipant->update(["status" => "DONE"]);
            }

            $newActivityFeed->published_at = now();
        }

        // Announcement
        $newActivityFeed->is_announcement = $request->is_announcement ?: 0;

        // Editor's Pick
        $newActivityFeed->editors_pick = $request->editors_pick ?: 0;

        // Charity Record
        $newActivityFeed->charity_id = $request->charity_id ?: 0;

        $newActivityFeed->save();

        if ($request->is_official == 1) {
            event(new NewOfficialPost(
                random_int(1000, 9999) . $newActivityFeed->feed_id,
                $newActivityFeed->title,
                $request->notification_message,
                $newActivityFeed->feed_id,
                $newActivityFeed->feed_type
            ));
            $users = User::where("is_verified", 1)->get();
            foreach ($users as $user) {
                $notif = new Notifications();
                $notif->title = $newActivityFeed->title;
                $notif->message = $request->notification_message;
                $notif->user_id = $user->user_id;
                $notif->feed_id = $newActivityFeed->feed_id;
                $notif->deep_link = "activity-feed/official-post/" . $newActivityFeed->feed_id;
                $notif->save();
            }

            if ($newActivityFeed->is_announcement == 1) {
                $fcm = new FCMNotificationController();
                $fcm->sendNotificationTopic(
                    env('APP_ENV') == 'production' ? "message_all_users" : "message_all_staging_users",
                    $notif->title,
                    $notif->message,
                    ["url" => $notif->deep_link]
                );
            }
        }

        // Upload images after saving the activity feed
        if ($newActivityFeed->feed_id) {
            if (is_array($request->images)) {
                $validator = Validator::make($request->images, [
                    'images.*' => 'mimes:jpg,jpeg,png|max:10240'
                ], [
                    'images.*.mimes' => 'Only jpeg, png, and jpg images are allowed',
                    'images.*.max' => 'Sorry! Maximum allowed size for an image is 10MB',
                ]);

                if (!$validator->fails()) {
                    foreach (array_slice($request->images, 0, 5) as $image) {
                        if (!is_null($image)) {
                            if (
                                $image->getClientOriginalExtension() === 'jpg'
                                || $image->getClientOriginalExtension() === 'jpeg'
                                || $image->getClientOriginalExtension() === 'png'
                            ) {
                                $randomHex1 = bin2hex(random_bytes(6));
                                $randomHex2 = bin2hex(random_bytes(6));
                                $uploadDate = now()->year . "-" . now()->month . "-" . now()->day;
                                $extension = $image->extension();
                                $newFileName = $uploadDate . '-' . $randomHex1 . '-' . $randomHex2 . '.'  . $extension;
                                $image->storeAs('/public/images/activity-feed/' . $newActivityFeed->feed_id, $newFileName);
                                $activityFeedImages = new ActivityFeedImage();
                                $activityFeedImages->feed_id = $newActivityFeed->feed_id;
                                $activityFeedImages->image_path = $newFileName;
                                $activityFeedImages->save();
                            } else {
                                return response(["error" => ["image" => 'Only jpeg, png, and jpg images are allowed']], 400);
                            }
                        }
                    }
                } else {
                    return response(["error" => ["image" => $validator->errors()->get('images.*')]], 400);
                }
            }
        }


        $newActivityFeed->images;

        if (!is_null($newActivityFeed->published_at)) {

            $activityFeed = ActivityFeed::with([
                'images',
                'clubInterest',
                'charity.images',
                'challenge' => function ($query) use ($user_id) {
                    $query
                        ->withCount([
                            'participants',
                            'participants as is_joined' => function ($query) use ($user_id) {
                                $query->where('user_id', $user_id);
                            }
                        ])
                        ->with(['clubInterest' => function ($query) use ($user_id) {
                            $query->withCount(['members as is_club_member' => function ($query) use ($user_id) {
                                $query->where('user_id', $user_id);
                            }]);

                            $query->withCount(['participatedChallenges as challenges_done_count' => function ($query) use ($user_id) {
                                $query->where('challenge_participants.user_id', $user_id)->where('status', 'DONE');
                            }]);

                            $query->withCount(['participatedMeetups as meetups_done_count' => function ($query) use ($user_id) {
                                $query->where('meetup_participants.user_id', $user_id)->where('status', 'DONE');
                            }]);

                            $query->withCount(['participatedLiveSessions as live_session_done_count' => function ($query) use ($user_id) {
                                $query->where('live_session_participants.user_id', $user_id)->where('status', 'DONE');
                            }]);
                        }]);
                },
                'liveSession' => function ($query) use ($user_id) {
                    $query
                        ->withCount([
                            'participants',
                            'participants as is_joined' => function ($query) use ($user_id) {
                                $query->where('user_id', $user_id);
                            }
                        ])
                        ->with(['clubInterest' => function ($query) use ($user_id) {
                            $query->withCount(['members as is_club_member' => function ($query) use ($user_id) {
                                $query->where('user_id', $user_id);
                            }]);

                            $query->withCount(['participatedChallenges as challenges_done_count' => function ($query) use ($user_id) {
                                $query->where('challenge_participants.user_id', $user_id)->where('status', 'DONE');
                            }]);

                            $query->withCount(['participatedMeetups as meetups_done_count' => function ($query) use ($user_id) {
                                $query->where('meetup_participants.user_id', $user_id)->where('status', 'DONE');
                            }]);

                            $query->withCount(['participatedLiveSessions as live_session_done_count' => function ($query) use ($user_id) {
                                $query->where('live_session_participants.user_id', $user_id)->where('status', 'DONE');
                            }]);
                        }]);
                },
                'meetup' => function ($query) use ($user_id) {
                    $query
                        ->withCount([
                            'participants',
                            'participants as is_joined' => function ($query) use ($user_id) {
                                $query->where('user_id', $user_id);
                            }
                        ])
                        ->with(['clubInterest' => function ($query) use ($user_id) {
                            $query->withCount(['members as is_club_member' => function ($query) use ($user_id) {
                                $query->where('user_id', $user_id);
                            }]);

                            $query->withCount(['participatedChallenges as challenges_done_count' => function ($query) use ($user_id) {
                                $query->where('challenge_participants.user_id', $user_id);
                            }]);

                            $query->withCount(['participatedMeetups as meetups_done_count' => function ($query) use ($user_id) {
                                $query->where('meetup_participants.user_id', $user_id);
                            }]);

                            $query->withCount(['participatedLiveSessions as live_session_done_count' => function ($query) use ($user_id) {
                                $query->where('live_session_participants.user_id', $user_id);
                            }]);
                        }]);
                },
                'user' => function ($query) {
                    $query->withSum(['bcoinTotal' => function ($query) {
                        $query->where('amount', '>', 0);
                    }], 'amount');
                },
            ])
                ->withCount([
                    'comments' => function ($query) {
                        $query->whereDoesntHave('flags');
                    },
                    'likes',
                    'likes as is_like' => function ($query) use ($user_id) {
                        $query->where('user_id', $user_id);
                    },
                ])
                ->where('feed_id', $newActivityFeed->feed_id)
                ->get();

            event(new NewActivityFeed((string) $activityFeed->first()));
            return ["data" => $activityFeed->first()];
        }

        return ["data" => $newActivityFeed];
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ActivityFeed  $activityFeed
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $activityFeed = ActivityFeed::with([
            'clubInterest',
            'images',
            'user' => function ($query) {
                $query->withSum(['bcoinTotal' => function ($query) {
                    $query->where('amount', '>', 0);
                }], 'amount');
            },
        ])->withCount([
            'likes',
            'likes as is_like' => function ($query) use ($request) {
                $query->where('user_id', auth()->user()->user_id);
            },
            'comments' => function ($query) {
                $query->whereDoesntHave('flags');
            },
        ])
            ->find($id);
        return response(["data" => $activityFeed]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ActivityFeed  $activityFeed
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
     * @param  \App\Models\ActivityFeed  $activityFeed
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ActivityFeed  $activityFeed
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $recordToDelete = ActivityFeed::find($id);
        if ($recordToDelete) {
            $recordToDelete->delete();

            $notifToDelete = Notifications::where("feed_id", (int) $id);
            $notifToDelete->delete();

            return ["data" => ["deleted_feed" => $recordToDelete]];
        }
        return ["error" => ["message" => "Activity feed not found."]];
    }


    public function feedLike(Request $request)
    {
        $notif = new Notifications();
        $user = User::where('user_id', auth()->user()->user_id)->first();
        $feed = ActivityFeed::where('feed_id', $request->feed_id)->first();
        $likeFound = ActivityFeedLike::where('feed_id', $request->feed_id)
            ->where('user_id', auth()->user()->user_id)
            ->first();
        if ($likeFound == null) {
            $feedLike = new ActivityFeedLike();
            $feedLike->feed_id = $request->feed_id;
            $feedLike->user_id = auth()->user()->user_id;
            $feedLike->save();

            if ($feed->user_id != auth()->user()->user_id) {
                $notif->message = $user->first_name . ' ' . $user->last_name . ' liked your post.';
                $notif->user_id = $feed->user_id; // receiver
                $notif->feed_id = $request->feed_id;
                $notif->deep_link = "activity-feed/user-post/" . $request->feed_id;
                $notif->save();
                $tokens = FCMNotification::where('user_id',  $notif->user_id)
                    ->pluck('fcm_token')
                    ->all();
                $fcm = new FCMNotificationController();
                $fcm->sendNotification(
                    $tokens,
                    '',
                    $notif->message,
                    ["url" =>  $notif->deep_link]
                );
            }
            event(new NewFeedLike(
                $feedLike->like_id,
                $feed->user_id,
                $user->first_name . ' ' . $user->last_name . ' liked your post.',
                (int) auth()->user()->user_id,
                (int) $request->feed_id,
                1,
                $feed->feed_type,
            ));

            return ["data" => "You like the activity feed."];
        }
        event(new NewFeedLike(
            $likeFound->like_id,
            $feed->user_id,
            "You unlike the activity feed.",
            auth()->user()->user_id,
            (int)$request->feed_id,
            -1,
            $feed->feed_type,
        ));

        $likeFound->forceDelete();

        return ["data" => "You unlike the activity feed."];
    }


    public function feedFlag(Request $request)
    {
        $feedFound = ActivityFeed::where('feed_id', $request->feed_id)->first();
        if (!is_null($feedFound)) {
            $isFlagged = ActivityFeedFlag::where('feed_id', $request->feed_id)->first();
            if (is_null($isFlagged)) {
                $feed = new ActivityFeedFlag();
                $feed->feed_id = $request->feed_id;
                $feed->user_id = auth()->user()->user_id;
                $feed->save();
                try {
                    Mail::send(
                        'post-comment-email-notif',
                        [
                            'description' => 'post',
                            'urlLink' =>
                            env('APP_ENV') == 'production' ?
                                "https://livetogive.co/admin/posts/" . $request->feed_id :
                                "https://staging-web.livetogive.co/admin/posts/" . $request->feed_id
                        ],
                        function ($message) {
                            $message
                                ->to('support@livetogive.co')
                                ->subject('A post has been flagged');
                        }
                    );
                } catch (\Throwable $th) {
                    return response(["error" => $th->getMessage()], 422);
                }
                return ["data" => "You flag an activity feed."];
            }
        }
    }
}
