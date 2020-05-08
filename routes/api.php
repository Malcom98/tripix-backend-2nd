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

//ENDPOINT: user
Route::post('user/login','LoginController@checkLogin');
Route::post('user/register','UserController@saveUser');
Route::get('user/stats','UserController@getUserStats');
Route::post('user/verify','UserController@verifyEmailAddress');
Route::post('user/password/forgot','UserController@sendForgottenPasswordCode');
Route::post('user/password/code','UserController@checkResetPasswordCode');
Route::post('user/password/new','UserController@setNewPassword');
Route::post('user/change-email','UserController@changeLoggedUserEmailAdress');
Route::post('user/change-password','UserController@changeLoggedUserPassword');

//ENDPOINT: nearby
Route::get('nearby/restaurants','GoogleAPIController@getNearbyRestaurants');
Route::get('nearby/cafes','GoogleAPIController@getNearbyCafes');
Route::get('nearby/shops','GoogleAPIController@getNearbyShops');
Route::get('nearby/attractions','GoogleAPIController@getNearbyAttractions');
Route::get('nearby/cities','GoogleAPIController@getNearbyCities');

//ENDPOINT: attractions
Route::get('attractions/parks','GoogleAPIController@getAttractionParks');
Route::get('attractions/stadiums','GoogleAPIController@getAttractionStadiums');
Route::get('attractions/pets','GoogleAPIController@getAttractionPets');
Route::get('attractions/schools','GoogleAPIController@getAttractionSchools');
Route::get('attractions/religions','GoogleAPIController@getAttractionReligions');
Route::get('attractions/landmarks','GoogleAPIController@getAttractionLandmarks');

//ENDPOINT: photo
Route::get('photo','PhotoController@getPhoto');

//ENDPOINT: route
Route::post('route/new','RouteController@newRoute');
Route::post('route/plan','RouteController@planRoute');
Route::post('route/start','RouteController@startRoute');
Route::post('route/finish','RouteController@finishRoute');
Route::get('route/planned','RouteController@getPlannedRoutes');
Route::get('route/started','RouteController@getStartedRoutes');
Route::get('route/finished','RouteController@getFinishedRoutes');
Route::get('route/{id}','RouteController@getSpecificRoute');
Route::get('route/suggested/{place}','RouteController@getSuggestedRoutes');
Route::post('route-item/completed','RouteController@completeRouteWaypoint');

//ENDPOINT: place
Route::get('place/{id}','RouteController@getPlaceDescription');