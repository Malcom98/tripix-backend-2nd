<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\UserModel;
use Validator;
use Hash;
use \Firebase\JWT\JWT; //composer require firebase/php-jwt:dev-master

class LoginController extends Controller
{
    public function store(Request $request){

        //Validation
        $rules=[
            'email'=>'required|email',
            'password'=>'required'
        ];
        $validator=Validator::make($request->all(),$rules);
        if($validator->fails()){
            return response()->json($validator->errors(),400);
        }

        //Check if exists
        $email=$request->input('email');
        $password=$request->input('password');

        //Check if user with entered mail exists
        if(!UserModel::where('email',$email)->exists()){
            return response()->json(["message"=>"Invalid credentials."],403);
        }else{ // If exists
            $userModel=UserModel::where('email',$email)->get(); //Get that user
            $userModelVerificationCode=$userModel[0]->verified; // Gets user verification code
            $userModelPassword= $userModel[0]->password; // Get users password

            //Check if user verified it's account
            if($userModelVerificationCode!="None."){
                return response()->json(["message"=>"Account not verified yet."],403);
            }

            //Compare inputed password and password in DB that is hashed with bcrypt 
            if(!Hash::check($password,$userModelPassword))
                return response()->json(["message"=>"Invalid credentials."],403);

            $key = env("JWT_SECRET_KEY", "somedefaultvalue"); 
            $payload = array(
                "email"=>$email,
                "password"=> $userModelPassword,
            );

            $jwt = JWT::encode($payload, $key);
            return response()->json(["message"=>"Successfuly logged in.","token"=>$jwt,"user_id"=>$userModel[0]->id],200);
        }
    }
}
