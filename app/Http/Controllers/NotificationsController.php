<?php

namespace App\Http\Controllers;

use App\Models\FCMNotification;
use App\Models\Notifications;
use App\Models\User;
use App\Models\UserClubInterest;
use App\PusherEvents\AdminMessage;
use App\PusherEvents\AdminMessageClubMembers;
use Illuminate\Http\Request;

class NotificationsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $notifications = Notifications::with([
            'challenge:challenge_id,is_team_challenge,is_trackable',
            'activityFeed:feed_id,title,is_announcement,is_official,feed_type',
        ])
            ->where('user_id', auth()->user()->user_id)
            ->whereDoesntHave('activityFeed.flags')
            ->orderByDesc('scheduled_at')
            ->orderByDesc('created_at');
        return $notifications->simplePaginate(20);
    }

    public function messageDirectToUser(Request $request)
    {
        if (auth()->user()->privilege == "moderator") {
            $notif = new Notifications();
            $notif->title = $request->title;
            $notif->message = $request->message;
            $notif->deep_link = $request->deep_link ?: '';
            $notif->user_id = $request->user_id;
            $notif->save();

            event(new AdminMessage(
                $notif->notification_id,
                $notif->title,
                $notif->message,
                (int) $request->user_id,
                $request->deep_link
            ));

            $tokens = FCMNotification::where('user_id', $request->user_id)
                ->pluck('fcm_token')
                ->all();
            $fcm = new FCMNotificationController();
            $fcm->sendNotification(
                $tokens,
                $notif->title,
                $notif->message,
                ["url" => $notif->deep_link]
            );
            return response(null, 200);
        }
        abort(400);
    }

    public function messageAllUsers(Request $request)
    {
        if (auth()->user()->privilege == "moderator") {
            $users = User::where("is_verified", 1)->get();
            foreach ($users as $user) {
                $notif = new Notifications();
                $notif->title = $request->title;
                $notif->message = $request->message;
                $notif->deep_link = $request->deep_link ?: '';
                $notif->user_id = (int) $user->user_id;
                $notif->save();

                event(new AdminMessage(
                    $notif->notification_id,
                    $notif->title,
                    $notif->message,
                    (int)$user->user_id,
                    $request->deep_link
                ));
            }

            $fcm = new FCMNotificationController();
            $fcm->sendNotificationTopic(
                env('APP_ENV') == 'production' ? "message_all_users" : "message_all_staging_users",
                $notif->title,
                $notif->message,
                ["url" => $notif->deep_link]
            );
            return response(["users_count" => count($users)], 200);
        }
        abort(400);
    }

    public function messageToClubMembers(Request $request)
    {
        if (auth()->user()->privilege == "moderator") {
            $users = UserClubInterest::where("interest_id", $request->interest_id)->get();
            foreach ($users as $user) {
                $notif = new Notifications();
                $notif->title = $request->title;
                $notif->message = $request->message;
                $notif->user_id = (int)$user->user_id;
                $notif->interest_id = (int)$request->interest_id;
                $notif->deep_link = $request->deep_link ?: '';
                $notif->save();

                event(new AdminMessageClubMembers(
                    $notif->notification_id,
                    $notif->title,
                    $notif->message,
                    (int)$user->user_id,
                    $request->deep_link,
                    (int)$request->interest_id
                ));
            }

            $tokens = FCMNotification::whereIn('user_id', $users->pluck('user_id'))
                ->pluck('fcm_token')
                ->all();
            $fcm = new FCMNotificationController();
            $fcm->sendNotification(
                $tokens,
                $notif->title,
                $notif->message,
                ["url" => $notif->deep_link]
            );
            return response(["users_count" => count($users)], 200);
        }
        abort(400);
    }


    public function lastestNotification(Request $request)
    {
        $notifications = Notifications::where('user_id', $request->user_id)
            ->orderByDesc('scheduled_at')
            ->orderByDesc('created_at');
        return [
            "latest" => $notifications->first()
        ];
    }
}
