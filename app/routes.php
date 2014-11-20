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
Route::post('facebook/create', 'FacebookController@create');
Route::get('user/getMyInfo', 'UserController@getMyInfo');
Route::get('user/setAvailability/{status}', 'UserController@setAvailability');
Route::post('user/setMood', 'UserController@setMood');
Route::post('user/setTimeAvailability', 'UserController@setTimeAvailability');

//FRIEND
Route::post('user/checkUserExists','UserController@checkUserExists');
Route::post('user/sendInvite', 'UserController@sendInvite');
Route::get('user/addFriend/{id}','UserController@addFriend');
Route::get('user/acceptFriend/{id}','UserController@acceptFriend');
Route::get('user/deleteFriend/{id}','UserController@deleteFriend');
Route::get('user/setFavorite/{id}','UserController@setFavorite');
Route::get('user/deleteNudge/{id}','UserController@deleteNudge');

//SESSION
Route::post('user/login', 'UserController@login');
Route::post('google/login', 'GoogleController@login');
Route::post('facebook/login', 'FacebookController@login');
Route::get('user/logout', 'UserController@logout');

//LIST
Route::get('user/getFriends', 'UserController@getFriends');
Route::get('user/getAvailableV2', 'UserController@getAvailableV2');
Route::get('user/getAvailableFuture', 'UserController@getAvailableFuture');
Route::get('user/checkAvailability/{id}', 'UserController@checkAvailability');
Route::get('user/getRequests', 'UserController@getRequests');
Route::get('user/getFriendsCount', 'UserController@getFriendsCount');
Route::get('user/getNudges', 'UserController@getNudges');
Route::post('user/setNudges', 'UserController@setNudges');

//SCHEDULE
Route::get('google/getCalendars', 'GoogleController@getCalendars');
Route::post('google/confirmCalendars', 'GoogleController@confirmCalendars');
Route::get('google/pullEvents/{id}', 'GoogleController@pullEvents');
Route::get('google/pullEvents', 'GoogleController@pullEvents');
Route::get('google/refreshToken/{id}', 'GoogleController@refreshToken');

//TODO
Route::post('user/googleLogin', 'UserController@googleLogin');
Route::get('facebook/getAccessToken', 'FacebookController@getAccessToken');

//CONTACTS
Route::get('google/getContacts', 'GoogleController@getContacts');

Route::resource('user', 'UserController');
Route::resource('event', 'GoogleEventController');
Route::resource('google_calendar', 'GoogleCalendarController');
