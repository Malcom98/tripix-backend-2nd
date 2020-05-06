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
    //---------------------------------------------------------------------------------------------------------------------------------------------------
    //---------------------------------------------------------  A P I   F U N C T I O N S --------------------------------------------------------------
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    //Function getUserStats(Request $request) is used to return stats about user
    //  @request - Request that was received from user.
    public function getUserStats(Request $request){
        if(!JWTValidation($request)){
            return response()->json(["Error"=>"Unauthorized"],401);
        }else{ 
            //Get user ID
            $decodedJWT=JWTDecode($request);
            $user=UserModel::where('email',$decodedJWT->email)->get();
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
            
            //Form return object
            $userStats=[
                "finishedRoutesCount"=>$finishedRoutesCount,
                "plannedRoutesCount"=>$plannedRoutesCount,
                "totalDistanceTravelled"=>$distanceTravelled,
                "placesVisited"=>$placesVisitedCount
            ];

            return response()->json($userStats,200);

        }
    }
    //Function saveUser(Request $request) is used to store new user in database.
    //  @request - Request that was received from user.
    public function saveUser(Request $request){
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
        $verificationCode=self::sendActivationEmail($user);
        $user->verified=$verificationCode;
        $user->save();

        //Generate new token
        $jwt = JWTCreate($request->email,$hashedPassword);

        //Return response
        return response()->json(["message"=>"User sucessfuly created.",
                                "token"=>$jwt],201);
    }

    //Function verifyEmailAddress(Request $request) is used to verify E-Mail address with activation code.
    //  @request - Request that was received from user.
    public function verifyEmailAddress(Request $request){
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
            $token=JWTCreate($user[0]->email,$user[0]->password);
            return response()->json(["message"=>"Account sucessfully verified.","user_id"=>$user_id,
                                    "full_name"=>$user_fullname,"token"=>$token],200);
        }else{
            return response()->json(["message"=>"Invalid activation code."],403);
        }
    }

    //Function sendForgottenPasswordCode(Request $request) is used when user enters his email and clicks forgotten password.
    //An new pwd reset code is generated and send to entered email.
    //  @request - Request that was received from user.
    public function sendForgottenPasswordCode(Request $request){
        //Get email from request
        $email=$request->email;

        //Check if user with given email exists
        if(count(UserModel::where('email',$email)->get())==0)
            return response()->json(["message"=>"This email is not used by any user."],403);

        //Generate code
        $code=self::generateVerificationCode();

        //Update code in database
        UserModel::where('email',$email)->update(['pwdresetcode'=>$code]);

        //Send email
        $details = [
            'title' => 'Password Reset Code for Tripix - Travelling Application',
            'body' => 'Your password reset code is: '.$code
        ];
        \Mail::to($email)->send(new ActivationMail($details));

        //Return response
        return response()->json(["message"=>"Reset code has been sent to email."],200);
    }

    //Function checkResetPasswordCode(Request $request) is used to check whether reset code that user entered is valid.
    //  @request - Request that was received from user.
    public function checkResetPasswordCode(Request $request){
        //Get data from request
        $email=$request->email;
        $resetCode=$request->reset_code;

        //Check data and return response
        if(strlen($resetCode) && count(UserModel::where('email',$email)->where('pwdresetcode',$resetCode)->get())!=0)
            return response()->json(["message"=>"Valid code."],200);
        else
            return response()->json(["message"=>"Invalid code."],400);
    }

    //Function setNewPassword(Request $request) is used to set a new password that user entered after validating.
    //reset code.
    //  @request - Request that was received from user.
    public function setNewPassword(Request $request){
        //Validator
        $rules=[
            'new_password'=>'required|min:6',
            'reset_code'=>'required|min:4'
        ];

        $validator=Validator::make($request->all(),$rules);
        if($validator->fails())
            return response()->json($validator->errors(),400);

        //Get data from request
        $email=$request->email;
        $reset_code=$request->reset_code;
        $new_password=$request->new_password;
        $hashedPassword=bcrypt($new_password);

        //Reset password and return response
        if(count(UserModel::where('email',$email)->where('pwdresetcode',$reset_code)->get())!=0){
            UserModel::where('email',$email)->where('pwdresetcode',$reset_code)->update(['password'=>$hashedPassword,'pwdresetcode'=>null]);
            return response()->json(["message"=>"Password successfully changed."],200);
        }else{
            return response()->json(["message"=>"Invalid data."],403);
        }
    }

    //Function changeLoggedUserEmailAdress(Request $request) is used to set a new email on "Profile" tab when user is logged in.
    //  @request - Request that was received from user.
    public function changeLoggedUserEmailAdress(Request $request){
        if(!JWTValidation($request)){
            return response()->json(["message"=>"User does not exist."],403);
        }else{
            //Validation
            $rules=[
                'email'=>'required|min:3|max:64|email|unique:users',
            ];

            $validator=Validator::make($request->all(),$rules);
            if($validator->fails())
                return response()->json($validator->errors(),400);

            //Get new email from request
            $newEmail=$request->email;
            //Decode JWT
            $decodedJWT=JWTDecode($request);
            UserModel::where('email',$decodedJWT->email)->where('password',$decodedJWT->password)->update(['email'=>$newEmail]);

            //Generate new token
            $jwt = JWTCreate($newEmail,$decodedJWT->password);
            //Return response
            return response()->json(["message"=>"Email successfully changed.",
                                     "token"=>$jwt],200);
        }
    }

    //Function changeLoggedUserPassword(Request $request) is used to set a new password on "Profile" tab when user is logged in.
    //  @request - Request that was received from user.
    public function changeLoggedUserPassword(Request $request){
        if(!JWTValidation($request)){
            return response()->json(["message"=>"User does not exist."],403);
        }else{
            //Validation
            $rules=[
                'current_password'=>'required|min:6',
                'new_password'=>'required|min:6'
            ];

            $validator=Validator::make($request->all(),$rules);
            if($validator->fails())
                return response()->json($validator->errors(),400);

            //Check if entered password is equal to current password
            $decodedJWT=JWTDecode($request);
            $email=$decodedJWT->email;
            $password=$decodedJWT->password; // currentPassword

            //Check if entered password is invalid
            if(!Hash::check($request->current_password,$password))
                return response()->json(["message"=>"Invalid current password."],403);
            
            //Change password
            $newPassword=bcrypt($request->new_password);
            UserModel::where('email',$email)->where('password',$password)->update(["password"=>$newPassword]);

            //Generate new token
            $jwt = JWTCreate($email,$newPassword);

            //Return response
            return response()->json(["message"=>"Password successfuly changed.",
                                     "token"=>$jwt],200);
        }
    }





    //---------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------  O T H E R   F U N C T I O N S --------------------------------------------------------------
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    //Function sendActivationEmail($user) is used to send email with verification code.
    //  @user - UserModel object.
    public function sendActivationEmail($user)
    {
        //Generate verification code
        $verificationCode=self::generateVerificationCode();
        $email=$user->email;

        //Generate email title and content
        $details = [
            'title' => 'Activation code for Tripix - Travelling Application',
            'body' => 'Your activation code is: '.$verificationCode
        ];

        //Send email
        \Mail::to($email)->send(new ActivationMail($details));
        return $verificationCode;
    }

    //Function generateVerificationCode() is used to generate a random
    //6 digit code.
    public function generateVerificationCode(){
        $verificationCode="";
        $letters="12345678901234567890";
        for($i=0;$i<6;$i++){
            $verificationCode.=$letters[rand(1,15)];
        }
        return $verificationCode;
    }
}
