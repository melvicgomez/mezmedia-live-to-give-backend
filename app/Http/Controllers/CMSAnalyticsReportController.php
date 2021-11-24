<?php

namespace App\Http\Controllers;

use App\Models\ActivityFeed;
use App\Models\ActivityFeedComment;
use App\Models\ActivityFeedLike;
use App\Models\Challenge;
use App\Models\ClubInterest;
use App\Models\LiveSession;
use App\Models\Meetup;
use App\Models\MeetupParticipant;
use App\Models\User;
use App\Models\UserCheckInModel;
use App\Models\UserClubInterest;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CMSAnalyticsReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $filterDate = $request->filter_date ?: now();
        // count of verified users
        $numberOfVerifiedUsers = User::where('is_verified', 1)
            ->where('privilege', 'user')
            ->whereDate('created_at', '<=', $filterDate)
            ->count();
        $countries = User::where('is_verified', 1)
            ->where('privilege', 'user')
            ->whereDate('created_at', '<=', $filterDate)
            ->get()
            ->groupBy('country_code')
            ->toArray();

        $usersPerCountry = [];
        foreach ($countries as $key => $country) {
            array_push($usersPerCountry,  ["country" => $key, "users_count" => count($country)]);
        }

        // percentage of users with and without clubs
        $usersWithClubs = User::where('is_verified', 1)
            ->where('privilege', 'user')
            ->whereDate('created_at', '<=', $filterDate)
            ->whereHas('userInterests')
            ->count();

        $usersWithoutClubs = User::where('is_verified', 1)
            ->where('privilege', 'user')
            ->whereDate('created_at', '<=', $filterDate)
            ->whereDoesntHave('userInterests')
            ->count();

        // count of club members
        $clubsWithUsersCount = ClubInterest::select(
            'interest_id',
            'club_id',
            'interest_name',
            'icon_name',
            'image_cover',
            'description'
        )->with(['club'])
            ->withCount(
                ['members' => function ($query) use ($filterDate) {
                    $query->whereDate('created_at', '<=', $filterDate)
                        ->whereHas('user', function ($query) {
                            $query->where('is_verified', 1)
                                ->where('privilege', 'user');
                        });
                }]
            )->get()
            ->groupBy('club.club_name')
            ->toArray();

        $totalCheckin = UserCheckInModel::withTrashed()
            ->whereDate(
                'check_in_date_local',
                $filterDate
            )
            ->count();

        $totalComments = ActivityFeedComment::withTrashed()
            ->whereDate('created_at', '<=', $filterDate)
            ->count();
        $totalLikes = ActivityFeedLike::withTrashed()
            ->whereDate('created_at', '<=', $filterDate)
            ->count();

        return response([
            "users" => [
                "total_user_checkin" => $totalCheckin,
                "total_users" => $numberOfVerifiedUsers,
                "withClubs" => $usersWithClubs,
                "withoutClubs" => $usersWithoutClubs,
                "per_country" => $usersPerCountry
            ],
            "total_comments" => $totalComments,
            "total_likes" => $totalLikes,
            "clubs" => $clubsWithUsersCount,
        ], 200);
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

    public function getTopFeedEventReport(Request $request)
    {
        $filterDate = $request->filter_date ?: now();

        // TOP 3 Weekly Challenges
        $tempAllChallenges = Challenge::select(
            'challenge_id',
            'interest_id',
            'title',
            'image_cover',
            'type',
            'target_unit',
            'is_team_challenge',
            'is_trackable',
            'duration',
            'registration_ended_at',
            'started_at',
            'ended_at',
        )
            ->with([
                'clubInterest:interest_id,interest_name,description,image_cover,icon_name'
            ])
            ->whereNotNull('published_at')
            ->withCount(['participants' => function ($query) use ($filterDate) {
                $query->whereDate('participated_at', '<=', $filterDate);
            }])
            // ->where('duration', 'like', '%weekly%')
            ->get()
            ->sortByDesc('participants_count');


        $tempWeeklyChallenges =  $tempAllChallenges
            ->filter(function ($challenge) {
                return strtolower($challenge->duration) == "weekly";
            })
            ->values();

        $tempMonthlyChallenges = $tempAllChallenges
            ->filter(function ($challenge) {
                return strtolower($challenge->duration) == "monthly";
            })
            ->values();

        $tempDailyChallenges = $tempAllChallenges
            ->filter(function ($challenge) {
                return strtolower($challenge->duration) == "daily";
            })
            ->values();
        $topWeeklyChal =  [];
        $top3RanksWeeklyChal =  [];

        foreach ($tempWeeklyChallenges as $key => $value) {
            if ($value->participants_count > 0) {
                if (count($top3RanksWeeklyChal) !== 0) {
                    if ($top3RanksWeeklyChal[count($top3RanksWeeklyChal) - 1] != $value->participants_count) {
                        if (count($top3RanksWeeklyChal) == 3) {
                            continue;
                        } else {
                            array_push($topWeeklyChal, $value);
                            array_push($top3RanksWeeklyChal, $value->participants_count);
                        }
                    } else {
                        array_push($topWeeklyChal, $value);
                    }
                } else {
                    array_push($topWeeklyChal, $value);
                    array_push($top3RanksWeeklyChal, $value->participants_count);
                }
            }
        }

        $topMonthlyChal =  [];
        $top3RanksMonthlyChal =  [];

        foreach ($tempMonthlyChallenges as $key => $value) {
            if ($value->participants_count > 0) {
                if (count($top3RanksMonthlyChal) !== 0) {
                    if ($top3RanksMonthlyChal[count($top3RanksMonthlyChal) - 1] != $value->participants_count) {
                        if (count($top3RanksMonthlyChal) == 3) {
                            continue;
                        } else {
                            array_push($topMonthlyChal, $value);
                            array_push($top3RanksMonthlyChal, $value->participants_count);
                        }
                    } else {
                        array_push($topMonthlyChal, $value);
                    }
                } else {
                    array_push($topMonthlyChal, $value);
                    array_push($top3RanksMonthlyChal, $value->participants_count);
                }
            }
        }

        $topDailyChal =  [];
        $top3RanksDailyChal =  [];

        foreach ($tempDailyChallenges as $key => $value) {
            if ($value->participants_count > 0) {
                if (count($top3RanksDailyChal) !== 0) {
                    if ($top3RanksDailyChal[count($top3RanksDailyChal) - 1] != $value->participants_count) {
                        if (count($top3RanksDailyChal) == 3) {
                            continue;
                        } else {
                            array_push($topDailyChal, $value);
                            array_push($top3RanksDailyChal, $value->participants_count);
                        }
                    } else {
                        array_push($topDailyChal, $value);
                    }
                } else {
                    array_push($topDailyChal, $value);
                    array_push($top3RanksDailyChal, $value->participants_count);
                }
            }
        }

        // Top 3 Posts with Most Likes
        // $top3PostsLikesCtr =  [];
        // $top3FeedPostsLikes =  [];

        // $allFeedPosts = ActivityFeed::with([
        //     'images',
        //     'clubInterest',
        //     'user' => function ($query) {
        //         $query->withSum(['bcoinTotal' => function ($query) {
        //             $query->where('amount', '>', 0);
        //         }], 'amount');
        //     },
        // ])
        //     ->withCount([
        //         'comments' => function ($query) use ($filterDate) {
        //             $query->where('created_at', '<=', $filterDate)
        //                 ->whereDoesntHave('flags');
        //         },
        //         'likes' => function ($query) use ($filterDate) {
        //             $query->where('created_at', '<=', $filterDate);
        //         },
        //         'likes as is_like' => function ($query) {
        //             $query->where('user_id', auth()->user()->user_id);
        //         },
        //     ])
        //     ->whereDoesntHave('flags')
        //     ->where('feed_type', 'feed')
        //     ->whereDate('created_at', '<=', $filterDate);

        // $topPostsLikes =  $allFeedPosts->orderBy('likes_count', 'desc')
        //     ->get();

        // foreach ($topPostsLikes as $key => $post) {
        //     if ($post->likes_count > 0) {
        //         if (count($top3PostsLikesCtr) !== 0) {
        //             if ($top3PostsLikesCtr[count($top3PostsLikesCtr) - 1] != $post->likes_count) {
        //                 if (count($top3PostsLikesCtr) == 3) {
        //                     continue;
        //                 } else {
        //                     array_push($top3FeedPostsLikes, $post);
        //                     array_push($top3PostsLikesCtr, $post->likes_count);
        //                 }
        //             } else {
        //                 array_push($top3FeedPostsLikes, $post);
        //             }
        //         } else {
        //             array_push($top3FeedPostsLikes, $post);
        //             array_push($top3PostsLikesCtr, $post->likes_count);
        //         }
        //     }
        // }

        // // Top 3 Posts with Most Comments
        // $top3PostsCommentsCtr =  [];
        // $top3FeedPostsComments =  [];

        // $topPostsComments = $allFeedPosts->orderBy('comments_count', 'desc')
        //     ->get();

        // foreach ($topPostsComments as $key => $post) {
        //     if ($post->comments_count > 0) {
        //         if (count($top3PostsCommentsCtr) !== 0) {
        //             if ($top3PostsCommentsCtr[count($top3PostsCommentsCtr) - 1] != $post->comments_count) {
        //                 if (count($top3PostsCommentsCtr) == 3) {
        //                     continue;
        //                 } else {
        //                     array_push($top3FeedPostsComments, $post);
        //                     array_push($top3PostsCommentsCtr, $post->comments_count);
        //                 }
        //             } else {
        //                 array_push($top3FeedPostsComments, $post);
        //             }
        //         } else {
        //             array_push($top3FeedPostsComments, $post);
        //             array_push($top3PostsCommentsCtr, $post->comments_count);
        //         }
        //     }
        // }

        return response([
            "popular_events" => [
                "popular_daily_challenges" => $topDailyChal,
                "popular_weekly_challenges" => $topWeeklyChal,
                "popular_monthly_challenges" => $topMonthlyChal,
            ],
            // "top_posts_likes" => array_slice($topPostsLikes, 0, 3),
            // "top_posts_comments" => array_slice($topPostsComments, 0, 3)
            "top_posts_likes" => [],
            "top_posts_comments" => []
        ], 200);

        // return response([
        //     "popular_events" => [
        //         "popular_daily_challenges" => [],
        //         "popular_weekly_challenges" => [],
        //         "popular_monthly_challenges" => [],
        //     ],
        // "top_posts_likes" => [],
        // "top_posts_comments" => []
        // ], 200);
    }

    public function getChallengeInfoReport(Request $request)
    {
        $filterStartDate = $request->filter_start_date ?: now();
        $filterEndDate = $request->filter_end_date ?: now();

        $dailyChallengesCount = Challenge::whereNotNull('published_at')
            ->where('duration', 'like', '%daily%')
            ->count();

        $weeklyChallengesCount = Challenge::whereNotNull('published_at')
            ->where('duration', 'like', '%weekly%')
            ->count();

        $monthlyChallengesCount = Challenge::whereNotNull('published_at')
            ->where('duration', 'like', '%monthly%')
            ->count();

        $dailyChallengeUsers = collect();
        Challenge::with([
            'participants' => function ($query) use ($filterStartDate, $filterEndDate) {
                $query
                    ->whereDate('participated_at', '<=', $filterEndDate)
                    ->whereHas('user',  function ($query) {
                        $query->where('privilege', 'user')
                            ->where('is_verified', 1);
                    });
            },
        ])
            ->whereNotNull('published_at')
            ->where('duration', 'like', '%daily%')
            ->where(function ($query) use ($filterStartDate, $filterEndDate) {
                $query->where(function ($query) use ($filterStartDate, $filterEndDate) {
                    $query->whereDate('started_at', '<=', $filterStartDate)
                        ->whereDate('ended_at', '>=', $filterEndDate);
                })->orWhereBetween('ended_at', [$filterStartDate, $filterEndDate])
                    ->orWhereBetween('started_at', [$filterStartDate, $filterEndDate]);
            })
            ->get()
            ->each(function ($p, $k) use (&$dailyChallengeUsers) {
                $dailyChallengeUsers = $dailyChallengeUsers->merge($p->participants);
            });

        $weeklyChallengeUsers = collect();
        Challenge::with([
            'participants' => function ($query) use ($filterStartDate, $filterEndDate) {
                $query
                    ->whereDate('participated_at', '<=', $filterEndDate)
                    ->whereHas('user',  function ($query) {
                        $query->where('privilege', 'user')
                            ->where('is_verified', 1);
                    });
            },
        ])
            ->whereNotNull('published_at')
            ->where('duration', 'like', '%weekly%')
            ->where(function ($query) use ($filterStartDate, $filterEndDate) {
                $query->where(function ($query) use ($filterStartDate, $filterEndDate) {
                    $query->whereDate('started_at', '<=', $filterStartDate)
                        ->whereDate('ended_at', '>=', $filterEndDate);
                })->orWhereBetween('ended_at', [$filterStartDate, $filterEndDate])
                    ->orWhereBetween('started_at', [$filterStartDate, $filterEndDate]);
            })
            ->get()
            ->each(function ($p, $k) use (&$weeklyChallengeUsers) {
                $weeklyChallengeUsers = $weeklyChallengeUsers->merge($p->participants);
            });

        $monthlyChallengeUsers = collect();
        Challenge::select(
            'challenge_id',
            'title',
            'started_at',
            'ended_at',
            'is_trackable',
            'is_team_challenge',
        )->with([
            'participants' => function ($query) use ($filterStartDate, $filterEndDate) {
                $query
                    ->whereDate('participated_at', '<=', $filterEndDate)
                    ->whereHas('user',  function ($query) {
                        $query->where('privilege', 'user')
                            ->where('is_verified', 1);
                    });
            },
        ])
            ->whereNotNull('published_at')
            ->where('duration', 'like', '%monthly%')
            ->where(function ($query) use ($filterStartDate, $filterEndDate) {
                $query->where(function ($query) use ($filterStartDate, $filterEndDate) {
                    $query->whereDate('started_at', '<=', $filterStartDate)
                        ->whereDate('ended_at', '>=', $filterEndDate);
                })->orWhereBetween('ended_at', [$filterStartDate, $filterEndDate])
                    ->orWhereBetween('started_at', [$filterStartDate, $filterEndDate]);
            })
            ->get()
            ->each(function ($p, $k) use (&$monthlyChallengeUsers) {
                $monthlyChallengeUsers = $monthlyChallengeUsers->merge($p->participants);
            });

        return response([
            "challenges" => [
                "daily_users" => $dailyChallengeUsers->unique('user_id')->count(),
                "daily_challenge_count" => $dailyChallengesCount,
                "weekly_users" => $weeklyChallengeUsers->unique('user_id')->count(),
                "weekly_challenge_count" => $weeklyChallengesCount,
                "monthly_users" => $monthlyChallengeUsers->unique('user_id')->count(),
                "monthly_challenge_count" => $monthlyChallengesCount,
            ],
        ], 200);


        // return response([
        //     "challenges" => [
        //         "daily_users" => [],
        //         "daily_challenge_count" => 0,
        //         "weekly_users" => [],
        //         "weekly_challenge_count" => 0,
        //         "monthly_users" => [],
        //         "monthly_challenge_count" => 0,
        //     ],
        // ], 200);
    }
}
