<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

//Register
Route::get('users','UserController@index');
//Route::get('users/{id}','UserController@show');
Route::post('users','UserController@store');
Route::post('users/verify','UserController@verify');
Route::post('users/forgottenPassword','UserController@forgottenPassword');
Route::post('users/resetCode','UserController@resetCode');
Route::post('users/newPassword','UserController@newPassword');
Route::post('users/loggedNewEmail','UserController@newEmail');
Route::post('users/loggedNewPassword','UserController@loggednewPassword');
//Route::put('users/{id}','UserController@update');
//Route::delete('users/{id}','UserController@delete');

//Login
Route::post('login','LoginController@store');

//GetPhoto

//GoogleAPI
//GetPhoto
Route::get('getphoto','PhotoController@getPhoto');

//Nearby
Route::get('/nearby/restaurants','GoogleAPIController@getNearbyRestaurants');
Route::get('/nearby/cafes','GoogleAPIController@getNearbyCafes');
Route::get('/nearby/shops','GoogleAPIController@getNearbyShops');
Route::get('/nearby/attractions','GoogleAPIController@getNearbyAttractions');
//Nearby cities
Route::get('/nearby/cities','GoogleAPIController@getNearbyCities');


//Find attractions in specific place
Route::get('/attractions/parks','GoogleAPIController@getAttractionParks');
Route::get('/attractions/stadiums','GoogleAPIController@getAttractionStadiums');
Route::get('/attractions/pets','GoogleAPIController@getAttractionPets');
Route::get('/attractions/schools','GoogleAPIController@getAttractionSchools');
Route::get('/attractions/religions','GoogleAPIController@getAttractionReligions');
Route::get('/attractions/landmarks','GoogleAPIController@getAttractionLandmarks');

//Routes
Route::post('/route/new_route','GoogleAPIController@newRoute');
Route::post('/route/planned_route','GoogleAPIController@plannedRoute');