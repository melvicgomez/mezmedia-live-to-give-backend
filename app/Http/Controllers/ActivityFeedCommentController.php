<?php

namespace App\Http\Controllers;

use App\Models\ActivityFeed;
use App\Models\ActivityFeedComment;
use App\Models\ActivityFeedCommentFlag;
use App\Models\FCMNotification;
use App\Models\Notifications;
use App\Models\User;
use Illuminate\Http\Request;
use App\PusherEvents\NewFeedCommentEvent;
use Illuminate\Support\Facades\Mail;

class ActivityFeedCommentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
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
        if ($request->feed_id) {

            $user_id = auth()->user()->user_id;
            $feed_id = $request->feed_id;
            $feedComment = new ActivityFeedComment();
            $feedComment->comment = $request->comment;
            $feedComment->user_id = $user_id;
            $feedComment->feed_id = $feed_id;
            $feedComment->save();

            $feedComment->user;
            $feedComment->user->loadSum(['bcoinTotal' => function ($query) {
                $query->where('amount', '>', 0);
            }], 'amount');

            $notif = new Notifications();
            $user = User::where('user_id', $user_id)->first();
            $feed = ActivityFeed::where('feed_id', $request->feed_id)->first();

            $otherUsersInComment = ActivityFeedComment::where('feed_id', $request->feed_id)
                ->whereNotIn('user_id', [$user_id, $feed->user_id])
                ->groupBy('user_id')
                ->orderBy('created_at', 'desc')
                ->whereDoesntHave('flags')
                ->get();

            $otherUsersId = $otherUsersInComment->pluck('user_id');

            if ($feed->user_id != $user_id) {
                $notif->title = 'New Comment!';
                $notif->message = $user->first_name . ' ' . $user->last_name . ' has commented on your post.';
                $notif->user_id = $feed->user_id; // receiver
                $notif->source_user_id = $user_id; // sender
                $notif->feed_id = $feed->feed_id;
                $notif->deep_link = "activity-feed/user-post/" . $feed->feed_id;
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

            foreach ($otherUsersId as $userId) {
                $notif = new Notifications();
                $notif->title = 'New Comment!';
                $notif->message = 'There is a new comment in the post you commented on.';
                $notif->user_id = $userId; // receiver
                $notif->source_user_id = $user_id; // sender
                $notif->feed_id = $feed->feed_id;
                $notif->deep_link = "activity-feed/user-post/" . $feed->feed_id;
                $notif->save();

                $tokens = FCMNotification::where('user_id',  $notif->user_id)
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

            event(new NewFeedCommentEvent(
                $notif->notification_id,
                $feed->user_id,
                $notif->message,
                (string) $feedComment,
                $feed->feed_type,
                $feed->feed_id,
            ));
            return ["data" => $feedComment];
        }

        return ["error" => ["message" => "You need to provide feed_id to get list of comments"]];
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ActivityFeedComment  $activityFeedComment
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ActivityFeedComment  $activityFeedComment
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
     * @param  \App\Models\ActivityFeedComment  $activityFeedComment
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ActivityFeedComment  $activityFeedComment
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $commentToDelete = ActivityFeedComment::find($id);
        if ($commentToDelete) {
            $commentToDelete->delete();
            return ["data" => ["deleted_comment" => $commentToDelete]];
        }
        return ["error" => ["message" => "Comment not found."]];
    }


    public function feedFlag(Request $request)
    {

        $feedFound = ActivityFeedComment::where('comment_id', $request->comment_id)->first();
        if (!is_null($feedFound)) {
            $isFlagged = ActivityFeedCommentFlag::where('comment_id', $request->comment_id)->first();
            if (is_null($isFlagged)) {
                $feedComment = new ActivityFeedCommentFlag();
                $feedComment->comment_id = $request->comment_id;
                $feedComment->user_id = auth()->user()->user_id;
                $feedComment->save();
                try {
                    Mail::send(
                        'post-comment-email-notif',
                        [
                            'description' => 'comment',
                            'urlLink' =>
                            env('APP_ENV') == 'production' ?
                                "https://livetogive.co/admin/comments/" . $feedComment->comment_id :
                                "https://staging-web.livetogive.co/admin/comments/" . $feedComment->comment_id
                        ],
                        function ($message) {
                            $message
                                ->to('support@livetogive.co')
                                ->subject('A comment has been flagged');
                        }
                    );
                } catch (\Throwable $th) {
                    return response(["error" => $th->getMessage()], 422);
                }
                return ["data" => "You flag a comment."];
            }
        }
    }
}
