<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Validator;

class PhotoController extends Controller
{
    //Function getPhoto(Request $request) is used to retrieve photo from Google API.
    //  @request - Request that was received from user.
    public function getPhoto(Request $request){
        //Validate
        $rules=[
            'photo_reference'=>'required'
        ];

        $validator=Validator::make($request->all(),$rules);
        if($validator->fails())
            return response()->json($validator->errors(),400); 

        //Get data from user request
        $photo_reference=$request['photo_reference'];
        $maxwidth=400;
        if(isset($request['maxwidth']))
            $maxwidth=$request['maxwidth'];
        
        //Create url
        $url="https://maps.googleapis.com/maps/api/place/photo?maxwidth=".$maxwidth;
        if(isset($request['maxheight']))
            $url.="&maxheight=".$request['maxheight'];
        $url.="&photoreference=".$photo_reference."&key=".env("GOOGLE_API_KEY","somedefaultvalue");

        //Return image
        $image=file_get_contents($url);
        header("Content-Type: image/jpeg");
        echo $image;  
    }
}
