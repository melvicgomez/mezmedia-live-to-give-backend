<?php

namespace App\Http\Controllers;

use App\Models\ClubInterest;
use App\Models\ContactFormModel;
use App\Models\SuggestionFormChallengeModel;
use App\Models\SuggestionFormLiveSessionModel;
use App\Models\SuggestionFormMeetupModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\User;

class FormsController extends Controller
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

  public function contactForm(Request $request)
  {
    try {
      $user = User::where('user_id', auth()->user()->user_id)->first();
      $contactform = new ContactFormModel();
      $contactform->user_id = $user->user_id;
      $contactform->subject = $request->subject;
      $contactform->description = $request->description;
      $contactform->name = $user->first_name . " " . $user->last_name;
      $contactform->email = $user->email;
      $contactform->save();

      if ($contactform->contact_form_id) {
        Mail::send(
          'contact-form-user-email',
          [
            'name' => $contactform->name,
          ],
          function ($message) use ($contactform) {
            $message->to($contactform->email)->subject('Thank you for your enquiry!');
          }
        );
        Mail::send(
          'contact-form-support-email',
          [
            'contactform' => $contactform,
          ],
          function ($message) {
            $message
              ->to("support@livetogive.co")
              ->subject('You’ve received an enquiry from the Contact form');
          }
        );
        return response(null, 200);
      }
    } catch (\Throwable $th) {
      return response(["error" => $th->getMessage()], 422);
    }
  }

  public function publicContactForm(Request $request)
  {
    $param = [
      "secret" => "0xF168E67CA5d651Efef3d6619FC5051566755BFcB",
      "response" => $request->token
    ];

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://hcaptcha.com/siteverify',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 60,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => http_build_query($param),
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/x-www-form-urlencoded',
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    $response = json_decode($response);

    if ($response->success) {

      try {
        $contactform = new ContactFormModel();
        $contactform->user_id = is_null(auth()->user()) ? 0 : auth()->user()->user_id;
        $contactform->subject = $request->subject;
        $contactform->description = $request->description;
        $contactform->name = $request->name;
        $contactform->email = $request->email;
        $isValidUser = User::where('email', $contactform->email)->first();
        if (is_null($isValidUser)) {
          return response(null, 422);
        }
        $contactform->save();

        if ($contactform->contact_form_id) {
          Mail::send(
            'contact-form-user-email',
            [
              'name' => $contactform->name
            ],
            function ($message) use ($contactform) {
              $message
                ->to($contactform->email)
                ->subject('Thank you for your enquiry!');
            }
          );
          Mail::send(
            'contact-form-support-email',
            [
              'contactform' => $contactform,
            ],
            function ($message) {
              $message
                ->to("support@livetogive.co")
                ->subject('You’ve received an enquiry from the Contact form');
            }
          );
          return response(null, 200);
        }
      } catch (\Throwable $th) {
        return response(["error" => $th->getMessage()], 422);
      }
    } else {
      return response(["error" => 'Invalid request'], 400);
    };
  }

  public function challengeSuggestion(Request $request)
  {
    try {
      $challenge = new SuggestionFormChallengeModel();
      $challenge->user_id = auth()->user()->user_id;
      $challenge->interest_id = $request->interest_id;
      $challenge->title = $request->title;
      $challenge->description = $request->description;
      $challenge->save();
      $user = User::where('user_id', $challenge->user_id)->first();
      $interest = ClubInterest::where('interest_id', $challenge->interest_id)->first();

      if ($challenge->id) {
        Mail::send(
          'suggest-challenge-user-email',
          [
            'name' => $user->first_name . " " . $user->last_name,
            'title' => $challenge->title,
          ],
          function ($message) use ($user) {
            $message->to($user->email)->subject('Thank you for your Challenge suggestion!');
          }
        );
        Mail::send(
          'suggest-challenge-support-email',
          [
            'challenge' => $challenge,
            "clubname" => $interest->interest_name,
            "user" => $user,
          ],
          function ($message) {
            $message
              ->to("support@livetogive.co")
              ->subject('You’ve received a Challenge suggestion');
          }
        );
        return response($challenge);
      }
    } catch (\Throwable $th) {
      return response(["error" => $th->getMessage()], 422);
    }
  }

  public function liveSessionSuggestion(Request $request)
  {
    try {
      $liveSession = new SuggestionFormLiveSessionModel();
      $liveSession->user_id = auth()->user()->user_id;
      $liveSession->interest_id = $request->interest_id;
      $liveSession->host_name = $request->host_name;
      $liveSession->description = $request->description;
      $liveSession->save();

      $user = User::where('user_id', $liveSession->user_id)->first();
      $interest = ClubInterest::where('interest_id', $liveSession->interest_id)->first();

      if ($liveSession->id) {
        Mail::send(
          'suggest-live-session-user-email',
          [
            'name' => $user->first_name . " " . $user->last_name,
            'title' => $liveSession->host_name,
          ],
          function ($message) use ($user) {
            $message->to($user->email)
              ->subject('Thank you for your Live Session suggestion!');
          }
        );
        Mail::send(
          'suggest-live-session-support-email',
          [
            'liveSession' => $liveSession,
            "clubname" => $interest->interest_name,
            "user" => $user,
          ],
          function ($message) {
            $message
              ->to("support@livetogive.co")
              ->subject('You’ve received a Live Session suggestion');
          }
        );

        return response($liveSession);
      }
    } catch (\Throwable $th) {
      return response(["error" => $th->getMessage()], 422);
    }
  }

  public function meetupSuggestion(Request $request)
  {
    try {
      $meetup = new SuggestionFormMeetupModel();
      $meetup->user_id = auth()->user()->user_id;
      $meetup->interest_id = $request->interest_id;
      $meetup->title = $request->title;
      $meetup->description = $request->description;
      $meetup->slots = $request->slots;
      $meetup->started_at = $request->started_at;
      $meetup->ended_at = $request->ended_at;
      $meetup->virtual_room_link = $request->virtual_room_link;
      $meetup->additional_details = $request->additional_details;
      $meetup->save();

      $user = User::where('user_id', $meetup->user_id)->first();
      $interest = ClubInterest::where('interest_id', $meetup->interest_id)->first();

      if ($meetup->id) {
        Mail::send(
          'suggest-meetup-user-email',
          [
            'name' => $user->first_name . " " . $user->last_name,
            'title' => $meetup->title,
          ],
          function ($message) use ($user) {
            $message->to($user->email)
              ->subject('Thank you for your Virtual Meetup suggestion!');
          }
        );
        Mail::send(
          'suggest-meetup-support-email',
          [
            'meetup' => $meetup,
            "clubname" => $interest->interest_name,
            "user" => $user,
          ],
          function ($message) {
            $message
              ->to("support@livetogive.co")
              ->subject('You’ve received a Meetup suggestion');
          }
        );
        return response($meetup);
      }
    } catch (\Throwable $th) {
      return response(["error" => $th->getMessage()], 422);
    }
  }
}
