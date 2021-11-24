<?php

namespace App\Http\Controllers;

use App\Models\OneTimePin;
use App\Models\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class OneTimePinController extends Controller
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
        //
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
        //
    }

    public function verifyOtp(Request $request)
    {
        $userOtps = OneTimePin::where('user_id', $request->user_id)
            ->where('otp_code', $request->otp_code)
            ->where('expired_at', '>', now())
            ->where('is_used', 0);

        if (!is_null($userOtps->first())) {
            $token = null;
            $user = User::find($request->user_id);
            if ($user) {
                $userOtps->update(["is_used" => 1]);

                $token_result = $user->createToken('Token for ' . $request->user_id);
                $token = $token_result->token;
                $token->expires_at = now()->addDays(1);
                $token->save();

                // if ($user->is_verified == 0)
                //     $user->update(["is_verified" => 1]);

                return response([
                    "data" => [
                        "is_valid" => 1,
                        "accessToken" => $token_result->accessToken,
                        "token" =>  $token
                    ]
                ]);
            }
        }

        return response(["error" =>
        [
            "is_valid" => 0,
            "token" => 'Invalid PIN.'
        ]], 400);
    }

    public function newOtp(Request $request)
    {
        // limit of 5 request EVERY 10 minutes
        try {
            // Send PIN to email
            $authController = new AuthController();
            $otp_code = $authController->generateRandomString(5);
            $user = User::where('user_id', $request->user_id)->first();

            $otp = new OneTimePin();
            $otp->user_id = $request->user_id;
            $otp->otp_code = $otp_code;
            $otp->expired_at = now()->addMinutes(10);
            $otp->save();

            if ($otp->otp_id) {
                Mail::send(
                    'one-time-pin',
                    ['otp' => $otp_code, 'expired_at' => $otp->expired_at],
                    function ($message) use ($user) {
                        $message->to($user->email)->subject('Your Live to Give Verification PIN');
                    }
                );
            }

            // add validation of OTP spam
            return response($user, 204);
        } catch (\Throwable $th) {
            return response(["error" => $th->getMessage()], 422);
        }
    }
}
