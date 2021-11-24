<?php

namespace App\Http\Controllers;

use App\Models\Poll;
use App\Models\PollUserResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class CMSAllPollsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (auth()->user()->privilege == "moderator") {

            $order_by = $request->order_by ?: 'poll_id'; // column name
            $sort_by = $request->sort_by ?: 'desc'; // asc | desc

            $polls = Poll::withCount([
                'responses',
            ])->whereNull('deleted_at');

            if (!is_null($request->search)) {
                $polls->where(function ($q) use ($request) {
                    $q->where('title', 'like', "%" . $request->search . "%")
                        ->orWhere('option_one', 'like', "%" . $request->search . "%")
                        ->orWhere('option_two', 'like', "%" . $request->search . "%")
                        ->orWhere('option_three', 'like', "%" . $request->search . "%")
                        ->orWhere('option_four', 'like', "%" . $request->search . "%");
                });
            }

            $polls->orderBy($order_by, $sort_by);

            return $polls->paginate(200);
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
        if (auth()->user()->privilege == "moderator") {
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
            $poll->started_at = $request->started_at;
            $poll->ended_at = $request->ended_at;

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
        };
        abort(400);
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
            $pollDetail = Poll::with([
                'responses'
            ])->withCount([
                'responses',
            ])->find($id);

            if ($pollDetail) {
                $pollDetail->option_one_res_count =  $pollDetail->responses->where('answer', 1)->count();
                $pollDetail->option_two_res_count =  $pollDetail->responses->where('answer', 2)->count();
                $pollDetail->option_three_res_count =  $pollDetail->responses->where('answer', 3)->count();
                $pollDetail->option_four_res_count =  $pollDetail->responses->where('answer', 4)->count();
                unset($pollDetail->responses);

                return ["data" => $pollDetail];
            }
            return ["error" => ["message" => "No poll found."]];
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
        if (auth()->user()->privilege == "moderator") {
            $fieldsToUpdate = $request->only([
                "user_id",
                "title",
                "option_one",
                "option_two",
                "option_three",
                "option_four",
                'started_at',
                'ended_at',
            ]);

            $poll = Poll::where('poll_id', $id)->first();

            if (!is_null($poll)) {
                $poll->update($fieldsToUpdate);

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
        };
        abort(400);
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
        };
        abort(400);
    }

    public function pollRespondents(Request $request, $id)
    {
        $per_page = !is_null($request->per_page) ? (int) $request->per_page : 50;

        $respondents = PollUserResponse::with([
            'user' => function ($query) {
                $query
                    ->select(
                        'user_id',
                        'first_name',
                        'last_name',
                        'photo_url',
                        'country_code',
                        'privilege',
                        'is_verified',
                        'created_at',
                        'updated_at',
                    )
                    ->withSum(['bcoinTotal' => function ($query) {
                        $query->where('amount', '>', 0);
                    }], 'amount');
            },
        ])
            ->where('poll_id', intVal($id));

        return $respondents->paginate($per_page);
    }

    public function publishPoll(Request $request, $id)
    {
        if (auth()->user()->privilege == "moderator") {
            $poll = Poll::find($id);

            if (!is_null($poll)) {
                if ($request->action == 'publish') {
                    $poll->update([
                        "published_at" => now(),
                    ]);

                    return ["data" => $poll];
                } else {
                    $poll->update(["published_at" => null]);

                    // delete all response
                    // PollUserResponse::where('poll_id', $poll->poll_id)->delete();

                    return ["data" => $poll];
                }
            }
        }
        abort(404);
    }
}
