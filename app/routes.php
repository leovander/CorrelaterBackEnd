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
Route::get('user/addFriend/{myid}/{hisid}','UserController@addFriend');
Route::get('user/acceptFriend/{hisid}/{myid}','UserController@acceptFriend');
Route::get('user/getMyInfo','UserController@getMyInfo');
Route::post('login', 'UserController@displayLog');

//GOOOGLE ROUTES
Route::post('google/createWithGoogleAccount', 'GoogleController@createWithGoogleAccount');
Route::get('google/show/{id}', 'GoogleController@show');

Route::resource('user', 'UserController');
Route::resource('event', 'EventController');