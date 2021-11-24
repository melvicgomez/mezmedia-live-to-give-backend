<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\UserAccessLog;

class UserAccessLogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
        $userLog = new UserAccessLog;
        $userLog->user_id = $request->user_id;
        $userLog->description = $request->description;
        $userLog->save();
        return  $request;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\UserAccessLog  $userAccessLog
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        //
        $perPage = $request->per_page;
        $userLogs = UserAccessLog::where('user_id', $id)->simplePaginate($perPage);
        return $userLogs;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\UserAccessLog  $userAccessLog
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
     * @param  \App\Models\UserAccessLog  $userAccessLog
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\UserAccessLog  $userAccessLog
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
