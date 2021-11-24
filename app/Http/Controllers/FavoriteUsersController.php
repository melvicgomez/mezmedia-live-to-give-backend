<?php

namespace App\Http\Controllers;

use App\Models\FavoriteUsers;
use Illuminate\Http\Request;

class FavoriteUsersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $favorites = FavoriteUsers::with(['user', 'favoriteUser'])
            ->where('user_id', auth()->user()->user_id)
            ->get();
        return  $favorites;
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
        $user_id = auth()->user()->user_id;

        $favoritesListCount = FavoriteUsers::where('user_id',  $user_id)
            ->count();


        if (auth()->user()->privilege != "user") {
            // bypass user_id if moderator call this
            $user_id = $request->user_id ?: auth()->user()->user_id;
        }
        $favorite_user_id = $request->favorite_user_id;

        $favoriteAlready = FavoriteUsers::where('user_id', $user_id)
            ->where('favorite_user_id', $favorite_user_id)
            ->first();

        if ($user_id == $favorite_user_id) // abort if user_id and favorite_user_id same
            return response(["error" => "You cannot set yourself in favourites."], 422);

        if (is_null($favoriteAlready)) {
            if ($favoritesListCount >= 5)
                return response(["error" => "Favourites limit reached."], 422);

            // save favorite
            $favoriteUser = new FavoriteUsers();
            $favoriteUser->user_id = $user_id;
            $favoriteUser->favorite_user_id = $favorite_user_id;
            $favoriteUser->save();
            $favoriteUser->status = "favorite";
            return response($favoriteUser, 200);
        } else {
            // delete favorite
            $favoriteExists = FavoriteUsers::where('user_id', $user_id)
                ->where('favorite_user_id', $favorite_user_id)
                ->first();

            if (!is_null($favoriteExists)) {
                // check if favorite exists then unfavorite the user
                $favoriteExists->delete();
                $favoriteExists->status = "unfavorite";
                return response($favoriteExists, 200);
            } else {
                abort(400);
            }
        }

        return response(["error" => "Favourites limit reached."], 422);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\FavoriteUsers  $favoriteUsers
     * @return \Illuminate\Http\Response
     */
    public function show(FavoriteUsers $favoriteUsers)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\FavoriteUsers  $favoriteUsers
     * @return \Illuminate\Http\Response
     */
    public function edit(FavoriteUsers $favoriteUsers)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\FavoriteUsers  $favoriteUsers
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, FavoriteUsers $favoriteUsers)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\FavoriteUsers  $favoriteUsers
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, FavoriteUsers $favoriteUsers)
    {
        $user_id = auth()->user()->user_id;
        if (auth()->user()->privilege != "user") {
            // bypass user_id if moderator call this
            $user_id = $request->user_id ?: auth()->user()->user_id;
        }
        $favorite_user_id = $request->favorite_user_id;

        $favoriteExists = $favoriteUsers
            ->where('user_id', $user_id)
            ->where('favorite_user_id', $favorite_user_id)
            ->first();

        if (is_null($favoriteExists)) // check if favorite exists
            abort(400);
    }
}
