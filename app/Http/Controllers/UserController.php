<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\UserModel;
use App\Route;
use App\RouteItem;
use Validator;
use Hash;
use \Firebase\JWT\JWT;
use App\Mail\ActivationMail;

class UserController extends Controller
{
    //Function userStats(Request $request) is used to return stats about user
    //  @request - Request that was received from user.
    public function userStats(Request $request){
        if(!JWTValidation($request)){
            return response()->json(["Error"=>"Unauthorized"],401);
        }else{ 
            //Get user ID
            $jwt=$request->header('Authorization');
            $jwt=explode(' ',$jwt)[1]; // Remove bearer from header
            $key=env("JWT_SECRET_KEY","somedefaultvalue");
            $decoded=JWT::decode($jwt,$key,array('HS256'));
            $currentEmail=$decoded->email;
            $user=UserModel::where('email',$currentEmail)->get();
            $user_id=$user[0]["id"];

            //Get number of finished routes
            $finishedRoutes=Route::where('user_id',$user_id)->where('status_id',3)->get();
            $finishedRoutesCount=count($finishedRoutes);
            //Get number of planned routes
            $plannedRoutes=Route::where('user_id',$user_id)->where('status_id',1)->get();
            $plannedRoutesCount=count($plannedRoutes);
            //Get sum of total distance travelled
            $distanceTravelled=Route::where('user_id',$user_id)->where('status_id',3)->sum('total_distance');
            //Get total number of visited places
            $placesVisitedCount=0;
            foreach($finishedRoutes as $finishedRoute)
                $placesVisitedCount+=count(RouteItem::where('route_id',$finishedRoute["id"])->where('completed',1)->get());
            
            $returnObject=[
                "finishedRoutesCount"=>$finishedRoutesCount,
                "plannedRoutesCount"=>$plannedRoutesCount,
                "totalDistanceTravelled"=>$distanceTravelled,
                "placesVisited"=>$placesVisitedCount
            ];

            return response()->json($returnObject,200);

        }
    }
    //Function store(Request $request) is used to store new user in database.
    //  @request - Request that was received from user.
    public function store(Request $request){
        //Validation - Request
        $rules=[
            'name'=>'required|min:3|max:32',
            'email'=>'required|min:3|max:64|email|unique:users',
            'password'=>'required|min:6'
        ];

        $validator=Validator::make($request->all(),$rules);
        if($validator->fails())
            return response()->json($validator->errors(),400);

        //Adding user to database
        $user = new UserModel;
        $user->name=$request->name;
        $hashedPassword=bcrypt($request->password); // Using bcrypt to hash
        $user->password=$hashedPassword; 
        $user->email=$request->email;
        $verificationCode=self::SendActivationEmail($user);
        $user->verified=$verificationCode;
        $user->save();

        //Generate new token
        $jwt = self::GenerateToken($request->email,$hashedPassword);

        //Return response
        return response()->json(["message"=>"User sucessfuly created.",
                                    "token"=>$jwt],201);
    }

    //Function verify(Request $request) is used to verify E-Mail address with activation code.
    //  @request - Request that was received from user.
    public function verify(Request $request){
        //Getting data from request into PHP variables
        $email=$request->email;
        $activation_code=$request->activation_code;

        //Validation - Request
        $rules=[
            'email'=>'required',
            'activation_code'=>'required'
        ];

        $validator=Validator::make($request->all(),$rules);
        if($validator->fails())
            return response()->json($validator->errors(),400);

        //Check if account has already been verified            
        $user=UserModel::where('email',$email)->get();
        if(strlen($user[0]->verified)==0)
            return response()->json(["message"=>"Account has already been validated."],200);
        
        //Check if activation code in database and in request are the same
        if($user[0]->verified==$activation_code){
            UserModel::where('email',$email)->update(['verified'=>null]);
            $user_id=$user[0]->id;
            $user_fullname=$user[0]->name;
            $token=self::GenerateToken($user[0]->email,$user[0]->password);
            return response()->json(["message"=>"Account sucessfully verified.","user_id"=>$user_id,"full_name"=>$user_fullname,"token"=>$token],200);
        }else
            return response()->json(["message"=>"Invalid activation code."],403);
    }

    //Function forgottenPassword(Request $request) is used when user enters his email and clicks forgotten password.
    //An new pwd reset code is generated and send to entered email.
    //  @request - Request that was received from user.
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

    //Function resetCode(Request $request) is used to check whether reset code that user entered is valid.
    //  @request - Request that was received from user.
    public function resetCode(Request $request){
        $email=$request->email;
        $resetCode=$request->reset_code;

        if(strlen($resetCode) && count(UserModel::where('email',$email)->where('pwdresetcode',$resetCode)->get())!=0){
            return response()->json(["message"=>"Valid code."],200);
        }else{
            return response()->json(["message"=>"Invalid code."],400);
        }
    }

    //Function newPassword(Request $request) is used to set a new password that user entered after validating
    //reset code.
    //  @request - Request that was received from user.
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

    //Function newEmail(Request $request) is used to set a new email on "Profile" tab when user is logged in.
    //  @request - Request that was received from user.
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
            $jwt = GenerateToken($newEmail,$currentPassword);
            //Return response
            return response()->json(["message"=>"Email successfully changed.",
                                     "token"=>$jwt],200);
        }
    }

    //Function loggednewPassword(Request $request) is used to set a new password on "Profile" tab when user is logged in.
    //  @request - Request that was received from user.
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
            $jwt = self::GenerateToken($email,$newPassword);

            //Return response
            return response()->json(["message"=>"Password successfuly changed.",
                                     "token"=>$jwt],200);

            
        }
    }


    //------------------------ Other functions ---------------------
    //Function GenerateToken($email,$password) is used to generate JWT token depending
    //on email and password.
    //  @email - User email.
    //  @password - User password.
    public function GenerateToken($email,$password){
        $key=env('JWT_SECRET_KEY','somedefaultvalue');
        $payload = array(
            "email"=>$email,
            "password"=> $password
        );
        $jwt = JWT::encode($payload, $key);
        return $jwt;
    }

    //Function CheckIfUserExists(Request $request) is used to check whether
    //or not user with sent token exists in database.
    //  @request - Request that was received from user.
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

    //Function SendActivationEmail($user) is used to send email with verification code.
    //  @user - UserModel object.
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

    //Function GenerateVerificationCode() is used to generate a random
    //6 digit code.
    public function GenerateVerificationCode(){
        $verificationCode="";
        $letters="12345678901234567890";
        for($i=0;$i<6;$i++){
            $verificationCode.=$letters[rand(1,15)];
        }
        return $verificationCode;
    }
}
