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

//USER
Route::post('user/create', 'UserController@create');
Route::post('google/create', 'GoogleController@create');
Route::get('user/getMyInfo', 'UserController@getMyInfo');
Route::get('user/setAvailability/{status}', 'UserController@setAvailability');
Route::post('user/setMood', 'UserController@setMood');

//FRIEND
Route::post('user/checkUserExists','UserController@checkUserExists');
Route::post('user/sendInvite', 'UserController@sendInvite');
Route::get('user/addFriend/{id}','UserController@addFriend');
Route::get('user/acceptFriend/{id}','UserController@acceptFriend');
Route::get('user/deleteFriend/{id}','UserController@deleteFriend');

//SESSION
Route::post('user/login', 'UserController@login');
Route::post('google/login', 'GoogleController@login');
Route::get('user/logout', 'UserController@logout');

//LIST
Route::get('user/getFriends', 'UserController@getFriends');
Route::get('user/getFriendsNow', 'UserController@getFriendsNow');
Route::get('user/getAvailable', 'UserController@getAvailable');
Route::get('user/getRequests', 'UserController@getRequests');
Route::get('user/getFriendsCount', 'UserController@getFriendsCount');

//SCHEDULE
Route::get('google/getCalendars', 'GoogleController@getCalendars');
Route::post('google/confirmCalendars', 'GoogleController@confirmCalendars');
Route::get('google/pullEvents/{id}', 'GoogleController@pullEvents');
Route::get('google/pullEvents', 'GoogleController@pullEvents');
Route::get('google/refreshToken/{id}', 'GoogleController@refreshToken');

Route::resource('user', 'UserController');
Route::resource('event', 'GoogleEventController');
Route::resource('google_calendar', 'GoogleCalendarController');
