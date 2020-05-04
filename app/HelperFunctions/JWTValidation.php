<?php
namespace App\Http\Controllers;
use \Firebase\JWT\JWT;
use App\UserModel;
use Illuminate\Http\Request;

//Function JWTValidation(Request $request) is used to generate JWT token
//based on request which has email and hashed (bcrypted) password. 
//  @request - Request that was received from user.
function JWTValidation(Request $request){
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
?>