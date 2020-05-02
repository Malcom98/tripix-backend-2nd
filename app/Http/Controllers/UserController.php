<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\UserModel;
use Validator;
use Hash;
use \Firebase\JWT\JWT;
use App\Mail\ActivationMail;

class UserController extends Controller
{
    //-------------------------- API functions -----------------------
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

    //VERIFY
    public function verify(Request $request){
        $email=$request->email;
        $activation_code=$request->activation_code;

        $user=UserModel::where('email',$email)->get();
        if(strlen($user[0]->verified)==0){
            return response()->json(["message"=>"Account has already been validated."],200);
        }

        if($user[0]->verified==$activation_code){
            UserModel::where('email',$email)->update(['verified'=>null]);
            $user_id=$user[0]->id;
            $user_fullname=$user[0]->name;
            $token=self::GenerateToken($user[0]->email,$user[0]->password);
            return response()->json(["message"=>"Account sucessfully verified.","user_id"=>$user_id,"full_name"=>$user_fullname,"token"=>$token],200);
        }else{
            return response()->json(["message"=>"Invalid activation code."],403);
        }
    }

    //FORGOTTEN PASSWORD
    public function forgottenPassword(Request $request){
        //Get email from request
        $email=$request->email;

        if(count(UserModel::where('email',$email)->get())!=0){
            //Generate code
            $code=self::GenerateVerificationCode();

            //Update code in database
            UserModel::where('email',$email)->update(['pwdresetcode'=>$code]);
            
            //Send email
            $details = [
                'title' => 'Password Reset Code for Tripix - Travelling Application',
                'body' => 'Your password reset code is: '.$code
            ];
            \Mail::to($email)->send(new ActivationMail($details));

            //Response message
            return response()->json(["message"=>"Reset code has been sent to email."],200);
        }else{
            return response()->json(["message"=>"Invalid email."],403);
        }

    }

    public function resetCode(Request $request){
        $email=$request->email;
        $resetCode=$request->reset_code;

        if(strlen($resetCode) && count(UserModel::where('email',$email)->where('pwdresetcode',$resetCode)->get())!=0){
            return response()->json(["message"=>"Valid code."],200);
        }else{
            return response()->json(["message"=>"Invalid code."],400);
        }
    }

    public function newPassword(Request $request){
        //Validator
        $rules=[
            'new_password'=>'required|min:6',
            'reset_code'=>'required|min:4'
        ];

        $validator=Validator::make($request->all(),$rules);
        if($validator->fails()){
            return response()->json($validator->errors(),400);
        }

        //Put variables into request fields
        $email=$request->email;
        $reset_code=$request->reset_code;
        $new_password=$request->new_password;
        $hashedPassword=bcrypt($new_password);

        if(count(UserModel::where('email',$email)->where('pwdresetcode',$reset_code)->get())!=0){
            UserModel::where('email',$email)->where('pwdresetcode',$reset_code)->update(['password'=>$hashedPassword,'pwdresetcode'=>null]);
            return response()->json(["message"=>"Password successfully changed."],200);
        }else{
            return response()->json(["message"=>"Invalid data."],403);
        }
    }

    //Logged In - New Email
    public function newEmail(Request $request){
        if(!self::CheckIfUserExists($request)){
            return response()->json(["message"=>"User does not exist."],403);
        }else{
            //Validation
            $rules=[
                'email'=>'required|min:3|max:64|email|unique:users',
            ];

            $validator=Validator::make($request->all(),$rules);
            if($validator->fails()){
                return response()->json($validator->errors(),400);
            }

            //Get new email from request
            $newEmail=$request->email;
            //Dehash JWT
            $jwt=$request->header('Authorization');
            $jwt=explode(' ',$jwt)[1]; // Remove bearer from header
            $key=env("JWT_SECRET_KEY","somedefaultvalue");
            $decoded=JWT::decode($jwt,$key,array('HS256'));
            $currentEmail=$decoded->email;
            $currentPassword=$decoded->password;
            UserModel::where('email',$currentEmail)->where('password',$currentPassword)->update(['email'=>$newEmail]);

            //Generate new token
            $payload = array(
                "email"=>$newEmail,
                "password"=> $currentPassword
            );
            $jwt = JWT::encode($payload, $key);

            //Return response
            return response()->json(["message"=>"Email successfully changed.",
                                     "token"=>$jwt],200);
        }
    }

    //Logged In - New password
    public function loggednewPassword(Request $request){
        if(!self::CheckIfUserExists($request)){
            return response()->json(["message"=>"User does not exist."],403);
        }else{
            //Validation
            $rules=[
                'new_password'=>'required|min:6'
            ];

            $validator=Validator::make($request->all(),$rules);
            if($validator->fails()){
                return response()->json($validator->errors(),400);
            }

            //Check if entered password is equal to current password
            $jwt=$request->header('Authorization');
            $key=env('JWT_SECRET_KEY','somedefaultvalue');
            $jwt=explode(' ',$jwt)[1];
            $decoded=JWT::decode($jwt,$key,array('HS256'));
            $email=$decoded->email;
            $password=$decoded->password; // currentPassword

            //Check if entered password is invalid
            if(!Hash::check($request->password,$password))
                return response()->json(["message"=>"Invalid current password."],403);
            
            //Change password
            $newPassword=bcrypt($request->new_password);
            UserModel::where('email',$email)->where('password',$password)->update(["password"=>$newPassword]);

            //Generate new token
            $payload = array(
                "email"=>$email,
                "password"=> $newPassword
            );
            $jwt = JWT::encode($payload, $key);
            return response()->json(["message"=>"Password successfuly changed.",
                                     "token"=>$jwt],200);

            
        }
    }


    //------------------------ Other functions ---------------------
    public function GenerateToken($email,$password){
        $key=env('JWT_SECRET_KEY','somedefaultvalue');
        $payload = array(
            "email"=>$email,
            "password"=> $password
        );
        $jwt = JWT::encode($payload, $key);
        return $jwt;
    }

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
        $letters="12345678901234567890";
        for($i=0;$i<6;$i++){
            $verificationCode.=$letters[rand(1,15)];
        }
        return $verificationCode;
    }
}
