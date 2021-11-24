<?php

namespace App\Http\Controllers;

use App\Models\FCMNotification;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\Messaging;

class FCMNotificationController extends Controller
{


    public function registerToken($token)
    {
        $user_id = auth()->user()->user_id;

        $existingToken = FCMNotification::where('fcm_token', $token)->first();
        if (is_null($existingToken)) {
            $newFcm = new FCMNotification();
            $newFcm->user_id = $user_id;
            $newFcm->fcm_token = $token;
            $newFcm->save();
        } else {
            if ($existingToken->user_id != $user_id) {
                $existingToken->delete();
                $newFcm = new FCMNotification();
                $newFcm->user_id = $user_id;
                $newFcm->fcm_token = $token;
                $newFcm->save();
            }
        }
    }


    public function deleteToken($token)
    {
        $user_id = auth()->user()->user_id;
        $existingToken = FCMNotification::where('fcm_token', $token)
            ->where('user_id', $user_id)
            ->first();
        if (!is_null($existingToken)) {
            $existingToken->delete();
        }
    }



    public function sendNotification(
        $tokens,
        $title = '',
        $body = '',
        $data = []
    ) {
        if (count($tokens) > 0) {
            // $data = ['url' => 'livetogive://www.livetogive.co/settings'];
            $messaging = app('firebase.messaging');
            $verifyTokens = $messaging->validateRegistrationTokens($tokens);
            $validTokens = $verifyTokens['valid'];
            if (count($validTokens) > 0) {
                $message = CloudMessage::new()
                    ->withNotification(Notification::create($title, $body))
                    ->withData($data);
                // ->withImageUrl('http://lorempixel.com/200/400/');
                $messaging->sendMulticast($message, $validTokens);
            }
        }
    }

    public function sendNotificationTopic(
        $topic = 'message_all_users',
        $title = '',
        $body = '',
        $data = []
    ) {
        // $data = ['url' => 'livetogive://www.livetogive.co/settings'];
        $messaging = app('firebase.messaging');
        $message = CloudMessage::withTarget('topic', $topic)
            ->withNotification(Notification::create($title, $body))
            ->withData($data);

        $messaging->send($message);
    }
}
