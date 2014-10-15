<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

Route::get('/', 'HomeController@showWelcome');

//USER ROUTES
Route::post('user/createAccount', 'UserController@createAccount');
Route::post('user/login', 'UserController@login');
Route::post('user/googleLogin', 'UserController@googleLogin');
Route::get('user/logout', 'UserController@logout');
Route::get('user/getFriendsNow', 'UserController@getFriendsNow');
Route::get('user/getMyInfo', 'UserController@getMyInfo');
Route::get('user/show/{id}', 'UserController@show');

Route::post('user/checkUserExists','UserController@checkUserExists');
Route::get('user/addFriend/{id}','UserController@addFriend');
Route::get('user/acceptFriend/{id}','UserController@acceptFriend');
Route::post('user/sendInvite', 'UserController@sendInvite');
Route::get('user/getRequests', 'UserController@getRequests');
Route::get('user/getFriends', 'UserController@getFriends');

//GOOGLE ROUTES
Route::post('google/createWithGoogleAccount', 'GoogleController@createWithGoogleAccount');
Route::get('google/refreshGoogleAccessToken/{id}', 'GoogleController@refreshGoogleAccessToken');
Route::get('google/isValidGoogleToken/{id}', 'GoogleController@isValidGoogleToken');
Route::get('google/getCalendars/{id}', 'GoogleController@getCalendars');
Route::get('google/pullEvents/{id}', 'GoogleController@pullEvents');
Route::get('google/pullAllEvents/{id}', 'GoogleController@pullAllEvents');

Route::resource('user', 'UserController');
Route::resource('event', 'GoogleEventController');
Route::resource('google_calendar', 'GoogleCalendarController');