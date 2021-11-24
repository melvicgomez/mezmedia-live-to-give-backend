<?php

namespace App\Http\Controllers;

use App\Models\ActivityFeed;
use App\Models\ActivityFeedComment;
use App\Models\ActivityFeedFlag;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CMSAllPostsController extends Controller
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
            $order_by = $request->order_by ?: 'feed_id'; // created_at | user.first_name
            $sort_by = $request->sort_by ?: 'desc'; // asc | desc

            $latestFlags = DB::table('activity_feed_flags')
                ->select('feed_id', DB::raw('MAX(created_at) as flag_created_at'))
                ->whereNull('deleted_at')
                ->groupBy('feed_id');

            $activityFeed = ActivityFeed::join('users', 'activity_feeds.user_id', '=', 'users.user_id')
                ->leftJoinSub($latestFlags, 'latest_flags', function ($join) {
                    $join->on('activity_feeds.feed_id', '=', 'latest_flags.feed_id');
                })
                ->select(
                    'users.user_id',
                    'users.first_name',
                    'users.last_name',
                    'activity_feeds.*',
                    'latest_flags.flag_created_at as flag_created_at',
                )
                ->where('feed_type', 'feed')
                ->with(['image'])
                ->withTrashed();

            if (isset($request->search)) {
                $activityFeed->where(function ($query) use ($request) {
                    $query->where('content', 'like', "%" . $request->search . "%")
                        ->orWhere('title', 'like', "%" . $request->search . "%")
                        ->orWhereHas('user', function ($query) use ($request) {
                            $query->whereRaw("CONCAT(`first_name`, ' ', `last_name`) LIKE ?", "%" . $request->search . "%");
                        });
                });
            }

            if ($showByStatus == "flagged") {
                $activityFeed->whereNotNull('flag_created_at');
            }

            if ($showByStatus == "deleted") {
                $activityFeed->whereNotNull('deleted_at');
            }

            $activityFeed->orderBy($order_by, $sort_by);

            return $activityFeed->paginate(200);
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
        if (auth()->user()->privilege == "moderator") {

            $activityFeed = ActivityFeed::with([
                'recentFlag',
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
            ])->withTrashed()
                ->find($id);

            return $activityFeed;
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
            $activityFeed = ActivityFeed::with([
                'recentFlag',
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
            ])->withTrashed()
                ->find($id);

            if (!is_null($activityFeed)) {

                $action = $request->action ?: 'restore';  // restore | unflag

                if ($action == 'restore') {
                    // restore deleted post
                    $activityFeed->restore();
                }

                if ($action == 'flag') {
                    $isFlagged = ActivityFeedFlag::where('feed_id', $id)->first();
                    if (is_null($isFlagged)) {
                        $feed = new ActivityFeedFlag();
                        $feed->feed_id = $id;
                        $feed->user_id = auth()->user()->user_id;
                        $feed->save();

                        $flagInfo = ActivityFeedFlag::with(['user'])->where('feed_id', $id)->first();
                        return $flagInfo;
                    }
                }

                if ($action == 'unflag') {
                    // unflag the post by feed_id
                    $activityFeed->flags()->delete();
                }
            }

            return $activityFeed;
        }
        abort(404);
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

    public function getPostComments(Request $request)
    {
        if (auth()->user()->privilege == "moderator") {

            if ($request->feed_id) {
                $feedComments = ActivityFeedComment::with(['user' => function ($query) {
                    $query->withSum(['bcoinTotal' => function ($query) {
                        $query->where('amount', '>', 0);
                    }], 'amount');
                }])
                    ->where('feed_id', $request->feed_id)
                    ->orderBy('created_at', 'desc')
                    ->whereDoesntHave('flags')
                    ->get();
                return ["data" => $feedComments];
            }
            return ["error" => ["message" => "You need to provide feed_id to get list of comments"]];
        }
    }
}
