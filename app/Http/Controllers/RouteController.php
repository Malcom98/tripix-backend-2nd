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
    //---------------------------------------------------------------------------------------------------------------------------------------------------
    //---------------------------------------------------------  A P I   F U N C T I O N S --------------------------------------------------------------
    //---------------------------------------------------------------------------------------------------------------------------------------------------
    //Function newRoute(Request $request) is used to generate shortest path based on user request.
    //  @request - Request that was received from user.
    public function newRoute(Request $request){
        //JWT Validation
        if(!JWTValidation($request))
            return response()->json(["Error"=>"Unauthorized"],401);

        return ShortestPath::getShortestPath($request->origin,$request->destination,$request->waypoints);
    }

    //Function planRoute(Request $request) is used when user accepts the route
    //that was shown to him on "Route overview screen".
    //This function saves route and route items into database.    
    //  @request - Request that was received from user.
    public function planRoute(Request $request){
        //JWT Validation
        if(!JWTValidation($request))
            return response()->json(["Error"=>"Unauthorized."],401);

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
        if($validator->fails())
            return response()->json($validator->errors(),400);

        //Save data into table routes
        self::savePlannedRoute($request);
        $userRoutes=Route::where('user_id',$request->user_id)->get();
        $newRouteId=$userRoutes[count($userRoutes)-1]["id"];
        //Save data into table route_items
        self::savePlannedRouteItems($request,$newRouteId);
        //Response
        return response()->json(["Message"=>"Ok","RouteId"=>$newRouteId],200);
    }

    //Function startRoute(Request $request) is used when user starts shown route by 
    //pressing "Start" on "Route Overview with Map" screen.
    //  @request - Request that was received from user.
    public function startRoute(Request $request){
        return self::changeRouteStatus($request,'2','Route started.');
    }

    //Function finishRoute(Request $request) is used when user reaches land mark on the map
    //  @request - Request that was received from user.
    public function finishRoute(Request $request){
        return self::changeRouteStatus($request,'3','Route finished successfully. Congratulations.');
    }

    //Function getSuggestedRoutes(Request $request, $place) 
    //generates a suggested route based on random location.
    //User must send param place in his request and the algorithm will find
    //longitude and latitude of given place.
    //After that, algorithm will search for all attractions with 
    //google rating 3+ in range of 10 kilometers and create a route.
    //  @request - Request that was received from user.
    //  @place - Place for suggesting routes
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
        $nearbyAttractions=self::getNearbyAttractions($latitude,$longitude);

        //Get number of attractions for route
        $totalNumber=count($nearbyAttractions);
        if($totalNumber<6)
            return response()->json("We're sorry, but this place does not have enough attractions to generate a route.");
        
        //If number of attractions is greater than 25, then make it 25
        //because Google Directions API allows maximum 25 waypoints
        if($totalNumber>25)
            $totalNumber=25;

        //Make random routes
        $largeRouteCount=$totalNumber-($totalNumber/5); $largeRoute=array();
        $middleRouteCount=$largeRouteCount/2; $middleRoute=array();
        $miniRouteCount=$middleRouteCount/2; $miniRoute=array();
        self::makeRandomRoute($totalNumber,$miniRouteCount,$middleRouteCount,$largeRouteCount,
                                $miniRoute,$middleRoute,$largeRoute,$nearbyAttractions);
        
        //Create information about objects
        $miniRouteInfo=self::makeRouteObject($miniRoute,$miniRouteCount,$largeRoute,"Mini Route");
        $middleRouteInfo=self::makeRouteObject($middleRoute,$middleRouteCount,$largeRoute,"Middle Route");
        $largeRouteInfo=self::makeRouteObject($largeRoute,$largeRouteCount,$largeRoute,"Large Route");

        //Return response
        return response()->json(["attractions"=>$largeRoute,
                                "routes"=>array($miniRouteInfo,
                                $middleRouteInfo,
                                $largeRouteInfo)],
                                200);
    }

    //Function getPlannedRoutes(Request $request) is used to get planned routes
    //for user that requested his planned routes.
    //  @request - Request that was received from user.
    public function getPlannedRoutes(Request $request){
        return self::getSpecificGroupRoutes($request,'1');
    }

    //Function getStartedRoutes(Request $request) is used to get planned routes
    //for user that requested his planned routes.
    //  @request - Request that was received from user.
    public function getStartedRoutes(Request $request){
        return self::getSpecificGroupRoutes($request,'2');
    }

    //Function getFinishedRoutes(Request $request) is used to get planned routes
    //for user that requested his planned routes.
    //  @request - Request that was received from user.
    public function getFinishedRoutes(Request $request){
        return self::getSpecificGroupRoutes($request,'3');
    }

    //Function getSpecificGroupRoutes($request,$status_id) is used to
    //get to retrieve group routes based on route status_id.
    //  @request - Request that was received from user.
    //  @status_id - Route status id (1 - Planned, 2 - Started, 3 - Finished, 4 - Suggested)
    public function getSpecificGroupRoutes($request,$status_id){
        //JWT validation
        if(!JWTValidation($request)){
            return response()->json(["Error"=>"Unauthorized."],401);
        }else{
            $decodedJWT = JWTDecode($request);
            $user=UserModel::where('email',$decodedJWT->email)->get();
            $user_id=$user[0]["id"];
            
            //Get specific routes by status id
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

            //Return response
            return response()->json($plannedRoutes,200);
        }
    }

    //Function getSpecificRoute($request,$id) is used to
    //get specific detailer information about route with given id.
    //  @request - Request that was received from user.
    //  @id - route id that detailed instructions are requested for
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
                    "photo_reference"=>$routeItem->photo_reference,
                    "status"=>$routeItem->completed
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

    //Function getPlaceDescription(Request $request,$id) is used to get
    //best review on location from Google API.
    //  @request - Request that was received from user.
    //  @id - Google Place API id
    public function getPlaceDescription(Request $request,$id){
        if(!JWTValidation($request))
            return response()->json(["Error"=>"Unauthorized"],401);

        $apiResponse=file_get_contents('https://maps.googleapis.com/maps/api/place/details/json?place_id='.$id.'&key='.env("GOOGLE_API_KEY","somedefaultvalue"));

        //If there are no reviews about attraction
        if(!isset(json_decode($apiResponse)->result->reviews))
            return response()->json(["description"=>"There is no comment about this attraction yet."],200);

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


    //Function completeRouteWaypoint(Request $request) is used to change row attribute completed
    //from 0 to 1 and mark waypoint as visited
    //  @request - Request that was received from user.
    public function completeRouteWaypoint(Request $request){
        //JWT Validation
        if(!JWTValidation($request))
            return response()->json(["Error"=>"Unauthorized"],401);
        
        //Validation
        $rules=[
            'route_id'=>'required',
            'place_id'=>'required'
        ];

        $validator=Validator::make($request->all(),$rules);
        if($validator->fails())
            return response()->json($validator->errors(),400);

        //Check if user that sent request is creator of given route
        $decodedJWT=JWTDecode($request);
        $route_id=$request->route_id;
        $place_id=$request->place_id;
        $user_id=UserModel::where('email',$decodedJWT->email)->get()[0]["id"];
        $route=Route::where('id',$route_id)->where('user_id',$user_id)->get();
        if(count($route)==0)
            return response()->json(["Error"=>"Unauthorized"],403);

        //Change waypoint to completed
        RouteItem::where('route_id',$route_id)->where('place_reference',$place_id)->update(["completed"=>"1"]);
        return response()->json(["Message"=>"Ok"],200);
    }






    //---------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------  O T H E R   F U N C T I O N S --------------------------------------------------------------
    //---------------------------------------------------------------------------------------------------------------------------------------------------
    //Function getCoordinatesOfCity($place) returns object of
    //coordinates with latitude and longitude.
    //  @place - Place name
    private function getCoordinatesOfCity($place){
        $url="https://maps.googleapis.com/maps/api/geocode/json?address=".rawurlencode($place)."&key=".env("GOOGLE_API_KEY", "somedefaultvalue"); 
        $googleApiResponse=file_get_contents($url);
        $googleApiResponse=json_decode($googleApiResponse);
        $latitude=json_encode($googleApiResponse->results[0]->geometry->location->lat);
        $longitude=json_encode($googleApiResponse->results[0]->geometry->location->lng);

        $coordinates=array($latitude,$longitude);
        return $coordinates;
    }

    //Function getNearby($latitude,$longitude,$type) is used to
    //get specific nearby locations based on latitude and longitude coordinations.
    //  @latitude - Latitude of current location. 
    //  @longitude - Longitude of current location. 
    //  @Type - Google API place type. 
    private function getNearby($latitude,$longitude,$type){
            //Form link and get data
            $link="https://maps.googleapis.com/maps/api/place/nearbysearch/json?location=".$latitude.",".$longitude."&radius=5000&type=".$type."&key=".env("GOOGLE_API_KEY","somedefaultvalue");
            $response=json_decode(file_get_contents($link));
            $response=$response->results;

            //Create response array and fill it with data
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

            //Return response
            return $responseArray;
    }

    //Function changeRouteStatus($request,$status_id,$message) is used to change
    //route status id.
    //  @request - Request that was received from user.
    //  @status_id - New route status id.
    //  @message - Message that will be returned to user.
    private function changeRouteStatus($request,$status_id,$message){
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

        //Return response
        return response()->json(["Message"=>$message],200);
    }

    //Function savePlannedRoute($request) is used to save 
    //planned route in database table "route".
    //  @request - Request that was received from user.
    private function savePlannedRoute($request){
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

    //Function savePlannedRoute($request) is used to save 
    //planned route items in database table "route_items".
    //  @request - Request that was received from user.
    //  @routeId - foreign key to route to which route items belong.
    private function savePlannedRouteItems($request,$routeId){
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

    //Function getNearbyAttractions($latitude,$longitude) is used to get
    //nearby attractions(amusement parks, museums, libraries, parks, stadiums)
    //based on latitude and longitude given in arguments.
    //  @latitude - Latitude of place we want to get nearby locations.
    //  @longitude - Longitude of place we want to get nearby locations.
    private function getNearbyAttractions($latitude,$longitude){
        $tourist_attraction_json=self::getNearby($latitude,$longitude,"tourist_attraction");
        $amusement_park_json=self::getNearby($latitude,$longitude,"amusement_park");
        $museum_json=self::getNearby($latitude,$longitude,"museum");
        $library_json=self::getNearby($latitude,$longitude,"library");
        $park_json=self::getNearby($latitude,$longitude,"park");
        $stadium_json=self::getNearby($latitude,$longitude,"stadium");
        $mergedArray=array_merge($tourist_attraction_json,$amusement_park_json,
                $museum_json,$library_json,$park_json,$stadium_json);
        $mergedArray=RemoveDuplicates($mergedArray);

        return $mergedArray;
    }

    //Function makeRandomRoute($totalNumber,$miniRouteCount,$middleRouteCount,$largeRouteCount,&$miniRoute,&$middleRoute,&$largeRoute,$nearbyAttractions)
    //is used to make a random mini,middle and large route based on nearby attractions.
    //  @totalNumber - Total number of attractions.
    //  @miniRouteCount,middleRouteCount,largeRouteCount - Number of attractions in each route.
    //  @&miniRoute,&middleRoute,&largeRoute - (ref) Array of routes.
    //  @nearbyAttractions - Array of nearby attractions based on current latitude and longitude.
    private function makeRandomRoute($totalNumber,$miniRouteCount,$middleRouteCount,$largeRouteCount,
                                                              &$miniRoute,&$middleRoute,&$largeRoute,$nearbyAttractions){
        $usedIndices=array();

        $id=rand(0,$totalNumber);
        for($i=0;$i<$largeRouteCount;$i++){
            while(in_array($id,$usedIndices))
                $id=rand(0,$totalNumber-1);
            array_push($usedIndices,$id);
            
            if($i<$miniRouteCount) array_push($miniRoute,$nearbyAttractions[$id]);
            if($i<$middleRouteCount) array_push($middleRoute,$nearbyAttractions[$id]);
            if($i<$largeRouteCount) array_push($largeRoute,$nearbyAttractions[$id]);
        }
    }

    //Function makeRouteObject($route,$routeCount,&$largeRoute,$routeName) creates basic information
    //about route that is shown on "Suggested routes screen".
    //  @route - (ref) - Array of route objecst that are in route.
    //  @routeCount - Count of objects in route.
    //  @largeRoute - (ref) - Large route array of objects.
    //  @routeName - Name of route that will be given in response.
    private function makeRouteObject(&$route,$routeCount,&$largeRoute,$routeName){
        $routeRequestObject=json_decode(json_encode(ShortestPath::createObjectForShortestPath($route)));
        $routeTime=json_decode(ShortestPath::getShortestPath($routeRequestObject->origin,
            $routeRequestObject->destination,$routeRequestObject->waypoints,false))->duration;
        
        $routeInfo=[
            "number_attractions"=>count($route),
            "duration"=>$routeTime,
            "name"=>$routeName,
            "photo_ref"=>$largeRoute[$routeCount-1]->photo_reference
        ];

        return $routeInfo;
    }
}
