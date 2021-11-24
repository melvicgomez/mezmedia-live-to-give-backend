<?php

namespace App\Http\Controllers;

use App\Models\ActivityFeedComment;
use App\Models\ActivityFeedCommentFlag;
use Illuminate\Http\Request;

class CMSAllCommentsController extends Controller
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
            $order_by = $request->order_by ?: 'comment_id'; // column name
            $sort_by = $request->sort_by ?: 'desc'; // asc | desc

            $feedComments = ActivityFeedComment::join('users', 'activity_feed_comments.user_id', '=', 'users.user_id')
                ->leftJoin('activity_feed_comment_flags', 'activity_feed_comments.comment_id', '=', 'activity_feed_comment_flags.comment_id')
                ->select(
                    'users.user_id',
                    'users.first_name',
                    'users.last_name',
                    'activity_feed_comments.comment_id',
                    'activity_feed_comments.feed_id',
                    'activity_feed_comments.comment',
                    'activity_feed_comments.created_at as comment_created_at',
                    'activity_feed_comments.deleted_at as comment_deleted_at',
                    'activity_feed_comment_flags.flag_id',
                    'activity_feed_comment_flags.created_at as flag_created_at',
                )
                ->groupBy('activity_feed_comments.comment_id')
                ->withTrashed();

            if (isset($request->search)) {
                $feedComments->where(function ($q) use ($request) {
                    $q->where('comment', 'like', "%" . $request->search . "%")
                        ->orWhereHas('user', function ($q) use ($request) {
                            $q->whereRaw("CONCAT(`first_name`, ' ', `last_name`) LIKE ?", "%" . $request->search . "%");
                        });
                });
            }

            if ($showByStatus == "flagged")
                $feedComments->whereHas('flags');

            if ($showByStatus == "deleted")
                $feedComments->whereNotNull('activity_feed_comments.deleted_at');

            $feedComments->orderBy($order_by, $sort_by);

            return $feedComments->paginate(200);
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
            $comment = ActivityFeedComment::withTrashed()
                ->with([
                    'user' => function ($query) {
                        $query->withSum(['bcoinTotal' => function ($query) {
                            $query->where('amount', '>', 0);
                        }], 'amount');
                    },
                    'recentFlag.user' => function ($query) {
                        $query->withSum(['bcoinTotal' => function ($query) {
                            $query->where('amount', '>', 0);
                        }], 'amount');
                    },
                    'activityFeed' => function ($query) {
                        $query->with([
                            'images',
                            'clubInterest',
                            'user' => function ($query) {
                                $query->withSum(['bcoinTotal' => function ($query) {
                                    $query->where('amount', '>', 0);
                                }], 'amount');
                            },
                            'comments' => function ($query) {
                                $query
                                    ->with([
                                        'user' => function ($query) {
                                            $query->withSum(['bcoinTotal' => function ($query) {
                                                $query->where('amount', '>', 0);
                                            }], 'amount');
                                        },
                                        // 'recentFlag' => function ($query) {
                                        //     $query
                                        //         ->with([
                                        //             'user'
                                        //         ]);
                                        // }
                                    ])
                                    ->withTrashed();
                            },
                        ])
                            ->withCount([
                                'likes',
                                'comments' => function ($query) {
                                    $query->withTrashed();
                                },
                            ])
                            ->withTrashed();
                    }
                ])
                ->find($id);
            return $comment;
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
            $comment = ActivityFeedComment::withTrashed()
                // ->with(['flags' => function ($q) {
                //     $q->withTrashed();
                // }])
                ->where('comment_id', $id)
                ->first();
            if (!is_null($comment)) {
                $action = $request->action ?: 'unflag';

                if ($action == 'flag') {
                    $isFlagged = ActivityFeedCommentFlag::where('comment_id', $id)->first();
                    if (is_null($isFlagged)) {
                        $feedComment = new ActivityFeedCommentFlag();
                        $feedComment->comment_id = $id;
                        $feedComment->user_id = auth()->user()->user_id;
                        $feedComment->save();

                        $flagInfo = ActivityFeedCommentFlag::with(['user'])->where('comment_id', $id)->first();
                        return $flagInfo;
                    }
                }

                if ($action == 'unflag') {
                    $commentFlags = ActivityFeedCommentFlag::withTrashed()->where('comment_id', $id);
                    $commentFlags->delete(); // delete all comment's flag
                }

                return response()->json(null, 204);
            }
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
        if (auth()->user()->privilege == "moderator") {
            $comment = ActivityFeedComment::withTrashed()
                ->where('comment_id', $id)
                ->first();
            if (!is_null($comment)) {
                $comment->delete();
            }
            return response()->json(null, 204);
        }
        abort(404);
    }
}
