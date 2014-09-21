<?php

class UserController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		//
		$results = DB::select('select * from users');
		print('<pre>');
		print_r($results);
		print('</pre>');
	}


	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{		
		echo 'no go';
	}
	
	public function createAccount()
	{			
		$response = array();
		if($_POST['password']==$_POST['verify_password']){
			$users = User::where('email', '=', $_POST['email'])->take(1)->get();
			if($users->isEmpty()){
				$new_user = new User;
				$new_user->first_name = $_POST['first_name'];
				$new_user->last_name = $_POST['last_name'];
				$new_user->email = $_POST['email'];
				$new_user->password = Hash::make($_POST['password']);
				$new_user->save();
				$response['message'] = 'Account Created';
			}
			else $response['message'] = 'Email Taken';
		}
		else
		{
			$response['message'] = 'Password Mismatch';
		}
		
		header('Content-type: application/json');
		echo json_encode($response);
	}
	public function displayLog()
	{
		View::make('Login');
	}
	public function login(){
		$response = array();
		
		if (Auth::attempt(array('email' => $_POST['email'], 'password' => $_POST['password'])))
		{
			$response['message'] = Auth::user()->email;
			Redirect::to('user/getMyInfo');
		}
		else
		{
			$response['message'] = 'Email or Password is incorrect';		
		}
		
		header('Content-type: application/json');
		echo json_encode($response);
	}
	
	public function getMyInfo()
	{
		if(!Auth::check()) echo 'not logged in';
		
		else
		{
			$temp = Auth::user()->first_name;
			echo 'hello $temp';
		}
	}
	
	public function checkUserExists()
	{
		$response = array();
		
		$user = User::where('email','=',$_POST['email'])->get();
		if($user->isEmpty())
		{
			$response['message'] = 'E-mail Doesnt Exist';
		}		
		else
		{
			$response['user_info'] = $user;
		}
		header('Content-type: application/json');
		echo json_encode($response);
	}
	
	public function addFriend($createID, $sentID)
	{
		
		$requestedFriend = $_POST ['user_info'];
		
		$friendship = new Friend;
		$friendship->user_id = $createID;
		$friendship->friend_id = $sentID;
		$friendship->friend_status = 0;
		$friendship->save();
		
	}
	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	 
	public function acceptFriend($sentID, $createID)
	{
		$friendship = Friend::where('friend_id', '=', $sentID)
							->where('user_id','=',$createID)
							->update(array('friend_status' => 1));
							
		$newFriend = new Friend;
		$newFriend->user_id = $sentID;
		$newFriend->friend_id = $createID;
		$newFriend->friend_status = 1;
		$newFriend->save();
		
	}
	public function store()
	{
		//

	}


	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		//
	}


	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		//
	}


	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		//
	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		//
	}


}
