<?php

namespace App\Http\Controllers;

use App\Models\Charity;
use App\Models\CharityImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CharityController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $per_page = !is_null($request->per_page) ? (int) $request->per_page : 50;
        $charities = Charity::with("images")->whereNull('deleted_at');
        if (!is_null($request->country_code))
            $charities->where('country_code', $request->country_code);

        return $charities->simplePaginate($per_page);
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

        if (!is_null($request->charity_id)) {
            return $this->update($request, $request->charity_id);
        }

        $charity = new Charity();
        $charity->charity_name = $request->charity_name;
        $charity->user_id = $request->user_id;
        $charity->description = $request->description;
        $charity->html_content = $request->html_content;
        $charity->country_code = $request->country_code;
        $charity->bcoin_donated = $request->bcoin_donated;
        $charity->save();

        if ($charity->charity_id) {
            // Upload images after saving the activity feed
            if (is_array($request->images)) {
                $validator = Validator::make($request->images, [
                    'images.*' => 'mimes:jpg,jpeg,png|max:10240'
                ], [
                    'images.*.mimes' => 'Only jpeg, png, and jpg images are allowed',
                    'images.*.max' => 'Sorry! Maximum allowed size for an image is 10MB',
                ]);

                if (!$validator->fails()) {
                    foreach ($request->images as $image) {
                        if (!is_null($image)) {
                            $randomHex1 = bin2hex(random_bytes(6));
                            $randomHex2 = bin2hex(random_bytes(6));
                            $uploadDate = now()->year . "-" . now()->month . "-" . now()->day;
                            $extension = $image->extension();
                            $newFileName = $uploadDate . '-' . $randomHex1 . '-' . $randomHex2 . '.'  . $extension;
                            $image->storeAs('/public/images/charity/' . $charity->charity_id, $newFileName);
                            $charityImage = new CharityImage();
                            $charityImage->charity_id = $charity->charity_id;
                            $charityImage->image_path = $newFileName;
                            $charityImage->save();
                        }
                    }
                }
            }
        }

        $charity->images;

        return ["data" => $charity];
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Charity  $charity
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $charity = Charity::with(['images'])
            ->find($id);
        return response(["data" => $charity]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Charity  $charity
     * @return \Illuminate\Http\Response
     */
    public function edit(Charity $charity)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Charity  $charity
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $charity = Charity::find($id);
        if ($charity) {
            $fieldToUpdate = $request->only(['charity_name', 'user_id', 'description', 'html_content', 'country_code']);
            $charity->update($fieldToUpdate);

            if ($id) {
                // Upload images after saving the activity feed
                if (is_array($request->images)) {
                    $validator = Validator::make($request->images, [
                        'images.*' => 'mimes:jpg,jpeg,png|max:10240'
                    ], [
                        'images.*.mimes' => 'Only jpeg, png, and jpg images are allowed',
                        'images.*.max' => 'Sorry! Maximum allowed size for an image is 10MB',
                    ]);

                    if (!$validator->fails()) {
                        foreach ($request->images as $image) {
                            if (!is_null($image)) {
                                $randomHex1 = bin2hex(random_bytes(6));
                                $randomHex2 = bin2hex(random_bytes(6));
                                $uploadDate = now()->year . "-" . now()->month . "-" . now()->day;
                                $extension = $image->extension();
                                $newFileName = $uploadDate . '-' . $randomHex1 . '-' . $randomHex2 . '.'  . $extension;
                                $image->storeAs('/public/images/charity/' . $id, $newFileName);
                                $charityImage = new CharityImage();
                                $charityImage->charity_id = $id;
                                $charityImage->image_path = $newFileName;
                                $charityImage->save();
                            }
                        }
                    }
                }
            }

            $charity->images;
            return ["data" => $charity];
        }

        return response()->json(["error" => "Charity not found."], 400);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Charity  $charity
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $charityToDelete = Charity::find($id);
        if ($charityToDelete) {
            $charityToDelete->delete();
            return ["data" =>  $charityToDelete];
        }
        return ["error" => ["message" => "Comment not found."]];
    }
}
