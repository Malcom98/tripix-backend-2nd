<?php

namespace App\Http\Controllers;

use App\Route;
use App\RouteItem;
use App\TransportType;
use Illuminate\Http\Request;
use App\UserModel;
use Validator;
use \Firebase\JWT\JWT;
use App\GoogleAPIController;
use ShortestPath;

class RouteController extends Controller
{
    //-----------------------------------------------------------------------------------------------------------
    //------------------------------------- A P I    F U N C T I O N S ------------------------------------------
    //-----------------------------------------------------------------------------------------------------------

    //Planned route
    //This function is called when user presses "Create" on "Route Overview" screen.
    public function planRoute(Request $request){
        //JWT Validation
        if(!JWTValidation($request)){
            return response()->json(["Error"=>"Unauthorized."],401);
        }

        //Validation
        $rules=[
            'user_id'=>'required',
            'location'=>'required',
            'duration'=>'required',
            'route'=>'required',
            'distance'=>'required',
            'locations'=>'required'
        ];

        $validator=Validator::make($request->all(),$rules);
        if($validator->fails()){
            return response()->json($validator->errors(),400);
        }

        //Save data into table routes
        self::SavePlannedRoute($request);
        $userRoutes=Route::where('user_id',$request->user_id)->get();
        $newRouteId=$userRoutes[count($userRoutes)-1]["id"];
        //Save data into table route_items
        self::SavePlannedRouteItems($request,$newRouteId);
        //Response
        return response()->json(["Message"=>"Ok","RouteId"=>$newRouteId],200);
    }

    //Start route
    //This function is called when user presses "Start" on "Route Overview with Map" screen
    public function startRoute(Request $request){
        return self::ChangeRouteStatus($request,'2','Route started.');
    }

    //Finish route
    //This function is called when user reaches his last landmark on map
    public function finishRoute(Request $request){
        return self::ChangeRouteStatus($request,'3','Route finished successfully. Congratulations.');
    }

    //Suggested routes
    //This function generates a suggested route based on random location.
    //User must send param place in his request and the algorithm will find
    //longitude and latitude of given place.
    //After that, algorithm will search for all attractions with rating 3+ in range of 10 kilometers.
    public function getSuggestedRoutes(Request $request,$place){
        //JWT validation
        if(!JWTValidation($request))
            return response()->json(["Error"=>"Unauthorized."],401);

        //Get coordinates of a given city
        $url="https://maps.googleapis.com/maps/api/geocode/json?address=".rawurlencode($place)."&key=".env("GOOGLE_API_KEY", "somedefaultvalue"); 
        $googleApiResponse=file_get_contents($url);
        $googleApiResponse=json_decode($googleApiResponse);
        $latitude=json_encode($googleApiResponse->results[0]->geometry->location->lat);
        $longitude=json_encode($googleApiResponse->results[0]->geometry->location->lng);

        //Get nearby attractions
        $tourist_attraction_json=self::GetNearby($latitude,$longitude,"tourist_attraction");
        $amusement_park_json=self::getNearby($latitude,$longitude,"amusement_park");
        $museum_json=self::getNearby($latitude,$longitude,"museum");
        $library_json=self::getNearby($latitude,$longitude,"library");
        $park_json=self::getNearby($latitude,$longitude,"park");
        $stadium_json=self::getNearby($latitude,$longitude,"stadium");
        $mergedArray=array_merge($tourist_attraction_json,$amusement_park_json,
                $museum_json,$library_json,$park_json,$stadium_json);
        $mergedArray=RemoveDuplicates($mergedArray);

        //Get number of attractions for route
        $totalNumber=count($mergedArray);
        if($totalNumber<6)
            return response()->json("We're sorry, but this place does not have enough attractions to generate a route.");
        //If number of attractions is greater than 25, then make it 25
        //because Google Directions API allows maximum 25 waypoints
        if($totalNumber>25)
            $totalNumber=25;
        $largeRouteCount=$totalNumber-($totalNumber/5); $largeRoute=array();
        $middleRouteCount=$largeRouteCount/2; $middleRoute=array();
        $miniRouteCount=$middleRouteCount/2; $miniRoute=array();
        $usedIndices=array();
        
        $id=rand(0,$totalNumber);
        for($i=0;$i<$largeRouteCount;$i++){
            while(in_array($id,$usedIndices))
                $id=rand(0,$totalNumber-1);
            array_push($usedIndices,$id);
            
            if($i<$miniRouteCount) array_push($miniRoute,$mergedArray[$id]);
            if($i<$middleRouteCount) array_push($middleRoute,$mergedArray[$id]);
            if($i<$largeRouteCount) array_push($largeRoute,$mergedArray[$id]);
        }
        
        //Mini route
        $miniRouteRequestObject=json_decode(json_encode(ShortestPath::createObjectForShortestPath($miniRoute)));
        $miniRouteTime=json_decode(ShortestPath::getShortestPath($miniRouteRequestObject->origin,
            $miniRouteRequestObject->destination,$miniRouteRequestObject->waypoints,false))->duration;
        
        $miniRouteInfo=[
            "number_attractions"=>count($miniRoute),
            "duration"=>$miniRouteTime,
            "name"=>"Mini route",
            "photo_ref"=>$largeRoute[$miniRouteCount-1]->photo_reference
        ];

        //Middle route
        $middleRouteRequestObject=json_decode(json_encode(ShortestPath::createObjectForShortestPath($middleRoute)));
        $middleRouteTime=json_decode(ShortestPath::getShortestPath($middleRouteRequestObject->origin,
            $middleRouteRequestObject->destination,$middleRouteRequestObject->waypoints,false))->duration;
        $middleRouteInfo=[
            "number_attractions"=>count($middleRoute),
            "duration"=>$middleRouteTime,
            "name"=>"Middle route",
            "photo_ref"=>$largeRoute[$middleRouteCount-1]->photo_reference
        ];

        //Large Route
        $largeRouteRequestObject=json_decode(json_encode(ShortestPath::createObjectForShortestPath($largeRoute)));
        $largeRouteTime=json_decode(ShortestPath::getShortestPath($largeRouteRequestObject->origin,
            $largeRouteRequestObject->destination,$largeRouteRequestObject->waypoints,false))->duration;
        $largeRouteInfo=[
            "number_attractions"=>count($largeRoute),
            "duration"=>$largeRouteTime,
            "name"=>"Large route",
            "photo_ref"=>$largeRoute[$largeRouteCount-1]->photo_reference
        ];

        return response()->json(["attractions"=>$largeRoute,
                                "routes"=>array($miniRouteInfo,
                                $middleRouteInfo,
                                $largeRouteInfo)],
                                200);
    }

    //This function is used to 
    //Get my planned route
    public function getPlannedRoutes(Request $request,$id){
        return self::getSpecificGroupRoutes($request,'1',$id);
    }

    //Get started routes
    public function getStartedRoutes(Request $request,$id){
        return self::getSpecificGroupRoutes($request,'2',$id);
    }

    //Get my finished route
    public function getFinishedRoutes(Request $request,$id){
        return self::getSpecificGroupRoutes($request,'3',$id);
    }

    //This function is used to get certain status_id routes
    public function getSpecificGroupRoutes($request,$status_id,$user_id){
        //JWT validation
        if(!JWTValidation($request)){
            return response()->json(["Error"=>"Unauthorized."],401);
        }else{
            //Check if user input is correct
            $user=UserModel::where('id',$user_id)->get();
            if(count($user)==0){
                return response()->json(["Message"=>"There is no user with given ID."],400);
            }

            //Authenticate user request
            $email=$user[0]->email;
            $decodedObject = JWTDecode($request);
            if($email!=$decodedObject->email)
                return response()->json(["Error"=>"Forbidden."],403);
            
            //GetPlannedRoutes
            $routes=Route::where('user_id',$user_id)->where('status_id',$status_id)->orderBy('id','desc')->get();
            $plannedRoutes=array();
            foreach($routes as $route){
                $route_id=$route->id;
                $location=$route->location;
                $duration=$route->total_time;
                $date=explode(' ',$route->created_at)[0];

                $routeItems=RouteItem::where('route_id',$route_id)->get();
                $number_attractions=count($routeItems);
                $photo_reference="null";
                if(isset($routeItems[0]))
                    $photo_reference=$routeItems[0]->photo_reference;

                $newItem=["route_id"=>$route_id,"location"=>$location,"photo_ref"=>$photo_reference,
                            "number_attractions"=>$number_attractions,"duration"=>$duration, "date"=>$date];
                array_push($plannedRoutes,$newItem);
            }

            return response()->json($plannedRoutes,200);
        }
    }

    //Get specific route by id -> MUST BE CHANGED
    public function getSpecificRoute(Request $request,$id){
        //JWT validation
        if(!JWTValidation($request)){
            return response()->json(["Error"=>"Unauthorized"],401);
        }else{
            //Get data from request
            $route_id=$id;
            
            //Get route items
            $route=Route::where('id',$route_id)->get();
            $routeItems=RouteItem::where('route_id',$route_id)->get();  

            //Put route items in routeItemsArray object
            $routeItemsArray=array();
            foreach($routeItems as $routeItem){
                $routeItemObject=[
                    "name"=>$routeItem->name,
                    "place_id"=>$routeItem->place_reference,
                    "latitude"=>$routeItem->latitude,
                    "longitude"=>$routeItem->longitude,
                    "duration"=>$routeItem->time,
                    "distance"=>$routeItem->distance,
                    "photo_reference"=>$routeItem->photo_reference
                ];
                array_push($routeItemsArray,$routeItemObject);
            }


            $specificRoute=[
                "route_id"=>$route[0]->id,
                "location"=>$route[0]->location,
                "locations"=>$routeItemsArray,
                "route"=>$route[0]->route,
                "duration"=>$route[0]->total_time,
                "distance"=>$route[0]->total_distance
            ];

            //Returning array of planned route objects
            return $specificRoute;
        }
    }

    //This function returns place description.
    public function getPlaceDescription(Request $request,$id){
        if(!JWTValidation($request)){
            return response()->json(["Error"=>"Unauthorized"],401);
        }

        $apiResponse=file_get_contents('https://maps.googleapis.com/maps/api/place/details/json?place_id='.$id.'&key='.env("GOOGLE_API_KEY","somedefaultvalue"));

        //If there are no reviews about attraction
        if(!isset(json_decode($apiResponse)->result->reviews)){
            return response()->json(["description"=>"There is no comment about this attraction yet."],200);
        }
        //Else take best rated comment
        $reviewsObject=json_decode($apiResponse)->result->reviews;

        $bestRating=-1;
        $description="";
        foreach($reviewsObject as $review){
            if($review->rating>$bestRating){
                $bestRating=$review->rating;
                $description=$review->text;
            }
        }

        return response()->json(["description"=>str_replace("\n","",$description)],200);
    }


    //-----------------------------------------------------------------------------------------------------------
    //---------------------------------  O T H E R   F U N C T I O N S ------------------------------------------
    //-----------------------------------------------------------------------------------------------------------
    private function GetNearby($latitude,$longitude,$type){
            $link="https://maps.googleapis.com/maps/api/place/nearbysearch/json?location=".$latitude.",".$longitude."&radius=5000&type=".$type."&key=".env("GOOGLE_API_KEY","somedefaultvalue");
            $response=json_decode(file_get_contents($link));
            $response=$response->results;

            $responseArray=array();
            foreach($response as $r){
                    if(isset($r->photos[0]->photo_reference) && isset($r->rating)){
                    $place_id=$r->place_id;
                    $latitude=$r->geometry->location->lat;
                    $longitude=$r->geometry->location->lng;
                    $photo_reference=$r->photos[0]->photo_reference;
                    $rating=$r->rating;
                    $name=$r->name;

                    $ro=[
                        "place_id"=>$place_id,
                        "latitude"=>$latitude,
                        "longitude"=>$longitude,
                        "photo_reference"=>$photo_reference,
                        "rating"=>$rating,
                        "name"=>$name
                    ];
                    array_push($responseArray,$ro);
                }
            }
            return $responseArray;
    }

    private function ChangeRouteStatus($request,$status_id,$message){
        if(!JWTValidation($request))
            return response()->json(["Error"=>"Unauthorized"],401);

        //Validation
        $rules=[
            'route_id'=>'required'
        ];

        $validator=Validator::make($request->all(),$rules);
        if($validator->fails())
            return response()->json($validator->errors(),400);

        //Check if route exists
        $route_id=$request->route_id;
        if(count(Route::where('id',$route_id)->get())==0)
            return response()->json(["Error"=>"Route with this ID does not exist."]);

        //Update
        Route::where('id',$route_id)->update(['status_id'=>$status_id]);
        return response()->json(["Message"=>$message],200);
    }

    private function SavePlannedRoute($request){
        //Get info from request
        $location=$request->location;
        $user_id=$request->user_id;
        $status_id=1;
        $polyline=$request->route;
        $total_time=$request->duration;
        $total_distance=$request->distance;

        //Put info into database
        $route=new Route();
        $route->location=$location;
        $route->user_id=$user_id;
        $route->status_id=$status_id;
        $route->route=$polyline;
        $route->total_time=$total_time;
        $route->total_distance=$total_distance;
        $route->save();
    }

    private function SavePlannedRouteItems($request,$routeId){
        //Get route items
        $locations=$request->locations;
        $order_counter=0;
        $transport_type_id=TransportType::where('transport_type','Car')->get()[0]["id"];

        //Inserting route items into database
        foreach($locations as $location){
            $routeItem=new RouteItem();
            $routeItem->route_id=$routeId;
            $routeItem->name=$location['name'];
            $routeItem->photo_reference=$location['photo_reference'];
            $routeItem->place_reference=$location["place_id"];
            $routeItem->order=$order_counter;
            $routeItem->latitude=$location["latitude"];
            //$routeItem->description=$location["description"];
            $routeItem->longitude=$location["longitude"];
            $routeItem->time=$location["duration"];
            $routeItem->distance=$location["distance"];
            $routeItem->transport_type_id=$transport_type_id;
            $routeItem->completed=0;
            $routeItem->save();
            $order_counter++;
        }
    }
}
