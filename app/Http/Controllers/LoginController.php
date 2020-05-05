<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\UserModel;
use Validator;
use Hash;
use \Firebase\JWT\JWT; //composer require firebase/php-jwt:dev-master

class LoginController extends Controller
{
    //Function checkLogin(Request $request) is used to check whether user entered
    //correct or incorrent credentials.
    //If valid credentials were entered, response with JWT token is sent.
    //  @request - Request that was received from user.
    public function checkLogin(Request $request){
        //Validation
        $rules=[
            'email'=>'required|email',
            'password'=>'required'
        ];

        $validator=Validator::make($request->all(),$rules);
        if($validator->fails())
            return response()->json($validator->errors(),400);

        //Get data from request
        $email=$request->input('email');
        $password=$request->input('password');

        //Check if user with entered mail exists
        if(!UserModel::where('email',$email)->exists())
            return response()->json(["Error"=>"Invalid credentials."],403);
        
        //If user with given email exists
        $userModel=UserModel::where('email',$email)->get(); //Get that user
        $userModelVerificationCode=$userModel[0]->verified; // Gets user verification code
        $userModelPassword= $userModel[0]->password; // Get users password
        $userModelName= $userModel[0]->name; // Get users name

        //Check if user verified it's account
        if(strlen($userModelVerificationCode))
            return response()->json(["message"=>"Account not verified yet."],403);

        //Compare inputed password and password in DB that is hashed with bcrypt 
        if(!Hash::check($password,$userModelPassword))
            return response()->json(["message"=>"Invalid credentials."],403);

        //Create JWT and return response
        $jwt = JWTCreate($email,$userModelPassword);
        return response()->json(["message"=>"Successfuly logged in.","token"=>$jwt,"user_id"=>$userModel[0]->id,"full_name"=>$userModelName],200);
    }
}
