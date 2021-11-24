<?php

namespace App\Http\Controllers;

use App\Helpers\CollectionHelper;
use App\Models\ActivityFeed;
use App\Models\ClubInterest;
use App\Models\FavoriteUsers;
use App\Models\UserClubInterest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ClubInterestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $id = auth()->user()->privilege == "moderator" ? $request->user_id : auth()->user()->user_id;

        $clubInterests = ClubInterest::with(['club'])
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

        return $clubInterests;
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
        // update record if interest_id is present
        if ($request->interest_id) {
            return $this->update($request, $request->interest_id);
        }

        // create new record if no interest_id provided
        $clubInterest = new ClubInterest();
        $clubInterest->interest_name = $request->interest_name;
        $clubInterest->club_id = $request->club_id;
        $clubInterest->icon_name = $request->icon_name;
        $clubInterest->description = $request->description;
        $clubInterest->html_content = $request->html_content;
        $clubInterest->save();


        if ($request->hasFile('image_cover')) {
            if ($request->file('image_cover')->isValid()) {
                $validator = Validator::make($request->all(), [
                    'image_cover' => 'mimes:jpg,jpeg,png|max:10240'
                ], [
                    'image_cover.mimes' => 'Only jpeg, png, and jpg images are allowed',
                    'image_cover.max' => 'Sorry! Maximum allowed size for an image is 10MB',
                ]);

                if (!$validator->fails()) {
                    $randomHex1 = bin2hex(random_bytes(6));
                    $randomHex2 = bin2hex(random_bytes(6));
                    $uploadDate = now()->year . "-" . now()->month . "-" . now()->day;
                    $extension = $request->image_cover->extension();
                    $newFileName = $uploadDate . '-' . $randomHex1 . '-' . $randomHex2 . '.'  . $extension;
                    $request->image_cover->storeAs('/public/images/club-interest/' . $clubInterest->interest_id, $newFileName);
                    $clubInterest->update(["image_cover" => $newFileName]);
                } else {
                    return response(["error" => ["image" => $validator->errors()->get('image_cover')]], 400);
                }
            }
        }

        return ["data" => $clubInterest];
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ClubInterest  $clubInterest
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $activityFeed = ActivityFeed::whereNull('deleted_at')
            ->whereNotNull('published_at')
            ->whereDoesntHave('flags');

        $activityFeed = $activityFeed->where('interest_id', (int) $id)
            ->orWhereHas('challenge', function ($query) use ($id) {
                $query->where('interest_id', (int) $id)
                    ->whereNotNull('published_at');
            })->orWhereHas('liveSession', function ($query) use ($id) {
                $query->where('interest_id', (int) $id)
                    ->whereNotNull('published_at');
            })->orWhereHas('meetup', function ($query) use ($id) {
                $query->where('interest_id', (int) $id)
                    ->whereNotNull('published_at');
            });

        $clubInterest = ClubInterest::withCount([
            'members as is_club_member' =>  function ($query) {
                $query->where('user_club_interests.user_id', auth()->user()->user_id);
            },
            'getRelatedChallenges as related_challenges_count',
            'getRelatedLiveSessions as related_live_sessions_count',
            'getRelatedMeetups as related_meetups_count',
            'participatedChallenges as challenges_done_count' => function ($query) {
                $query->where('challenge_participants.user_id', auth()->user()->user_id)->where('status', 'DONE');
            },
            'participatedMeetups as meetups_done_count' => function ($query) {
                $query->where('meetup_participants.user_id', auth()->user()->user_id)->where('status', 'DONE');
            },
            'participatedLiveSessions as live_session_done_count' => function ($query) {
                $query->where('live_session_participants.user_id', auth()->user()->user_id)->where('status', 'DONE');
            },
        ])
            ->find($id);

        $clubInterest->members_count = UserClubInterest::with(['user'])
            ->where('interest_id', $id)
            ->whereHas('user',  function ($query) {
                $query
                    ->where('privilege', 'user')
                    ->where('is_verified', 1);
            })
            ->get()
            ->count();
        $clubInterest->related_posts_count = $activityFeed->count();
        return ["data" => $clubInterest];
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ClubInterest  $clubInterest
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
     * @param  \App\Models\ClubInterest  $clubInterest
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $fieldsToUpdate = $request->only([
            'club_id',
            'interest_name',
            'icon_name',
            'description',
            'html_content',
        ]);

        $clubInterest =  ClubInterest::where('interest_id', $id);
        $clubInterest->update($fieldsToUpdate);
        if ($request->hasFile('image_cover')) {
            if ($request->file('image_cover')->isValid()) {
                $validator = Validator::make($request->all(), [
                    'image_cover' => 'mimes:jpg,jpeg,png|max:10240'
                ], [
                    'image_cover.mimes' => 'Only jpeg, png, and jpg images are allowed',
                    'image_cover.max' => 'Sorry! Maximum allowed size for an image is 10MB',
                ]);

                if (!$validator->fails()) {
                    $randomHex1 = bin2hex(random_bytes(6));
                    $randomHex2 = bin2hex(random_bytes(6));
                    $uploadDate = now()->year . "-" . now()->month . "-" . now()->day;
                    $extension = $request->image_cover->extension();
                    $newFileName = $uploadDate . '-' . $randomHex1 . '-' . $randomHex2 . '.'  . $extension;
                    $request->image_cover->storeAs('/public/images/club-interest/' . $id, $newFileName);
                    $clubInterest->update(["image_cover" => $newFileName]);
                } else {
                    return response(["error" => ["image" => $validator->errors()->get('image_cover')]], 400);
                }
            }
        }

        return ["data" => $clubInterest ? "Changed club data successfully." : "No changes made."];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ClubInterest  $clubInterest
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
        $clubInterest =  ClubInterest::findOrFail($id)->delete();
        if ($clubInterest)
            return ["deleted" => $clubInterest];
        return ["error" => "No club interest found."];
    }


    public function joinOrLeaveClub(Request $request)
    {
        $isParticipant = UserClubInterest::where('user_id', auth()->user()->user_id)
            ->where('interest_id', $request->interest_id);

        if ($request->status == "join") {
            if (!$isParticipant->first()) {
                $userClubInterest = new UserClubInterest();
                $userClubInterest->user_id = auth()->user()->user_id;
                $userClubInterest->interest_id = $request->interest_id;
                $userClubInterest->save();
                return ["data" => "User joined the club."];
            }
        } else if ($request->status == "leave") {
            $isParticipant->delete();
            return ["data" => "User left the club."];
        }

        return ["error" => $isParticipant ? "User is member of this club." : "Status provided is not valid."];
    }

    public function clubParticipants(Request $request, $id)
    {

        $user_id = auth()->user()->user_id;
        $per_page = !is_null($request->per_page) ? (int) $request->per_page : 50;
        $clubParticipants = UserClubInterest::join('users', 'users.user_id', '=', 'user_club_interests.user_id')
            ->orderBy('users.first_name', 'asc')
            ->orderBy('users.last_name', 'asc')
            ->select([
                'user_club_interests.user_id',
                'user_club_interests.interest_id',
                'user_club_interests.created_at'
            ])
            ->with(['user' => function ($query) use ($user_id) {
                $query
                    ->withCount(['favoriteUsers as is_favorite' => function ($query) use ($user_id) {
                        $query->where('user_id', $user_id);
                    }])
                    ->withSum(['bcoinTotal' => function ($query) {
                        $query->where('amount', '>', 0);
                    }], 'amount');
            }])
            ->where('users.privilege', 'user')
            ->where('users.is_verified', 1)
            ->where('interest_id', intVal($id))
            ->get();

        // get total # of club participants
        $clubParticipantsCount = count($clubParticipants);

        $alreadyFavoriteInClub =  $clubParticipants
            ->filter(function ($user) {
                return $user->user->is_favorite == 1;
            });

        $extraFavorites = FavoriteUsers::with(['favoriteUser' => function ($query) use ($user_id) {
            $query
                ->withCount(['favoriteUsers as is_favorite' => function ($query) use ($user_id) {
                    $query->where('user_id', $user_id);
                }])
                ->withSum(['bcoinTotal' => function ($query) {
                    $query->where('amount', '>', 0);
                }], 'amount');
        }])
            ->where('user_id', $user_id)
            ->whereNotIn('favorite_user_id', $alreadyFavoriteInClub->pluck('user_id'))
            ->get()
            ->toArray();

        $tempFave = [];

        foreach ($extraFavorites as $item) {
            array_push($tempFave, [
                "interest_id" => null,
                "user_id" => $item['favorite_user_id'],
                "user" => $item['favorite_user'],
                "is_ghost" => 1,
            ]);
        }

        $favoriteUsers = collect(array_merge(
            $alreadyFavoriteInClub->sortBy([
                ['user.first_name', 'asc'],
                ['user.last_name', 'asc']
            ])->toArray(),
            collect($tempFave)->sortBy([
                ['user.first_name', 'asc'],
                ['user.last_name', 'asc']
            ])->toArray()
        ));

        if (!is_null($request->search)) {
            $clubParticipants = $clubParticipants->filter(function ($user) use ($request) {
                $found = false;
                $keywords = explode(" ", $request->search);
                $user_name = strtolower($user->user->first_name . " " . $user->user->last_name);

                // uncomment if want to include country in search
                // $user_name = strtolower(
                //     $user->user->first_name . " " .
                //         $user->user->last_name . " " .
                //         $user->user->country_code
                // );
                foreach ($keywords as $keyword) {
                    // check each word from keywords if it exists in the $user_name
                    if (strpos($user_name, strtolower($keyword)) !== false) {
                        // break the loop if keyword found a match
                        $found = true;
                        break;
                    }
                }
                return $found;
            });
        }

        $participantsCount = collect([
            'participants_count' => $clubParticipantsCount,
            'favorite_users' => $favoriteUsers->values(),
        ]);
        return  $participantsCount->merge(CollectionHelper::paginate($clubParticipants, $per_page));
    }
}
