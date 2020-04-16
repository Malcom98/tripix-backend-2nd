<?php

namespace App\Http\Controllers;

use App\Route;
use Illuminate\Http\Request;
use App\UserModel;
use Validator;
use \Firebase\JWT\JWT;

class RouteController extends Controller
{
    public function plannedRoute(Request $request){
        //JWT Validation
        if(!self::JWTValidation($request)){
            return response()->json(["Error"=>"Unauthorized."],401);
        }

        //Authorization
        $rules=[
            'user_id'=>'required',
            'location'=>'required',
            'total_time'=>'required',
            'total_distance'=>'required',
            'locations'=>'required'
        ];

        $validator=Validator::make($request->all(),$rules);
        if($validator->fails()){
            return response()->json($validator->errors(),400);
        }

        //Save data into table routes

        //Save data into table route_items

        
        //Response
        return response()->json(["Message"=>"Ok"],200);
    }


    /* ------------------ Other functions ------------------------- */
    private function SavePlannedRoute($request){
        $location=$request->used_id;
        $user_id=$request->user_id;
        $status_id=1;
        $total_time=$request->total_time;
        $total_distance=$request->total_distance;


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
