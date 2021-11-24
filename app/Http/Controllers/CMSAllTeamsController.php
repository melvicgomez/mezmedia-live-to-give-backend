<?php

namespace App\Http\Controllers;

use App\Models\ChallengeTeam;
use App\Models\ChallengeParticipant;
use App\Models\ChallengeParticipantProgress;
use App\Models\ActivityFeed;
use Illuminate\Http\Request;

class CMSAllTeamsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (auth()->user()->privilege == "moderator") {
            // $activityFeed = ChallengeTeam::with(['participants.user', 'challenge'])->whereNull('deleted_at');
            $teams = ChallengeTeam::join('users', 'challenge_teams.user_id', '=', 'users.user_id')
                ->leftJoin('challenges', 'challenges.challenge_id', '=', 'challenge_teams.challenge_id')
                ->select(
                    'challenge_teams.*',
                    'users.first_name as leader_first_name',
                    'users.last_name as leader_last_name',
                    'challenges.title as challenge_name',
                )->with(['participants.user']);

            if (!is_null($request->search)) {
                $teams->where(function ($q) use ($request) {
                    $q->where('users.last_name', 'like', "%" . $request->search . "%")
                        ->orWhere('users.first_name', 'like', "%" . $request->search . "%")
                        ->orWhere('team_name', 'like', "%" . $request->search . "%")
                        ->orWhere('team_code', 'like', "%" . $request->search . "%")
                        ->orWhere('challenges.title', 'like', "%" . $request->search . "%");
                });
            }

            return $teams->paginate(200);
        };
        abort(400);
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
            $team = ChallengeTeam::with([
                'participants.user' => function ($query) {
                    $query->select('user_id', 'first_name', 'last_name', 'photo_url', 'user_id', 'user_id', 'country_code')
                        ->withSum(['bcoinTotal' => function ($query) {
                            $query->where('amount', '>', 0);
                        }], 'amount');
                },
                'challenge.clubInterest' => function ($query) {
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
                },
            ])->find($id);

            if ($team)
                return ["data" => $team];

            return response()->json(["error" => "No team found to delete."], 400);
        };

        abort(400);
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
        if (auth()->user()->privilege == "moderator") {

            $team = ChallengeTeam::find($id);
            if ($team) {
                $teamMembers = ChallengeParticipant::where('team_id', $team->team_id)->get();
                foreach ($teamMembers as $member) {
                    $member->update(["team_id" =>  0]);
                }
                $team->delete();
                return ["data" => ["team" =>  $team]];
            }
            return response()->json(["error" => "No team found to delete."], 400);
        };
        abort(400);
    }

    public function removeMember(Request $request)
    {
        if (auth()->user()->privilege == "moderator") {

            $member = ChallengeParticipant::where('team_id', $request->team_id)->where('user_id', $request->user_id);
            $member->update(["team_id" =>  0]);

            return response(["data" => "User is removed from team"], 200);
        };
        abort(400);
    }
}
