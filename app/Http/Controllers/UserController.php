<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\UserModel;
use Validator;
use \Firebase\JWT\JWT;
use App\Mail\ActivationMail;

class UserController extends Controller
{
    //-------------------------- API functions -----------------------
    //GET   /users
    public function index(Request $request){
        //Check if JWT is valid.
        if(self::CheckIfUserExists($request))
            return response()->json(UserModel::get(),200);
        else
            return response()->json(["Message"=>"Unauthorized"],401);
    }

    //GET   /users/{id}  - CURRENTLY UNAVAILABLE BECAUSE THERE'S DISABLED API ROUTE
    public function show($id){
        $user=UserModel::find($id);

        //Check if user exists
        if(is_null($user)){
            return response()->json(["message"=>"User not found."],404);
        }

        return response()->json($user,200);
    }

    //POST  /users
    public function store(Request $request){
        //Validation
        $rules=[
            'name'=>'required|min:3|max:32',
            'email'=>'required|min:3|max:64|email|unique:users',
            'password'=>'required|min:6'
        ];

        $validator=Validator::make($request->all(),$rules);
        if($validator->fails()){
            return response()->json($validator->errors(),400);
        }


        //---------- Adding user ---------- 
        $user = new UserModel;
        $user->name=$request->name;
        $hashedPassword=bcrypt($request->password); // Using bcrypt to hash
        $user->password=$hashedPassword; 
        $user->email=$request->email;
        $verificationCode=self::SendActivationEmail($user);
        $user->verified=$verificationCode;
        $user->save();

        $key = env("JWT_SECRET_KEY", "somedefaultvalue"); 
        $payload = array(
            "email"=>$request->email,
            "password"=> $hashedPassword
        );
        $jwt = JWT::encode($payload, $key);

        return response()->json(["message"=>"User sucessfuly created.",
                                    "token"=>$jwt],201);
    }

    //PUT   /users/{id}  - CURRENTLY UNAVAILABLE BECAUSE THERE'S DISABLED API ROUTE
    public function update(Request $request, $id){
        //Validation
        $rules=[
            'name'=>'required|min:3|max:32',
            'email'=>'required|min:3|max:64|email|unique:users',
            'password'=>'required|min:6'
        ];

        $validator=Validator::make($request->all(),$rules);
        if($validator->fails()){
            return response()->json($validator->errors(),400);
        }

        //-----Updating user-------
        $user=UserModel::find($id);

        //Check if user exists
        if(is_null($user)){
            return response()->json(["message"=>"User not found."],404);
        }
            
        $user->update($request->all());
        return response()->json(["message"=>"User sucessfuly updated."],200);
    }

    //DELETE    /users/{id}  - CURRENTLY UNAVAILABLE BECAUSE THERE'S DISABLED API ROUTE
    public function delete($id){
        $user=UserModel::find($id);

        //Check if user exists
        if(is_null($user)){
            return response()->json(["message"=>"User not found."],404);
        }

        $user->delete();
        return response()->json(["message"=>"User sucessfuly deleted."],200);
    }


    //VERIFY
    public function verify(Request $request){
        $email=$request->email;
        $activation_code=$request->activation_code;

        $user=UserModel::where('email',$email)->get();
        if($user[0]->verified=="None."){
            return response()->json(["message"=>"Account has already been validated."],200);
        }

        if($user[0]->verified==$activation_code){
            UserModel::where('email',$email)->update(['verified'=>'None.']);
            return response()->json(["message"=>"Account sucessfully verified."],200);
        }else{
            return response()->json(["message"=>"Invalid activation code."],403);
        }
    }

    //------------------------ Other functions ---------------------
    public function CheckIfUserExists(Request $request){
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

    public function SendActivationEmail($user)
    {
        $verificationCode=self::GenerateVerificationCode();
        $email=$user->email;

        $details = [
            'title' => 'Activation code for Tripix - Travelling Application',
            'body' => 'Your activation code is: '.$verificationCode
        ];

        \Mail::to($email)->send(new ActivationMail($details));
        return $verificationCode;
    }

    public function GenerateVerificationCode(){
        $verificationCode="";
        $letters="aAbBcCdDeEfFgGhHiIjJkKlLyY";
        for($i=0;$i<16;$i++){
            $verificationCode.=$letters[rand(1,20)];
        }
        return $verificationCode;
    }
}
