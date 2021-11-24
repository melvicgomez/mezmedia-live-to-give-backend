<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Challenge;
use App\Models\Meetup;
use App\Models\LiveSession;
use Illuminate\Http\Request;

class CMSAllUsersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $per_page = !is_null($request->per_page) ? (int) $request->per_page : 200;

        if (auth()->user()->privilege == "moderator") {
            $order_by = $request->order_by ?: 'user_id'; // column name
            $sort_by = $request->sort_by ?: 'asc'; // asc | desc

            $users = User::where('is_verified', 1);

            if (isset($request->search)) {
                $users->where(function ($query) use ($request) {
                    $query->where('first_name', 'like', "%" . $request->search . "%")
                        ->orWhere('last_name', 'like', "%" . $request->search . "%")
                        ->orWhere('email', 'like', "%" . $request->search . "%");
                });
            }

            $users->withSum(['bcoinTotal' => function ($query) {
                $query->where('amount', '>', 0);
            }], 'amount');

            $users->withCount([
                'activityChallenges',
                'activityMeetups',
                'activityLiveSessions',
                'feedPosts',
                'comments',
                'feedPostFlags',
                'commentFlags'
            ]);

            $users->orderBy('is_verified', 'desc')
                ->orderBy('created_at', 'desc')
                ->orderBy($order_by, $sort_by);
            return $users->paginate($per_page);
        }
        abort(404);
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
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
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

    public function deleteUserPhoto($id)
    {
        if (auth()->user()->privilege == "moderator") {
            $user = User::find($id);
            if (!is_null($user)) {
                $user->update(["photo_url" => null]);
                return  $user;
            }
        }
        abort(404);
    }

    public function getUserComments(Request $request)
    {
        if (auth()->user()->privilege == "moderator") {
            $showByStatus = $request->show_status ?: 'all'; // all | flagged | deleted

            $user = User::find($request->user_id);
            if (!is_null($user)) {
                $userComments = $user->comments();
                $userComments->with(['recentFlag']);

                if ($showByStatus == "flagged") {
                    $userComments->whereHas('recentFlag');
                }

                if ($showByStatus == "deleted") {
                    $userComments->whereNotNull('deleted_at');
                }

                if (isset($request->search) && !empty($request->search)) {
                    $userComments->where('comment', 'like', "%" . $request->search . "%");
                }

                $userComments->orderBy('comment_id', 'desc');
                return  $userComments->paginate(200);
            }
        }
        abort(404);
    }

    public function getUserFeedPosts(Request $request)
    {
        if (auth()->user()->privilege == "moderator") {

            $user = User::find($request->user_id);

            if (!is_null($user)) {
                $userFeedPosts = $user->feedPosts();
                $userFeedPosts->with(['user', 'images'])->withCount([
                    'comments' => function ($query) {
                        $query->whereDoesntHave('flags');
                    },
                    'likes',
                    'likes as is_like' => function ($query) use ($request) {
                        $query->where('user_id', $request->user_id);
                    },
                ]);
                $userFeedPosts->orderBy('feed_id', 'desc');
                return  $userFeedPosts->paginate(20);
            }
        }
        abort(404);
    }

    public function getUserChallenges(Request $request)
    {
        if (auth()->user()->privilege == "moderator") {
            $per_page = !is_null($request->per_page) ? (int) $request->per_page : 20;

            $challenges = Challenge::with(['clubInterest' => function ($query) use ($request) {
                $query->withCount(['members as is_club_member' => function ($query) use ($request) {
                    $query->where('user_id', $request->user_id);
                }]);
                $query->withCount(['participatedChallenges as challenges_done_count' => function ($query) use ($request) {
                    $query->where('challenge_participants.user_id', $request->user_id)->where('status', 'DONE');
                }]);

                $query->withCount(['participatedMeetups as meetups_done_count' => function ($query) use ($request) {
                    $query->where('meetup_participants.user_id', $request->user_id)->where('status', 'DONE');
                }]);

                $query->withCount(['participatedLiveSessions as live_session_done_count' => function ($query) use ($request) {
                    $query->where('live_session_participants.user_id', $request->user_id)->where('status', 'DONE');
                }]);
            }])
                ->whereNull('deleted_at')
                ->whereNotNull('published_at')
                ->withCount([
                    'participants',
                    'participants as is_joined_challenge' => function ($query) use ($request) {
                        $query->where('user_id', $request->user_id);
                    },
                ]);

            // filter user's post based
            $challenges->whereHas('participants', function ($query) use ($request) {
                $query->where('user_id', $request->user_id);
            });

            // clone meetup and get all ended challenges
            $endedChallenges = clone $challenges;
            $endedChallenges
                ->orderBy('ended_at', 'desc')
                ->where('ended_at', '<=', now()->format('Y-m-d H:i'));

            // clone meetup and get all active challenges
            $activeChallenges = clone $challenges;
            $activeChallenges
                ->orderBy('ended_at', 'asc')
                ->where('ended_at', '>=', now()->format('Y-m-d H:i'));
            $responseObject = collect(
                ['ongoing_activity' => $activeChallenges->get()]
            );

            return $responseObject->merge($endedChallenges->simplePaginate($per_page));
        }
        abort(404);
    }

    public function getUserLiveSessions(Request $request)
    {
        if (auth()->user()->privilege == "moderator") {
            $per_page = !is_null($request->per_page) ? (int) $request->per_page : 20;

            $liveSessions = LiveSession::with(['clubInterest' => function ($query) use ($request) {
                $query->withCount(['members as is_club_member' => function ($query) use ($request) {
                    $query->where('user_id', $request->user_id);
                }]);
                $query->withCount(['participatedChallenges as challenges_done_count' => function ($query) use ($request) {
                    $query->where('challenge_participants.user_id', $request->user_id)->where('status', 'DONE');
                }]);

                $query->withCount(['participatedMeetups as meetups_done_count' => function ($query) use ($request) {
                    $query->where('meetup_participants.user_id', $request->user_id)->where('status', 'DONE');
                }]);

                $query->withCount(['participatedLiveSessions as live_session_done_count' => function ($query) use ($request) {
                    $query->where('live_session_participants.user_id', $request->user_id)->where('status', 'DONE');
                }]);
            }])
                ->whereNull('deleted_at')
                ->whereNotNull('published_at')
                ->withCount([
                    'participants',
                    'participants as is_joined_live_session' => function ($query) use ($request) {
                        $query->where('user_id', $request->user_id);
                    },
                ]);

            $liveSessions->whereHas('participants', function ($query) use ($request) {
                $query->where('user_id', $request->user_id);
            });

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
        abort(404);
    }

    public function getUserMeetups(Request $request)
    {
        if (auth()->user()->privilege == "moderator") {
            $per_page = !is_null($request->per_page) ? (int) $request->per_page : 20;

            $meetups = Meetup::with(['clubInterest' => function ($query) use ($request) {
                $query->withCount(['members as is_club_member' => function ($query) use ($request) {
                    $query->where('user_id', $request->user_id);
                }]);
                $query->withCount(['participatedChallenges as challenges_done_count' => function ($query) use ($request) {
                    $query->where('challenge_participants.user_id', $request->user_id)->where('status', 'DONE');
                }]);

                $query->withCount(['participatedMeetups as meetups_done_count' => function ($query) use ($request) {
                    $query->where('meetup_participants.user_id', $request->user_id)->where('status', 'DONE');
                }]);

                $query->withCount(['participatedLiveSessions as live_session_done_count' => function ($query) use ($request) {
                    $query->where('live_session_participants.user_id', $request->user_id)->where('status', 'DONE');
                }]);
            }])
                ->whereNull('deleted_at')
                ->whereNotNull('published_at')
                ->withCount([
                    'participants',
                    'participants as is_joined_meetup' => function ($query) use ($request) {
                        $query->where('user_id', $request->user_id);
                    },
                ]);

            $meetups->whereHas('participants', function ($query) use ($request) {
                $query->where('user_id', $request->user_id);
            });

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
        abort(404);
    }

    public function getUserHistory(Request $request)
    {
        if (auth()->user()->privilege == "moderator") {
            $user = User::find($request->user_id);
            if (!is_null($user)) {
                $userHistory = $user
                    ->bcoinTotal()
                    ->withTrashed()
                    ->orderBy('created_at', 'desc');
                return  $userHistory->paginate(20);
            }
        }
        abort(404);
    }
}
