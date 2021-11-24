<?php

use App\Http\Controllers\ActivityFeedCommentController;
use App\Http\Controllers\ActivityFeedController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BcoinLogController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\ChallengeTeamController;
use App\Http\Controllers\CharityController;
use App\Http\Controllers\CharityResponseController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\ClubInterestController;
use App\Http\Controllers\FCMNotificationController;
use App\Http\Controllers\FormsController;
use App\Http\Controllers\LiveSessionController;
use App\Http\Controllers\MeetupController;
use App\Http\Controllers\NotificationBellLogsController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\OneTimePinController;
use App\Http\Controllers\PollController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\UserAccessLogController;
use App\Http\Controllers\UserCheckInController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EmailWhitelistController;
use App\Http\Controllers\CMSAllPostsController;
use App\Http\Controllers\CMSAllUsersController;
use App\Http\Controllers\CMSAllCommentsController;
use App\Http\Controllers\CMSAllOfficialPostController;
use App\Http\Controllers\CMSAllChallengesController;
use App\Http\Controllers\CMSAllLiveSessionsController;
use App\Http\Controllers\CMSAllMeetupsController;
use App\Http\Controllers\CMSAnalyticsReportController;
use App\Http\Controllers\CMSAllPollsController;
use App\Http\Controllers\CMSAllTeamsController;
use App\Http\Controllers\FavoriteUsersController;
use App\Models\OAuthTokens;
use Carbon\Carbon;

Route::group(['scheme' => 'https'], function () {

    // public route
    Route::post('signup', [AuthController::class, 'register'])->name('register');
    Route::post('login', [AuthController::class, 'authenticate'])->name('login')->middleware('throttle:login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('otp-verify', [OneTimePinController::class, 'verifyOtp']);
    // Route::post('otp-verify', [OneTimePinController::class, 'verifyOtp'])->middleware('throttle:otp');
    Route::post('otp-new', [OneTimePinController::class, 'newOtp'])->middleware('throttle:otp');

    // public route for CRON job
    Route::get('challenge/ending-reminder-3-days', [ChallengeController::class, 'endingReminder3Days']);
    Route::get('challenge/ended-reminder', [ChallengeController::class, 'endedReminderToday']);
    Route::get('live-session/starting-reminder/1hr', [LiveSessionController::class, 'startingReminder']);
    Route::get('live-session/starting-reminder/24hr', [LiveSessionController::class, 'startingReminder24Hr']);
    Route::get('meetup/starting-reminder/1hr', [MeetupController::class, 'startingReminder']);
    Route::get('meetup/starting-reminder/24hr', [MeetupController::class, 'startingReminder24Hr']);
    // Route::get('live-session/event-ended', [LiveSessionController::class, 'eventEnded']);
    Route::get('meetup/event-ended', [MeetupController::class, 'eventEnded']);
    Route::get('challenge/event-ended', [ChallengeController::class, 'eventEnded']);
    Route::get('challenge/event-ended-sync-reminder', [ChallengeController::class, 'eventEndedSyncReminder']);
    Route::get('user-check-in-streak-v2', [UserCheckInController::class, 'checkInStreak']);
    Route::post('form/public-contact-form', [FormsController::class, 'publicContactForm']);
    Route::get('all-officials/schedule-official-posts', [CMSAllOfficialPostController::class, 'scheduledPosts']);
    Route::get('all-live-sessions/schedule-live-sessions', [CMSAllLiveSessionsController::class, 'scheduleLiveSession']);
    Route::get('check-charity-notifications', [CharityResponseController::class, 'checkCharityNotification']);
    Route::get('revoke-all-tokens', function () {
        if (now()->timezone('Asia/Hong_Kong')->format('Y-m-d H:i') >= "2021-07-24 02:00") {
            return OAuthTokens::whereNotNull('user_id')->update(["revoked" => 1]);
        }
    });

    Route::get('check-system-maintenance', function () {
        $status = false;
        $now = now()->timezone('Asia/Hong_Kong');
        $status = $now->isAfter(Carbon::parse(Carbon::create(2021, 7, 23, 18, 0, 0), 'Asia/Hong_Kong'));
        return response(["status" => $status], 200);
    });
    Route::middleware('auth:api')->group(function () {
        Route::put('change-password/{id}', [AuthController::class, 'changePassword']);
        Route::post('new-password', [AuthController::class, 'newPassword']);
        Route::delete('logout', [AuthController::class, 'logout']);

        Route::resource('user', UserController::class);
        Route::resource('user-access-log', UserAccessLogController::class);
        Route::resource('user-check-in-v2', UserCheckInController::class);

        Route::resource('club', ClubController::class);
        Route::resource('club-interest', ClubInterestController::class);
        Route::get('club-interest/participants/{id}', [ClubInterestController::class, 'clubParticipants']);
        Route::post('user-club-interest-participate', [ClubInterestController::class, 'joinOrLeaveClub']);

        Route::resource('live-session', LiveSessionController::class);
        Route::get('live-session/participants/{id}', [LiveSessionController::class, 'liveSessionParticipants']);
        Route::put('live-session/participant/{id}/{status}', [LiveSessionController::class, 'joinOrLeaveLiveSession']);
        Route::post('live-session/join-room-link', [LiveSessionController::class, 'joinRoomLink']);

        Route::resource('meetup', MeetupController::class);
        Route::get('meetup/participants/{id}', [MeetupController::class, 'meetupParticipants']);
        Route::put('meetup/participant/{id}/{status}', [MeetupController::class, 'joinOrLeaveMeetup']);
        Route::post('meetup/join-room-link', [MeetupController::class, 'joinRoomLink']);

        Route::resource('challenge', ChallengeController::class);
        Route::get('challenge/participants/{id}', [ChallengeController::class, 'challengeParticipants']);
        Route::put('challenge/participant/{id}/{status}', [ChallengeController::class, 'joinOrLeaveChallenge']);
        Route::get('challenge/teams/{id}', [ChallengeController::class, 'challengeTeams']);

        Route::resource('challenge-team', ChallengeTeamController::class);
        Route::post('challenge-participate-team', [ChallengeTeamController::class, 'joinOrLeaveTeam']);

        Route::resource('activity-feed', ActivityFeedController::class);
        Route::resource('activity-feed-comment', ActivityFeedCommentController::class);
        Route::post('activity-feed-comment-flag', [ActivityFeedCommentController::class, 'feedFlag']);
        Route::post('activity-feed-like', [ActivityFeedController::class, 'feedLike']);
        Route::post('activity-feed-flag', [ActivityFeedController::class, 'feedFlag']);

        Route::resource('bcoins', BcoinLogController::class);
        Route::resource('charity', CharityController::class);
        Route::get('ranking', [RankingController::class, 'index']);
        Route::get('ranking/overall', [RankingController::class, 'getOverallRankByCountry']);
        // Route::get('ranking/overall/business-area', [RankingController::class, 'index']);

        Route::resource('notifications', NotificationsController::class);
        Route::resource('latest-notification', NotificationBellLogsController::class);
        Route::post('message-direct-user', [NotificationsController::class, 'messageDirectToUser']);
        Route::post('message-all-users', [NotificationsController::class, 'messageAllUsers']);
        Route::post('message-club-members', [NotificationsController::class, 'messageToClubMembers']);

        Route::get('test-user-active-challenges', [ChallengeTeamController::class, 'testActiveChallenges']);
        Route::get('user-active-challenges', [ChallengeTeamController::class, 'activeChallenges']);
        Route::post('sync-challenge-progress', [ChallengeTeamController::class, 'syncChallengeProgress']);
        Route::post('store-strava-progress', [ChallengeTeamController::class, 'storeStravaProgress']);
        Route::post('store-fitbit-progress', [ChallengeTeamController::class, 'storeFitbitProgress']);
        Route::post('store-google-progress', [ChallengeTeamController::class, 'storeGoogleFitProgress']);
        Route::post('store-apple-progress', [ChallengeTeamController::class, 'storeAppleHealthProgress']);

        Route::get('challenge-entry-list', [ChallengeController::class, 'listOfChallengeEntry']);

        Route::post('form/contact-form', [FormsController::class, 'contactForm']);
        Route::post('form/challenge-suggestion-form', [FormsController::class, 'challengeSuggestion']);
        Route::post('form/live-session-suggestion-form', [FormsController::class, 'liveSessionSuggestion']);
        Route::post('form/meetup-suggestion-form', [FormsController::class, 'meetupSuggestion']);

        Route::get('fcm-token/{token}', [FCMNotificationController::class, 'registerToken']);
        Route::delete('fcm-token/{token}', [FCMNotificationController::class, 'deleteToken']);
        Route::resource('whitelist', EmailWhitelistController::class);
        Route::resource('favorite-users', FavoriteUsersController::class);

        Route::resource('poll', PollController::class);
        Route::post('store-poll-answer', [PollController::class, 'answerPoll']);
        Route::resource('check-charity-expiration', CharityResponseController::class);

        Route::prefix('admin')->group(function () {
            Route::get('get-report', [CMSAnalyticsReportController::class, 'index']);
            Route::get('get-top-feed-event-report', [CMSAnalyticsReportController::class, 'getTopFeedEventReport']);
            Route::get('get-challenge-info-report', [CMSAnalyticsReportController::class, 'getChallengeInfoReport']);

            Route::resource('all-users', CMSAllUsersController::class);
            Route::get('user-delete-photo/{id}', [CMSAllUsersController::class, 'deleteUserPhoto']);
            Route::get('users-get-comments', [CMSAllUsersController::class, 'getUserComments']);
            Route::get('users-get-feed-posts', [CMSAllUsersController::class, 'getUserFeedPosts']);
            Route::get('users-get-challenges', [CMSAllUsersController::class, 'getUserChallenges']);
            Route::get('users-get-live-sessions', [CMSAllUsersController::class, 'getUserLiveSessions']);
            Route::get('users-get-meetups', [CMSAllUsersController::class, 'getUserMeetups']);
            Route::get('users-get-history', [CMSAllUsersController::class, 'getUserHistory']);

            Route::resource('all-posts', CMSAllPostsController::class);
            Route::get('posts-get-comments', [CMSAllPostsController::class, 'getPostComments']);

            Route::resource('all-comments', CMSAllCommentsController::class);
            Route::get('comments-get-post', [CMSAllCommentsController::class, 'getPostDetails']);

            Route::resource('all-officials', CMSAllOfficialPostController::class);
            Route::post('all-officials/publish/{id}', [CMSAllOfficialPostController::class, 'publishPost']);
            Route::get('all-officials/publish/{id}', [CMSAllOfficialPostController::class, 'publishPost']);

            Route::resource('all-challenges', CMSAllChallengesController::class);
            Route::post('all-challenges/publish/{id}', [CMSAllChallengesController::class, 'publishChallenge']);
            Route::get('all-challenges/publish/{id}', [CMSAllChallengesController::class, 'publishChallenge']);
            Route::post('all-challenges/quit', [CMSAllChallengesController::class, 'quitChallenge']);

            Route::resource('all-live-sessions', CMSAllLiveSessionsController::class);
            Route::post('all-live-sessions/publish/{id}', [CMSAllLiveSessionsController::class, 'publishLiveSession']);
            Route::get('all-live-sessions/publish/{id}', [CMSAllLiveSessionsController::class, 'publishLiveSession']);

            Route::resource('all-meetups', CMSAllMeetupsController::class);
            Route::post('all-meetups/publish/{id}', [CMSAllMeetupsController::class, 'publishMeetup']);
            Route::get('all-meetups/publish/{id}', [CMSAllMeetupsController::class, 'publishMeetup']);

            Route::resource('all-teams', CMSAllTeamsController::class);
            Route::post('all-teams/challenge-team', [CMSAllTeamsController::class, 'removeMember']);

            Route::resource('all-polls', CMSAllPollsController::class);
            Route::get('polls/respondent/{id}', [CMSAllPollsController::class, 'pollRespondents']);
            Route::post('all-polls/publish/{id}', [CMSAllPollsController::class, 'publishPoll']);
            Route::get('all-charities-response', [CharityResponseController::class, 'getCharityResponse']);
        });
    });
});



// LIST OF CRON JOBS AND TRIGGER
// 0  9  *  *  *       curl https://staging-app.livetogive.co/api/challenge/ending-reminder-3-days #CloudwaysApps
// 0  9  *  *  *       curl https://staging-app.livetogive.co/api/challenge/ended-reminder #CloudwaysApps
// *  *  *  *  *       curl https://staging-app.livetogive.co/api/live-session/starting-reminder/1hr #CloudwaysApps
// 0  9  *  *  *       curl https://staging-app.livetogive.co/api/live-session/starting-reminder/24hr #CloudwaysApps
// *  *  *  *  *       curl https://staging-app.livetogive.co/api/meetup/starting-reminder/1hr #CloudwaysApps
// 0  9  *  *  *       curl https://staging-app.livetogive.co/api/meetup/starting-reminder/24hr #CloudwaysApps
// *  *  *  *  *       curl https://staging-app.livetogive.co/api/meetup/event-ended #CloudwaysApps
// *  *  *  *  *       curl https://staging-app.livetogive.co/api/challenge/event-ended #CloudwaysApps
// *  *  *  *  *       curl https://staging-app.livetogive.co/api/challenge/event-ended-sync-reminder #CloudwaysApps

// DELETE FOR 1.2.0
// 30  2  *  *  *       curl https://staging-app.livetogive.co/api/user-check-in-streak-v2 #CloudwaysApps

// *  *  *  *  *       curl https://staging-app.livetogive.co/api/all-officials/schedule-official-posts #CloudwaysApps
// *  *  *  *  *       curl https://staging-app.livetogive.co/api/all-live-sessions/schedule-live-sessions #CloudwaysApps
// *  *  *  *  *       curl https://staging-app.livetogive.co/api/check-charity-notifications #CloudwaysApps
// *  *  *  *  *       curl https://staging-app.livetogive.co/api/revoke-all-tokens #CloudwaysApps