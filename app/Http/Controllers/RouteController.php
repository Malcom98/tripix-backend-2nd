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

    //Get my planned routes
    //Returns status id 1 and 2
    public function getPlannedRoutes(Request $request){
        //JWT validation
        if(!self::JWTValidation($request)){
            return response()->json(["Error"=>"Unauthorized"],401);
        }else{
            //Validation
            $rules=[
                'user_id'=>'required'
            ];

            $validator=Validator::make($request->all(),$rules);
            if($validator->fails()){
                return response()->json($validator->errors(),400);
            }

            //Get data from request
            $user_id=$request->user_id;
            //Get routes
            $startedRoutes=Route::where('user_id',$user_id)->where('status_id',2)->get(); // Started routes
            $plannedRoutes=Route::where('user_id',$user_id)->where('status_id',1)->get(); // Planned routes
            $returnPlannedRoutes=array(); // Array of objects to be returned

            //For started/planned route
            foreach($plannedRoutes as $route){
                //Get route items
                $routeItems=RouteItem::where('route_id',$route->id)->get();  

                //Put route items in routeItemsArray object
                $routeItemsArray=array();
                foreach($routeItems as $routeItem){
                    $routeItemObject=[
                        "name"=>"TO DO!!!",
                        "place_id"=>$routeItem->place_reference,
                        "latitude"=>$routeItem->latitude,
                        "longitude"=>$routeItem->longitude,
                        "duration"=>$routeItem->time,
                        "distance"=>$routeItem->distance,
                        "photo_reference"=>"TO DO!!!"
                    ];
                    array_push($routeItemsArray,$routeItemObject);
                }


                $plannedRouteObject=[
                    "route_id"=>$route->id,
                    "location"=>$route->location,
                    "locations"=>$routeItemsArray,
                    "route"=>"TO DO!!!!",
                    "duration"=>$route->total_time,
                    "distance"=>$route->total_distance
                ];
                array_push($returnPlannedRoutes,$plannedRouteObject);
            }

            //Returning array of planned route objects
            return $returnPlannedRoutes;
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
        $total_time=$request->duration;
        $total_distance=$request->distance;

        //Put info into database
        $route=new Route();
        $route->location=$location;
        $route->user_id=$user_id;
        $route->status_id=$status_id;
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
            $routeItem->place_reference=$location["place_id"];
            $routeItem->order=$order_counter;
            $routeItem->latitude=$location["latitude"];
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
