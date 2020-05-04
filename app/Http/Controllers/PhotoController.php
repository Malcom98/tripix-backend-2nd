<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PhotoController extends Controller
{
    public function getPhoto(Request $request){
        $photo_reference=$request['photo_reference'];
        $maxwidth=400;
        if(isset($request['maxwidth']))
            $maxwidth=$request['maxwidth'];
        
        if($photo_reference==null)
            return response()->json(["Error"=>"Bad Request"],400);

        $url="https://maps.googleapis.com/maps/api/place/photo?maxwidth=".$maxwidth;
        if(isset($request['maxheight']))
            $url.="&maxheight=".$request['maxheight'];
        $url.="&photoreference=".$photo_reference."&key=".env("GOOGLE_API_KEY","somedefaultvalue");

        $image=file_get_contents($url);
        header("Content-Type: image/jpeg");
        echo $image;  
    }
}
