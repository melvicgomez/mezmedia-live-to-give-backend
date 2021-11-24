<?php

namespace App\Http\Controllers;

use App\Models\ActivityFeed;
use App\Models\ActivityFeedImage;
use App\Models\User;
use App\Models\Notifications;
use App\PusherEvents\NewOfficialPost;
use App\PusherEvents\NewActivityFeed;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class CMSAllOfficialPostController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (auth()->user()->privilege == "moderator") {

            $showByStatus = $request->show_status ?: 'all'; // all | official | announcement
            $order_by = $request->order_by ?: 'feed_id'; // created_at | user.first_name
            $sort_by = $request->sort_by ?: 'desc'; // asc | desc

            $activityFeed = ActivityFeed::with([
                'images',
                'clubInterest',
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
                    'likes as is_like' => function ($query) {
                        $query->where('user_id', auth()->user()->user_id);
                    },
                ])
                ->where('is_official', 1)->whereNull('deleted_at');

            if (!is_null($request->search)) {
                $activityFeed->where(function ($q) use ($request) {
                    $q->where('title', 'like', "%" . $request->search . "%")
                        ->orWhere('content', 'like', "%" . $request->search . "%")
                        ->orWhere('html_content', 'like', "%" . $request->search . "%");
                });
            }

            if ($showByStatus == "official") {
                $activityFeed->where('is_announcement', 0);
            }

            if ($showByStatus == "announcement") {
                $activityFeed->where('is_announcement', 1);
            }

            $activityFeed->orderBy($order_by, $sort_by);

            return $activityFeed->paginate(200);
        }
        abort(400);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if ($request->feed_id) {
            // call update function
            return $this->update($request, $request->feed_id);
        }

        $newActivityFeed = new ActivityFeed();

        $newActivityFeed->title = $request->title  ?: null;
        $newActivityFeed->content = $request->content  ?: null;
        $newActivityFeed->html_content = $request->html_content  ?: null;
        $newActivityFeed->notification_message = $request->notification_message ?: null;
        $newActivityFeed->feed_type = $request->feed_type ?: null;

        $newActivityFeed->user_id = auth()->user()->user_id  ?: 0; // users
        $newActivityFeed->interest_id = $request->interest_id ?: 0; // clubs
        $newActivityFeed->pin_post = $request->pin_post ?: 0; // pin_post prioritize

        // Official BWell Post
        $newActivityFeed->is_official = $request->is_official ?: 0;
        $newActivityFeed->published_at = null;
        $newActivityFeed->scheduled_at =  $request->scheduled_at ?: null;

        // Announcement
        $newActivityFeed->is_announcement = $request->is_announcement ?: 0;

        $newActivityFeed->save();

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

        return ["data" => $newActivityFeed];
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
            $activityFeed = ActivityFeed::withTrashed()->with([
                'clubInterest',
                'images',
                'user' => function ($query) {
                    $query->withSum(['bcoinTotal' => function ($query) {
                        $query->where('amount', '>', 0);
                    }], 'amount');
                },
            ])->withCount([
                'likes',
                'likes as is_like' => function ($query) {
                    $query->where('user_id', auth()->user()->user_id);
                },
                'comments' => function ($query) {
                    $query->whereDoesntHave('flags');
                },
            ])
                ->find($id);
            return response(["data" => $activityFeed]);
        }
        abort(404);
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
                'content',
                'html_content',
                'pin_post',
                'scheduled_at'
            ]);

            $activityFeed = ActivityFeed::find($id);

            if (!is_null($activityFeed)) {
                var_dump($request->title);
                $activityFeed->update($fieldsToUpdate);

                if ($activityFeed->feed_type == 'official') {
                    if (isset($request->interest_id)) {
                        var_dump((int) $request->interest_id);
                        $activityFeed->update(['interest_id' => (int) $request->interest_id]);
                    }
                }

                if (is_null($activityFeed->published_at)) {
                    if (isset($request->notification_message)) {
                        $activityFeed->update(['notification_message' => $request->notification_message]);
                    }
                }

                if (is_array($request->images)) {
                    $validator = Validator::make($request->images, [
                        'images.*' => 'mimes:jpg,jpeg,png|max:10240'
                    ], [
                        'images.*.mimes' => 'Only jpeg, png, and jpg images are allowed',
                        'images.*.max' => 'Sorry! Maximum allowed size for an image is 10MB',
                    ]);

                    if (!$validator->fails()) {
                        foreach ($request->images as $image) {
                            if (!is_null($image)) {
                                $randomHex1 = bin2hex(random_bytes(6));
                                $randomHex2 = bin2hex(random_bytes(6));
                                $uploadDate = now()->year . "-" . now()->month . "-" . now()->day;
                                $extension = $image->extension();
                                $newFileName = $uploadDate . '-' . $randomHex1 . '-' . $randomHex2 . '.'  . $extension;
                                $image->storeAs('/public/images/activity-feed/' . $id, $newFileName);
                                $imagesToDelete = ActivityFeedImage::where('feed_id', $id);
                                if ($imagesToDelete) {
                                    $imagesToDelete->delete();
                                }
                                $activityFeedImages = new ActivityFeedImage();
                                $activityFeedImages->feed_id = $id;
                                $activityFeedImages->image_path = $newFileName;
                                $activityFeedImages->save();
                            }
                        }
                    }
                }
                $activityFeed->images;
                return ["data" => $activityFeed];
            }
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
            $recordToDelete = ActivityFeed::find($id);
            if ($recordToDelete) {
                $recordToDelete->delete();

                if ($recordToDelete->published_at) {
                    $notifToDelete = Notifications::where("feed_id", (int) $id);
                    $notifToDelete->delete();
                }

                return ["data" => ["deleted_feed" => $recordToDelete]];
            }
            return ["error" => ["message" => "Activity feed not found."]];
        }
        abort(404);
    }

    public function publishPost(Request $request, $id)
    {
        if (auth()->user()->privilege == "moderator") {
            $activityFeed = ActivityFeed::find($id);

            if (!is_null($activityFeed)) {
                if ($activityFeed->is_official == 1) {
                    if ($request->action == 'publish') {
                        $activityFeed->update([
                            "published_at" => now(),
                            "scheduled_at" => null
                        ]);

                        event(new NewOfficialPost(
                            random_int(1000, 9999) . $activityFeed->feed_id,
                            $activityFeed->title,
                            $activityFeed->notification_message,
                            $activityFeed->feed_id,
                            $activityFeed->feed_type
                        ));
                        $users = User::where("is_verified", 1)->get();
                        foreach ($users as $user) {
                            $notif = new Notifications();
                            $notif->title = $activityFeed->title;
                            $notif->message = $activityFeed->notification_message;
                            $notif->user_id = $user->user_id;
                            $notif->feed_id = $activityFeed->feed_id;
                            $notif->deep_link = "activity-feed/official-post/" . $activityFeed->feed_id;
                            $notif->save();
                        }

                        if ($activityFeed->is_announcement == 1) {
                            $fcm = new FCMNotificationController();
                            $fcm->sendNotificationTopic(
                                env('APP_ENV') == 'production' ? "message_all_users" : "message_all_staging_users",
                                $notif->title,
                                $notif->message,
                                ["url" => $notif->deep_link]
                            );
                        }

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
                                'comments' => function ($query) {
                                    $query->whereDoesntHave('flags');
                                },
                                'likes',
                                'likes as is_like' => function ($query) {
                                    $query->where('user_id', auth()->user()->user_id);
                                },
                            ])
                            ->where('feed_id', $activityFeed->feed_id)
                            ->get();

                        event(new NewActivityFeed((string) $activityFeed->first()));
                        return ["data" => $activityFeed->first()];
                    } else {
                        $activityFeed->update(["published_at" => null]);

                        $notifToDelete = Notifications::where("feed_id", (int) $id);
                        $notifToDelete->delete();

                        return ["data" => $activityFeed];
                    }
                }
            }
        }
        abort(404);
    }


    public function publishPostUnAuth(Request $request, $id)
    {
        $activityFeed = ActivityFeed::find($id);

        if (!is_null($activityFeed)) {
            if ($activityFeed->is_official == 1) {
                if ($request->action == 'publish') {
                    $activityFeed->update([
                        "published_at" => now(),
                        "scheduled_at" => null
                    ]);

                    event(new NewOfficialPost(
                        random_int(1000, 9999) . $activityFeed->feed_id,
                        $activityFeed->title,
                        $activityFeed->notification_message,
                        $activityFeed->feed_id,
                        $activityFeed->feed_type
                    ));
                    $users = User::where("is_verified", 1)->get();
                    foreach ($users as $user) {
                        $notif = new Notifications();
                        $notif->title = $activityFeed->title;
                        $notif->message = $activityFeed->notification_message;
                        $notif->user_id = $user->user_id;
                        $notif->feed_id = $activityFeed->feed_id;
                        $notif->deep_link = "activity-feed/official-post/" . $activityFeed->feed_id;
                        $notif->save();
                    }

                    if ($activityFeed->is_announcement == 1) {
                        $fcm = new FCMNotificationController();
                        $fcm->sendNotificationTopic(
                            env('APP_ENV') == 'production' ? "message_all_users" : "message_all_staging_users",
                            $notif->title,
                            $notif->message,
                            ["url" => $notif->deep_link]
                        );
                    }

                    $activityFeed = ActivityFeed::with([
                        'images',
                        'clubInterest',
                        'charity.images',
                    ])
                        ->withCount([
                            'comments' => function ($query) {
                                $query->whereDoesntHave('flags');
                            },
                            'likes',
                        ])
                        ->where('feed_id', $activityFeed->feed_id)
                        ->get();

                    event(new NewActivityFeed((string) $activityFeed->first()));
                    return ["data" => $activityFeed->first()];
                } else {
                    $activityFeed->update(["published_at" => null]);

                    $notifToDelete = Notifications::where("feed_id", (int) $id);
                    $notifToDelete->delete();

                    return ["data" => $activityFeed];
                }
            }
        }
    }


    public function scheduledPosts()
    {
        $tempNow = now()->timezone('Asia/Hong_Kong')->format('Y-m-d H:i');
        $scheduledPosts = ActivityFeed::whereNotNull('scheduled_at')
            ->where('scheduled_at', $tempNow)
            ->get();

        foreach ($scheduledPosts as $posts) {
            $request = new Request(["action" => 'publish']);
            $this->publishPostUnAuth($request, $posts->feed_id);
        }
    }
}
