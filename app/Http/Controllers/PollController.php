<?php

namespace App\Http\Controllers;

use App\Models\Poll;
use App\Models\PollUserResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PollController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $poll = Poll::where('started_at', '<=', now())
            ->where('ended_at', '>=', now())->whereNotNull('published_at')->first();

        if ($poll) {
            $userResponse = PollUserResponse::where('user_id', auth()->user()->user_id)
                ->where('poll_id', $poll->poll_id)->first();

            return ["poll" => $poll, 'user_response' => is_null($userResponse) ? 0 : 1];
        }

        return ["poll" => $poll];
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
        if ($request->poll_id) {
            // call update function
            return $this->update($request, $request->poll_id);
        }

        $poll = new Poll();
        $poll->user_id =  auth()->user()->user_id;
        $poll->title = $request->title;
        $poll->option_one = $request->option_one;
        $poll->option_two = $request->option_two;
        $poll->option_three = $request->option_three;
        $poll->option_four = $request->option_four;

        if (!is_null($request->started_at)) {
            $started_at = Carbon::createFromFormat('Y-m-d H:i', $request->started_at)
                ->tz('UTC');
            $poll->started_at = $started_at;
        }

        if (!is_null($request->ended_at)) {
            $ended_at = Carbon::createFromFormat('Y-m-d H:i', $request->ended_at)
                ->tz('UTC');
            $poll->ended_at = $ended_at;
        }

        $poll->save();

        if (!is_null($poll->poll_id))
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
                        $request->image_cover->storeAs('/public/images/poll/' . $poll->poll_id, $newFileName);
                        $poll->update(["image_cover" => $newFileName]);
                    } else {
                        return response(["error" => ["image" => $validator->errors()->get('image_cover')]], 400);
                    }
                }
            }

        return array_merge(["data" => $poll]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Poll  $poll
     * @return \Illuminate\Http\Response
     */
    public function show(Poll $poll)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Poll  $poll
     * @return \Illuminate\Http\Response
     */
    public function edit(Poll $poll)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Poll  $poll
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $fieldsToUpdate = $request->only([
            "user_id",
            "title",
            "option_one",
            "option_two",
            "option_three",
            "option_four",
        ]);

        $started_at = NULL;
        $ended_at = NULL;

        if (!is_null($request->started_at)) {
            $started_at = Carbon::parse($request->started_at)->format('Y-m-d H:i');
        }

        if (!is_null($request->ended_at)) {
            $ended_at = Carbon::parse($request->ended_at)->format('Y-m-d H:i');
        };

        $poll = Poll::where('poll_id', $id)->first();

        if (!is_null($poll)) {
            $poll->update(
                array_merge(
                    (array) $fieldsToUpdate,
                    (array) [
                        "started_at" => $started_at ? $started_at : $poll->started_at,
                        "ended_at" => $ended_at ? $ended_at : $poll->ended_at,
                    ]
                )
            );

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
                        $request->image_cover->storeAs('/public/images/poll/' .  $id, $newFileName);
                        $poll->update(["image_cover" => $newFileName]);
                    } else {
                        return response(["error" => ["image" => $validator->errors()->get('image_cover')]], 400);
                    }
                }
            }
        }
        return ["data" => $poll];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Poll  $poll
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $poll = Poll::find($id);

        if ($poll) {

            // delete all user response
            PollUserResponse::where('poll_id', $poll->poll_id)->delete();

            $poll->delete();

            return response()->json(["data" =>
            [
                "poll" => $poll
            ]]);
        }

        return response()->json(["data" =>
        [
            "poll" => "No poll deleted."
        ]]);
    }

    public function answerPoll(Request $request)
    {
        $poll = Poll::find($request->poll_id);

        $userResponse = PollUserResponse::where('poll_id', $request->poll_id)->where('user_id', auth()->user()->user_id)->first();

        if (is_null($userResponse)) {
            $pollResponse = new PollUserResponse();
            $pollResponse->user_id = auth()->user()->user_id;
            $pollResponse->poll_id = (int)$request->poll_id;
            $pollResponse->answer = (int)$request->answer;
            $pollResponse->save();
            return $pollResponse;
        }

        return ["error" => "User already answered this poll."];
    }
}
