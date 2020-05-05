<?php
namespace App\Http\Controllers;
use \Firebase\JWT\JWT;

//Function JWTCreate($email,$password) is used to generate JWT token depending
//on email and password.
//  @email - User email.
//  @password - User password.
function JWTCreate($email,$password){
    $key=env('JWT_SECRET_KEY','somedefaultvalue');
    $payload = array(
        "email"=>$email,
        "password"=> $password
    );
    $jwt = JWT::encode($payload, $key);
    return $jwt;
}
?>