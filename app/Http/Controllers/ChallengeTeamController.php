<?php

namespace App\Http\Controllers;

use App\Models\AppleRecord;
use App\Models\Challenge;
use App\Models\ChallengeParticipant;
use App\Models\ChallengeParticipantProgress;
use App\Models\ChallengeTeam;
use App\Models\FCMNotification;
use App\Models\Notifications;
use App\Models\StravaRecord;
use App\Models\FitbitRecord;
use App\Models\GoogleFitRecord;
use App\Models\User;
use Carbon\Carbon;
use App\PusherEvents\ChallengeReminder;
use Illuminate\Http\Request;

class ChallengeTeamController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $per_page = !is_null($request->per_page) ? (int) $request->per_page : 10;
        $activityFeed = ChallengeTeam::with('participants')->whereNull('deleted_at');

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
        if ($request->team_id) {
            return $this->update($request, $request->team_id);
        }

        $authController = new AuthController();

        do {
            $teamCode = $authController->generateRandomString(5);
            $isTeamCodeAvailable = ChallengeTeam::where('team_code', $teamCode)
                ->where('challenge_id', $teamCode)
                ->count();
        } while ($isTeamCodeAvailable > 0);


        $teamFound = ChallengeTeam::where('team_name', $request->team_name)->where('challenge_id', $request->challenge_id)->get()->count();
        if ($teamFound == 0) {
            $challengeTeam = new ChallengeTeam();
            $challengeTeam->user_id = auth()->user()->user_id;
            $challengeTeam->challenge_id = $request->challenge_id;
            $challengeTeam->team_name = $request->team_name;
            $challengeTeam->is_private = $request->is_private ?: 0;
            $challengeTeam->team_code = $teamCode;
            $challengeTeam->save();


            if (!is_null($challengeTeam->team_id)) {
                $myRequest = new Request();
                $myRequest->team_code = $teamCode;
                $myRequest->challenge_id = $request->challenge_id;
                $myRequest->user_id = auth()->user()->user_id;
                $myRequest->status = "join";
                $this->joinOrLeaveTeam($myRequest);
            }

            $challengeTeam->participants;
            return ["data" => ["team" => $challengeTeam]];
        } else {
            return response(["error" => "Team already exists in this challenge."], 400);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ChallengeTeam  $challengeTeam
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $team = ChallengeTeam::find($id);
        $team->participants;
        if ($team)
            return ["data" => ["team" => $team]];
        return response()->json(["error" => "No team found to delete."], 400);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ChallengeTeam  $challengeTeam
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
     * @param  \App\Models\ChallengeTeam  $challengeTeam
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $team = ChallengeTeam::find((int) $id);
        $toUpdate = $request->only(['team_name', 'challenge_id', 'user_id']);

        if ($team) {
            $team->update($toUpdate);
            if (isset($request->is_private))
                $team->update(['is_private' => (int) $request->is_private]);
            if ($request->get_new_code == 1) {
                $authController = new AuthController();
                do {
                    $teamCode = $authController->generateRandomString(5);
                    $isTeamCodeAvailable = ChallengeTeam::where('team_code', $teamCode)
                        ->where('challenge_id', $teamCode)
                        ->count();
                } while ($isTeamCodeAvailable > 0);
                $team->update(['team_code' => $teamCode]);
            }

            return ["data" => ["team" =>  $team]];
        }

        return response()->json(["error" => "No team found to delete."], 400);
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ChallengeTeam  $challengeTeam
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $team = ChallengeTeam::find($id);
        if ($team) {
            $team->delete();
            return ["data" => ["team" =>  $team]];
        }
        return response()->json(["error" => "No team found to delete."], 400);
    }


    public function joinOrLeaveTeam(Request $request)
    {
        $team = ChallengeTeam::where("team_code", $request->team_code)
            ->where("challenge_id", $request->challenge_id)
            ->first();

        if ($team) {
            // check count of members in that team
            $teamMembers = ChallengeParticipant::where('team_id', $team->team_id)
                ->whereHas('user', function ($query) {
                    $query
                        // ->where('users.privilege', 'user')
                        ->where('users.is_verified', 1);
                })
                ->get();
            if (count($teamMembers) < 5) {
                $challengeParticipant = ChallengeParticipant::where("challenge_id", $request->challenge_id)
                    ->where("user_id", auth()->user()->user_id);

                $challenge = Challenge::where('challenge_id', $request->challenge_id)->first();

                $user = User::where('user_id', $request->user_id)->first(); // user who joined
                foreach ($teamMembers as $participant) {
                    // NOTIFICATION RECORD
                    $notif = new Notifications();
                    $notif->user_id = $participant->user->user_id;
                    $notif->title = 'New Team Member';
                    $notif->message = $user->first_name . " " . $user->last_name .
                        " joined " .  $team->team_name . " in " . $challenge->title . ".";
                    $notif->challenge_id = $request->challenge_id;
                    $notif->deep_link = "team-challenge/" . $request->challenge_id;
                    $notif->save();

                    // NEW TEAM MEMBER JOINED
                    event(new ChallengeReminder(
                        $notif->notification_id,
                        $notif->title,
                        $notif->message,
                        $notif->user_id,
                        $notif->challenge_id,
                        1,
                        1
                    ));

                    $tokens = FCMNotification::where('user_id', $notif->user_id)
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

                $challengeParticipant->update(["team_id" =>  $request->status == "join" ? $team->team_id : 0]);
                return ["data" => $challengeParticipant->first()];
            } else {
                return response(["error" => "Current team is now full with 5 members."], 400);
            }
        } else
            return response()->json(["error" => "No team found."], 400);
    }



    public function activeChallenges(Request $request)
    {

        $user_id = auth()->user()->user_id;
        $tz = $request->tz ?: "+08:00";
        $userChallenges = ChallengeParticipant::select('participant_id', 'challenge_id', 'participated_at')
            ->with([
                'challenge:challenge_id,target_unit,type,started_at,registration_ended_at,ended_at',
                "lastSyncStrava" => function ($query) {
                    $query->select('progress_id', 'participant_id', "created_at")
                        ->withCasts([
                            'created_at' => 'datetime:Y-m-d H:i:s'
                        ]);
                },
                "lastSyncHealthKit" => function ($query) {
                    $query->select('progress_id', 'participant_id', "created_at")
                        ->withCasts([
                            'created_at' => 'datetime:Y-m-d H:i:s'
                        ]);
                },
                "lastSyncGoogleFit" => function ($query) {
                    $query->select('progress_id', 'participant_id', "created_at")
                        ->withCasts([
                            'created_at' => 'datetime:Y-m-d H:i:s'
                        ]);
                },
                "lastSyncFitbit" => function ($query) {
                    $query->select('progress_id', 'participant_id', "created_at")
                        ->withCasts([
                            'created_at' => 'datetime:Y-m-d H:i:s'
                        ]);
                }
            ])
            ->whereHas('challenge', function ($query) use ($tz) {
                $query
                    ->where('ended_at', '>=', now($tz)->subDays(2)->format('Y-m-d H:i'))
                    ->where('started_at', '<=', now($tz)->format('Y-m-d H:i'))
                    ->where('is_trackable', 1);
            })
            ->where('user_id', $user_id)->get();

        $tempStravaLastRec =  ChallengeParticipantProgress::whereHas('participant', function ($query) use ($user_id) {
            $query->where('user_id', $user_id)->whereHas('challenge',  function ($query) {
                $query->where('is_trackable', 1);
            });
        })->where('source', 'strava')
            ->orderBy('created_at', 'desc')->first();

        $tempGoogleFitLastRec =  ChallengeParticipantProgress::whereHas('participant', function ($query) use ($user_id) {
            $query->where('user_id', $user_id)->whereHas('challenge',  function ($query) {
                $query->where('is_trackable', 1);
            });
        })->where('source', 'google_fit')
            ->orderBy('created_at', 'desc')->first();

        $tempHealthkitLastRec =  ChallengeParticipantProgress::whereHas('participant', function ($query) use ($user_id) {
            $query->where('user_id', $user_id)->whereHas('challenge',  function ($query) {
                $query->where('is_trackable', 1);
            });
        })->where('source', 'healthkit')
            ->orderBy('created_at', 'desc')->first();

        $tempFitbitLastRec =  ChallengeParticipantProgress::whereHas('participant', function ($query) use ($user_id) {
            $query->where('user_id', $user_id)->whereHas('challenge',  function ($query) {
                $query->where('is_trackable', 1);
            });
        })->where('source', 'fitbit')
            ->orderBy('created_at', 'desc')->first();

        return response([
            "last_sync" => [
                "strava" =>  $tempStravaLastRec ? $tempStravaLastRec->created_at->toDateTimeString() : NULL,
                "google_fit" => $tempGoogleFitLastRec ? $tempGoogleFitLastRec->created_at->toDateTimeString() : NULL,
                "healthkit" => $tempHealthkitLastRec ? $tempHealthkitLastRec->created_at->toDateTimeString() : NULL,
                "fitbit" => $tempFitbitLastRec ? $tempFitbitLastRec->created_at->toDateTimeString() : NULL
            ],
            "challenges" => $userChallenges
        ]);
    }

    public function testActiveChallenges(Request $request)
    {

        $user_id = $request->user_id;
        $tz = $request->tz ?: "+08:00";
        $userChallenges = ChallengeParticipant::select('participant_id', 'challenge_id', 'participated_at')
            ->with([
                'challenge:challenge_id,target_unit,type,started_at,registration_ended_at,ended_at',
                "lastSyncStrava" => function ($query) {
                    $query->select('progress_id', 'participant_id', "created_at")
                        ->withCasts([
                            'created_at' => 'datetime:Y-m-d H:i:s'
                        ]);
                },
                "lastSyncHealthKit" => function ($query) {
                    $query->select('progress_id', 'participant_id', "created_at")
                        ->withCasts([
                            'created_at' => 'datetime:Y-m-d H:i:s'
                        ]);
                },
                "lastSyncGoogleFit" => function ($query) {
                    $query->select('progress_id', 'participant_id', "created_at")
                        ->withCasts([
                            'created_at' => 'datetime:Y-m-d H:i:s'
                        ]);
                },
                "lastSyncFitbit" => function ($query) {
                    $query->select('progress_id', 'participant_id', "created_at")
                        ->withCasts([
                            'created_at' => 'datetime:Y-m-d H:i:s'
                        ]);
                },
            ])
            ->whereHas('challenge', function ($query) use ($tz) {
                $query
                    ->where('ended_at', '>=', now($tz)->subDays(2)->format('Y-m-d H:i'))
                    ->where('started_at', '<=', now($tz)->format('Y-m-d H:i'))
                    ->where('is_trackable', 1);
            })
            ->where('user_id', $user_id)->get();

        $tempStravaLastRec =  ChallengeParticipantProgress::whereHas('participant', function ($query) use ($user_id) {
            $query->where('user_id', $user_id)->whereHas('challenge',  function ($query) {
                $query->where('is_trackable', 1);
            });
        })->where('source', 'strava')
            ->orderBy('created_at', 'desc')->first();

        $tempGoogleFitLastRec =  ChallengeParticipantProgress::whereHas('participant', function ($query) use ($user_id) {
            $query->where('user_id', $user_id)->whereHas('challenge',  function ($query) {
                $query->where('is_trackable', 1);
            });
        })->where('source', 'google_fit')
            ->orderBy('created_at', 'desc')->first();

        $tempHealthkitLastRec =  ChallengeParticipantProgress::whereHas('participant', function ($query) use ($user_id) {
            $query->where('user_id', $user_id)->whereHas('challenge',  function ($query) {
                $query->where('is_trackable', 1);
            });
        })->where('source', 'healthkit')
            ->orderBy('created_at', 'desc')->first();

        $tempFitbitLastRec =  ChallengeParticipantProgress::whereHas('participant', function ($query) use ($user_id) {
            $query->where('user_id', $user_id)->whereHas('challenge',  function ($query) {
                $query->where('is_trackable', 1);
            });
        })->where('source', 'fitbit')
            ->orderBy('created_at', 'desc')->first();

        return response([
            "last_sync" => [
                "strava" =>  $tempStravaLastRec ? $tempStravaLastRec->created_at->toDateTimeString() : NULL,
                "google_fit" => $tempGoogleFitLastRec ? $tempGoogleFitLastRec->created_at->toDateTimeString() : NULL,
                "healthkit" => $tempHealthkitLastRec ? $tempHealthkitLastRec->created_at->toDateTimeString() : NULL,
                "fitbit" =>  $tempFitbitLastRec ? $tempFitbitLastRec->created_at->toDateTimeString() : NULL,
            ],
            "challenges" => $userChallenges
        ]);
    }

    public function syncChallengeProgress(Request $request)
    {
        foreach ($request->progress as $progress) {
            $challengeInfo = ChallengeParticipant::where('participant_id', (int)$progress['participant_id'])
                ->first();
            if (!is_null($challengeInfo)) {
                $challengeProgress = new ChallengeParticipantProgress();
                $challengeProgress->participant_id =  $progress['participant_id'];
                $challengeProgress->source =  $progress['source'];
                if (isset($progress['type']))
                    $challengeProgress->type =  $progress['type'];
                if (isset($progress['is_manual']))
                    $challengeProgress->is_manual =  $progress['is_manual'];
                if (isset($progress['start_date']))
                    $challengeProgress->start_date = $progress['start_date'];
                if (isset($progress['end_date']))
                    $challengeProgress->end_date =  $progress['end_date'];
                if (isset($progress['activity_id']))
                    $challengeProgress->activity_id =  $progress['activity_id'];
                $target_unit = $challengeInfo->challenge->target_unit;
                if ($target_unit === 'distance') {
                    $challengeProgress->distance = (float)$progress['progress'];
                }
                if ($target_unit === 'calories') {
                    $challengeProgress->calories_burnt = (float)$progress['progress'];
                }
                if ($target_unit === 'duration') {
                    $challengeProgress->duration_activity = (float)$progress['progress'];
                }
                $challengeProgress->save();
            }
        }
    }

    public function storeStravaProgress(Request $request)
    {
        foreach ($request->records as $record) {
            $recordExist = StravaRecord::where('strava_id', $record['id'])->where('user_id', auth()->user()->user_id)
                ->first();

            if (is_null($recordExist)) {
                $stravaRecord = new StravaRecord();
                $stravaRecord->user_id = auth()->user()->user_id;
                $stravaRecord->strava_id =  $record['id'];
                $stravaRecord->type =  $record['type'];
                $stravaRecord->name =  $record['name'];
                $stravaRecord->manual =  $record['manual'] ? 1 : 0;
                $stravaRecord->distance = (float)$record['distance'];
                $stravaRecord->duration = (float)$record['elapsed_time'];
                $stravaRecord->calories =  isset($record['calories']) ? (float)$record['calories']  : 0;
                $stravaRecord->timezone = $record['timezone'];
                $stravaRecord->start_date = Carbon::parse($record['start_date'])->format('Y-m-d H:i');
                $stravaRecord->start_date_local = Carbon::parse($record['start_date_local'])->format('Y-m-d H:i');
                if (isset($record['external_id']))
                    $stravaRecord->external_id = $record['external_id'];

                $stravaRecord->save();
            }
        }
    }

    public function storeAppleHealthProgress(Request $request)
    {
        foreach ($request->records as $record) {
            $recordExist = AppleRecord::where('user_id', auth()->user()->user_id)
                ->where('start_date', Carbon::parse($record['start'])->setTimezone('UTC')->format('Y-m-d H:i'))
                ->where('end_date', Carbon::parse($record['end'])->setTimezone('UTC')->format('Y-m-d H:i'))
                ->where('type', $record['activityName'])
                ->first();

            if (is_null($recordExist)) {
                $appleRecord = new AppleRecord();
                $appleRecord->user_id = auth()->user()->user_id;
                if (isset($record['activityName']))
                    $appleRecord->type =  $record['activityName'];
                if (isset($record['tracked']))
                    $appleRecord->manual =  $record['tracked'] ? 0 : 1;
                $appleRecord->distance = isset($record['distance']) ? (float)$record['distance'] : 0;
                $appleRecord->duration = Carbon::parse($record['end'])->diffInMinutes(Carbon::parse($record['start'])) / 60;
                $appleRecord->calories =  isset($record['calories']) ? (float)$record['calories']  : 0;
                $appleRecord->start_date = Carbon::parse($record['start'])->setTimezone('UTC')->format('Y-m-d H:i');
                $appleRecord->start_date_local = Carbon::parse($record['start'])->format('Y-m-d H:i');
                $appleRecord->end_date = Carbon::parse($record['end'])->setTimezone('UTC')->format('Y-m-d H:i');

                $appleRecord->save();
            }
        }
    }

    public function storeGoogleFitProgress(Request $request)
    {
        foreach ($request->records as $record) {
            $recordExist = GoogleFitRecord::where('user_id', auth()->user()->user_id)
                ->where('start_date', Carbon::createFromTimestamp(intval((int)$record['start'] / 1000))->format('Y-m-d H:i'))
                ->where('end_date', Carbon::createFromTimestamp(intval((int)$record['end'] / 1000))->format('Y-m-d H:i'))
                ->where('type', $record['activityName'])
                ->first();

            if (is_null($recordExist)) {
                $googleRecord = new GoogleFitRecord();
                $googleRecord->user_id = auth()->user()->user_id;
                $googleRecord->type =  $record['activityName'];
                $googleRecord->manual =  $record['tracked'] ? 0 : 1;
                $googleRecord->distance = isset($record['distance']) ? (float)$record['distance'] : 0;
                $googleRecord->duration = Carbon::createFromTimestamp(intval((int)$record['end'] / 1000))->diffInMinutes(Carbon::createFromTimestamp(intval((int)$record['start'] / 1000))) / 60;
                $googleRecord->calories =  isset($record['calories']) ? (float)$record['calories']  : 0;
                $googleRecord->start_date = Carbon::createFromTimestamp(intval((int)$record['start'] / 1000))->format('Y-m-d H:i');
                $googleRecord->end_date = Carbon::createFromTimestamp(intval((int)$record['end'] / 1000))->format('Y-m-d H:i');

                $googleRecord->save();
            }
        }
    }

    public function storeFitbitProgress(Request $request)
    {
        // dd($request->records);
        foreach ($request->records as $record) {
            $recordExist = FitbitRecord::where('fitbit_id', $record['logId'])->where('user_id', auth()->user()->user_id)
                ->first();

            if (is_null($recordExist)) {
                $fitbitRecord = new FitbitRecord();
                $fitbitRecord->user_id = auth()->user()->user_id;
                $fitbitRecord->fitbit_id =  $record['logId'];
                $fitbitRecord->type =  $record['activityName'];
                $fitbitRecord->log_type =  $record['logType'];
                $fitbitRecord->distance = (float)$record['distance'];
                $fitbitRecord->duration = (float)$record['duration'];
                $fitbitRecord->calories =  isset($record['calories']) ? (float)$record['calories']  : 0;
                $fitbitRecord->start_date = Carbon::parse($record['startTime'])->setTimezone('UTC')->format('Y-m-d H:i');
                $fitbitRecord->start_date_local = Carbon::parse($record['startTime'])->format('Y-m-d H:i');

                $fitbitRecord->save();
            }
        }
    }
}
