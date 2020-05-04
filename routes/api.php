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
Route::get('user/stats','UserController@userStats');
Route::post('users','UserController@store');
Route::post('users/verify','UserController@verify');
Route::post('users/forgottenPassword','UserController@forgottenPassword');
Route::post('users/resetCode','UserController@resetCode');
Route::post('users/newPassword','UserController@newPassword');
Route::post('users/loggedNewEmail','UserController@newEmail');
Route::post('users/loggedNewPassword','UserController@loggedNewPassword');

//Login
Route::post('login','LoginController@store');


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
Route::post('/route/plan_route','RouteController@planRoute');
Route::post('/route/start_route','RouteController@startRoute');
Route::post('/route/finish_route','RouteController@finishRoute');
Route::get('/route/planned_routes/{id}','RouteController@getPlannedRoutes');
Route::get('/route/started_routes/{id}','RouteController@getStartedRoutes');
Route::get('/route/finished_routes/{id}','RouteController@getFinishedRoutes');
Route::get('/route/specific_route/{id}','RouteController@getSpecificRoute');
Route::get('/route/suggested_route/{place}','RouteController@getSuggestedRoutes');

//Place
Route::get('/place/{id}','RouteController@getPlaceDescription');