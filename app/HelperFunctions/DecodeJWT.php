<?php
namespace App\Http\Controllers;
use \Firebase\JWT\JWT;
use Illuminate\Http\Request;

//Function JWTDecode(Request $request) is used to decode JWT token and return
//object which contains email and password.
//  @request - Request that was received from user.
function DecodeJWT(Request $request){
    $jwt=$request->header('Authorization');
    if(is_null($jwt))
        return false; // If there is no JWT in header
    $key = env("JWT_SECRET_KEY", "somedefaultvalue"); 
    $jwt=explode(' ',$jwt)[1]; // Remove bearer from header
    $decodedObject = JWT::decode($jwt, $key, array('HS256'));
    return $decodedObject;
}
?>