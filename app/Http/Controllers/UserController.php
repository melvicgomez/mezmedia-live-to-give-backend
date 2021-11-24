<?php

namespace App\Http\Controllers;

use App\Models\ClubInterest;
use App\Models\User;
use App\Models\UserClubInterest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $per_page = isset($request->per_page) ? $request->per_page : 10;
        $users = User::withSum(['bcoinTotal' => function ($query) {
            $query->where('amount', '>', 0);
        }], 'amount')
            ->where(function ($query) use ($request) {
                if (isset($request->is_verified))
                    $query->where('is_verified', intval($request->is_verified));
                if (isset($request->business_area))
                    $query->where('business_area', $request->business_area);
            })
            ->simplePaginate($per_page);

        return $users;
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
        $fieldsToUpdate = $request->only(
            "first_name",
            "last_name",
            "description",
            "email",
            "is_verified",
            "business_area",
            "country_code",
            "privilege",
            "tutorial_mobile_done",
            "tutorial_web_done",
        );

        $user = User::with(['userInterests.interest'])
            ->withSum(['bcoinTotal' => function ($query) {
                $query->where('amount', '>', 0);
            }], 'amount')
            ->where('user_id', $request->user_id);

        if (!is_null($user)) {
            $user->update($fieldsToUpdate);
            if ($request->community_guidelines == 1) {
                $user->update(["community_guidelines" => now()]);
            }
            if ($request->hasFile('photo_url')) {
                if ($request->file('photo_url')->isValid()) {
                    $validator = Validator::make($request->all(), [
                        'photo_url' => 'mimes:jpg,jpeg,png|max:10240'
                    ], [
                        'photo_url.mimes' => 'Only jpeg, png, and jpg images are allowed',
                        'photo_url.max' => 'Sorry! Maximum allowed size for an image is 10MB',
                    ]);
                    if (!$validator->fails()) {
                        $randomHex1 = bin2hex(random_bytes(6));
                        $randomHex2 = bin2hex(random_bytes(6));
                        $uploadDate = now()->year . "-" . now()->month . "-" . now()->day;
                        $extension = $request->photo_url->extension();
                        $newFileName = $uploadDate . '-' . $randomHex1 . '-' . $randomHex2 . '.'  . $extension;
                        $request->photo_url->storeAs('/public/images/user-profile/' . $request->user_id, $newFileName);
                        $user->update(["photo_url" => $newFileName]);
                    } else {
                        return response(["error" => ["image" => $validator->errors()->get('photo_url')]], 400);
                    }
                }
            }

            if (isset($request->interests)) {
                UserClubInterest::where('user_id', auth()->user()->user_id)->delete();
                foreach ($request->interests as $interest) {
                    $newUserInterests = new UserClubInterest();
                    $newUserInterests->user_id = auth()->user()->user_id;
                    $newUserInterests->interest_id = $interest;
                    $newUserInterests->save();
                }
            }
        }
        return response()->json(["data" => ["user" => $user->first()]]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {

        if (auth()->user()->privilege == "moderator" || (int)$id == auth()->user()->user_id) {
            $user = User::withSum(['bcoinTotal' => function ($query) {
                $query->where('amount', '>', 0);
            }], 'amount')
                ->where('user_id', $id)
                ->first();

            $clubInterests = ClubInterest::select(['interest_id', 'club_id', 'interest_name'])
                ->with(['club:club_id,club_name'])
                ->withCount([
                    'members',
                    'members as is_club_member' =>  function ($query) use ($id) {
                        $query->where('user_club_interests.user_id', $id);
                    },
                    'participatedChallenges as challenges_done_count' => function ($query) use ($id) {
                        $query->where('challenge_participants.user_id', $id)->where('status', 'DONE');
                    },
                    'participatedMeetups as meetups_done_count' => function ($query) use ($id) {
                        $query->where('meetup_participants.user_id', $id)->where('status', 'DONE');
                    },
                    'participatedLiveSessions as live_session_done_count' => function ($query) use ($id) {
                        $query->where('live_session_participants.user_id', $id)->where('status', 'DONE');
                    },
                ])
                ->get()
                ->groupBy('club.club_name')
                ->toArray();
            return response()->json(['data' => ["user" => $user, "interests" =>  $clubInterests]]);
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
        // $id to suspend
        if (auth()->user()->privilege == "moderator") {
            $user = User::where('user_id', $id)->first();
            if (!is_null($user)) {
                // get all user's tokens
                $userTokens = $user->tokens;
                foreach ($userTokens as $token) {
                    // revoke each token
                    $token->revoke();
                }

                // update user's password to NULL
                $user->update([
                    // 'password' => NULL,
                    'privilege' => 'suspended',
                    'is_verified' => 0,
                ]);

                return response(null, 204);
            }
        }
        abort(400);
    }
}
