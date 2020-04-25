<?php

namespace App\Http\Controllers;

use App\Route;
use App\RouteItem;
use App\TransportType;
use Illuminate\Http\Request;
use App\UserModel;
use Validator;
use \Firebase\JWT\JWT;

class RouteController extends Controller
{
    //Planned route
    //This function is called when user presses "Create" on "Route Overview" screen
    public function planRoute(Request $request){
        //JWT Validation
        if(!self::JWTValidation($request)){
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
        if(!self::JWTValidation($request)){
            return response()->json(["Error"=>"Unauthorized."],401);
        }else{
            //Check if user input is correct
            $user=UserModel::where('id',$user_id)->get();
            if(count($user)==0){
                return response()->json(["Message"=>"There is no user with given ID."],400);
            }
            
            //GetPlannedRoutes
            $routes=Route::where('user_id',$user_id)->where('status_id',$status_id)->get();
            $plannedRoutes=array();
            foreach($routes as $route){
                $route_id=$route->id;
                $location=$route->location;
                $duration=$route->total_time;
                $date=explode(' ',$route->created_at)[0];

                $routeItems=RouteItem::where('route_id',$route_id)->get();
                $number_attractions=count($routeItems);
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
        if(!self::JWTValidation($request)){
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

    //Get my suggested routes
    //Returns status id 3


    /* ------------------ Other functions ------------------------- */
    private function ChangeRouteStatus($request,$status_id,$message){
        if(!self::JWTValidation($request))
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
            $routeItem->description=$location["description"];
            $routeItem->longitude=$location["longitude"];
            $routeItem->time=$location["duration"];
            $routeItem->distance=$location["distance"];
            $routeItem->transport_type_id=$transport_type_id;
            $routeItem->completed=0;
            $routeItem->save();
            $order_counter++;
        }
    }

    //This function is used to validate JWT token
    private function JWTValidation(Request $request){
        //Decode JWT
        $jwt=$request->header('Authorization');
        if(is_null($jwt))
            return false; // If there is no JWT in header
        $key = env("JWT_SECRET_KEY", "somedefaultvalue"); 
        $jwt=explode(' ',$jwt)[1]; // Remove bearer from header
        $decoded = JWT::decode($jwt, $key, array('HS256'));

        //Get info from from decoded JWT (currently in JSON format)
        $email=$decoded->email;
        $password=$decoded->password;

        //Check if user exists
        $exists=UserModel::where('email',$email)->where('password',$password)->exists();
        return $exists;
    }
}
