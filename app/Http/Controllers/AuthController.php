<?php

namespace App\Http\Controllers;

use App\Models\EmailWhitelist;
use App\Models\OneTimePin;
use App\Models\User;
use App\Models\UserForgotPassword;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;


class AuthController extends Controller
{

    private $validEmails = [
        "@barclays.com",
        "@barcap.com",
        "@barclayscapital.com",
        "@barclayscorporate.com",
        "@just-challenge.com",
        "@barclayscorp.com",
        "@barclaysasia.com",
        "@barclayswealth.com",
        "@mezmedia.com",
    ];

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


    public function register(Request $request)
    {
        $whitelisted = EmailWhitelist::where('email', $request->username)->first();
        if (!Str::endsWith(
            strtolower($request->username),
            $this->validEmails
        ) && is_null($whitelisted)) {
            return response(["error" => (object) array_merge(
                ["username" => "Not a valid email address."],
            )], 400);
        } else {
            // proceed if a valid barclays' email
            $validator = Validator::make($request->all(), [
                'password' => 'required',
                'username' => 'required',
            ]);

            $userFound = User::where('email', strtolower($request->username))->first();
            if ($validator->fails()) {
                return response(["error" => (object) array_merge(
                    [
                        "username" => !isset($request->username) ? "Username is a required field." : null,
                        "password" => !isset($request->password) ?  "Password is a required field." : null
                    ]
                )], 409);
            }
            if (is_null($userFound)) {
                // create new account
                $user = new User;
                $user->password = Hash::make($request->password);
                $user->email = strtolower($request->username);
                if ($request->privilege)
                    $user->privilege = $request->privilege;
                else
                    $user->privilege = "user";
                $user->save();
                return response(["data" => [
                    "user_id" => $user->user_id,
                    "email" => $user->email,
                ]]);
            } else {
                if ($userFound->is_verified == 1) {
                    return response(["error" => ["username" => "Email address already exists."]], 409);
                } else if ($userFound->is_verified == 0) {
                    if ($userFound->privilege == 'suspended') {
                        return response(["error" => (object) array_merge(
                            ["username" => "Not a valid email address."],
                        )], 400);
                    } else {
                        // user is existing but not verified
                        $new_password = Hash::make($request->password);
                        $userFound->update(["password" => $new_password]);

                        return response(["data" => [
                            "user_id" => $userFound->user_id,
                            "email" => $userFound->email,
                        ]]);
                    }
                }
            }
        }
    }

    public function authenticate(Request $request)
    {
        $whitelisted = EmailWhitelist::where('email', $request->username)->first();

        if (!Str::endsWith(
            strtolower($request->username),
            $this->validEmails
        ) && is_null($whitelisted))
            return response(["error" => (object) array_merge(
                ["username" => "You have entered an invalid email or password."],
            )]);

        $errorCount = 0;
        $validatorError = [];
        $userNotFoundError = [];

        $validator = Validator::make($request->all(), [
            'password' => 'required',
            'username' => 'required',
        ]);


        if ($validator->fails()) {
            $errorCount += 1;
            $validatorError = [
                "username" => !isset($request->username) ? "Username is a required field." : null,
                "password" => !isset($request->password) ?  "Password is a required field." : null
            ];
        }

        $userFound = User::where('email', strtolower($request->username))->where('is_verified', 1)
            ->count();

        if ($userFound === 0) {
            $errorCount += 1;
            $userNotFoundError = ["username" => "This email is not registered with an account."];
        }

        if ($errorCount > 0) {
            return ["error" => array_merge($validatorError, $userNotFoundError)];
        } else {
            if (Auth::attempt([
                'email' => strtolower($request->username),
                'password' => $request->password,
            ])) {
                $user = Auth::user();

                $now = now()->timezone('Asia/Hong_Kong');
                $status = $now->isAfter(Carbon::parse(Carbon::create(2021, 7, 23, 18, 0, 0), 'Asia/Hong_Kong'));
                if ($status) {
                    if ($user->privilege != "moderator") {
                        return ["error" => ["username" => null, "password" => "Email or password did not match."]];
                    }
                }

                // consideration: add a condition to check how many concurrent logins are done
                $token = Request::create(
                    '/oauth/token',
                    'POST',
                    [
                        'grant_type' => $request->grant_type,
                        'client_id' => $request->client_id,
                        'client_secret' => $request->client_secret,
                        'scope' =>  $request->scope,
                        'username' =>  strtolower($request->username),
                        'password' => $request->password,
                    ]
                );

                $logDescrReq = new Request(["user_id" => $user->user_id, "description" => "User login."]);
                $userLogs = new UserAccessLogController();
                $userLogs->store($logDescrReq);
                $user->loadSum(['bcoinTotal' => function ($query) {
                    $query->where('amount', '>', 0);
                }], 'amount');

                // revoke all tokens
                $usersToken = $user->tokens
                    ->where('revoked', false)
                    ->where('expires_at', '>=', now());

                if (count($usersToken) >= 3) {
                    $usersToken->slice(2)
                        ->map(function ($item) {
                            $item->revoke();
                        });
                }

                return response()->json(["data" => [
                    "token" => json_decode(app()->handle($token)->content()),
                    "user" => $user,
                ]]);
            } else {
            }

            return ["error" => ["username" => null, "password" => "Email or password did not match."]];
        }
    }

    public function logout()
    {
        $logDescrReq = new Request(["user_id" => auth()->user()->user_id, "description" => "User logout."]);
        $userLogs = new UserAccessLogController();
        $userLogs->store($logDescrReq);

        auth()->user()->token()->revoke();
        return response()->json(["data" => ['user' => auth()->user()]]);
    }

    public function changePassword(Request $request, $id)
    {
        $user = User::find($id);

        $validator = Validator::make($request->all(), [
            'new_password' => 'regex:/^(?=.{8,}$)(?=.*?[a-z])(?=.*?[A-Z])(?=.*?[0-9]).*$/',
        ], [
            'new_password.regex' => 'Invalid new password',
        ]);

        if (!$validator->fails()) {
            if (Hash::check($request->old_password, $user->password)) {
                $new_password = Hash::make($request->new_password);
                $user->update(["password" => $new_password]);

                $logDescrReq = new Request(["user_id" => $id, "description" => "Password changed."]);
                $userLogs = new UserAccessLogController();
                $userLogs->store($logDescrReq);

                $logDescrReq = new Request(["user_id" =>  $user->user_id, "description" => "User changed password successfully."]);
                $userLogs = new UserAccessLogController();
                $userLogs->store($logDescrReq);

                // revoke the 3 oldest token
                $userTokens = $user->tokens
                    ->where('revoked', false)
                    ->where('expires_at', '>=', now());
                foreach ($userTokens as $token) {
                    $token->revoke();
                }
                return response(null, 204);
            }
        } else {
            return response(null, 400);
        }
        return response(null, 400);
    }


    public function forgotPassword(Request $request)
    {
        $user = User::select(
            'user_id',
            'email',
            'first_name',
            'last_name',
            'is_verified',
            'privilege'
        )
            ->where('email', strtolower($request->email))
            ->first();
        if ($user) {
            if (
                $user->privilege == "user" ||
                $user->privilege == "moderator"
            ) {
                try {
                    $otp_code = $this->generateRandomString(5);
                    $request_code = $this->generateRandomString(15);

                    $otp = new OneTimePin();
                    $otp->user_id = $user->user_id;
                    $otp->otp_code = $otp_code;
                    $otp->expired_at = now()->addMinutes(10);
                    $otp->save();

                    $forgotPassObj = new UserForgotPassword();
                    $forgotPassObj->user_id = $user->user_id;
                    $forgotPassObj->request_code = $request_code;
                    $forgotPassObj->save();


                    $logDescrReq = new Request(["user_id" =>  $user->user_id, "description" => "Requested forgot password."]);
                    $userLogs = new UserAccessLogController();
                    $userLogs->store($logDescrReq);

                    return ["data" => ["user_id" => $user->user_id, "request_code" =>  $request_code]];
                } catch (\Throwable $th) {
                    return response(["error" => $th->getMessage()], 422);
                }
            }
        }

        return response(["error" => "Email address does not exists."], 422);
    }


    public function newPassword(Request $request)
    {

        $user = User::withSum(['bcoinTotal' => function ($query) use ($request) {
            $query->where('amount', '>', 0);
        }], 'amount')
            ->where('user_id',  auth()->user()->user_id)
            ->first();

        if (!is_null($user)) {
            $forgotPassObj = UserForgotPassword::where('request_code', $request->request_code)
                ->where('user_id', auth()->user()->user_id);
            if (!is_null($forgotPassObj)) {
                if ($forgotPassObj->first()->status === 0) {
                    $new_password = Hash::make($request->new_password);
                    $user->update(["password" => $new_password]);
                    $forgotPassObj->update(["status" => 1]);

                    $logDescrReq = new Request(["user_id" =>  $user->user_id, "description" => "User changed password successfully by using password feature."]);
                    $userLogs = new UserAccessLogController();
                    $userLogs->store($logDescrReq);
                    // revoke all tokens
                    $userTokens = $user->tokens
                        ->where('revoked', false)
                        ->where('expires_at', '>=', now());
                    foreach ($userTokens as $token) {
                        $token->revoke();
                    }
                    return response(null, 204);
                }
            }
            return response(null, 400);
        }
        return response(null, 400);
    }


    public function generateRandomString($length = 5)
    {
        $pinCharacters = '0123456789BCDFGHJKLMNPQRSTVWXYZ';
        $requestCharacters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $characters = $length === 5 || $length === 6 ? $pinCharacters :  $requestCharacters;

        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function beamsToken()
    {

        $userLogs = new UserAccessLogController();
        $logDescrReq = new Request(["user_id" => auth()->user()->user_id, "description" => "beamsToken"]);
        $userLogs->store($logDescrReq);

        return response()->json(null, 200);
    }
}
