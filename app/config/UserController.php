<?php

/* TODO LIST:
 * Deny Friend Request
 * get a list of my friend's events and return if the ones who are free
 */

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
			$user = User::where('email', '=', $_POST['email'])->take(1)->get();
			if($user->isEmpty()) {
				$new_user = new User;
				$new_user->first_name = $_POST['first_name'];
				$new_user->last_name = $_POST['last_name'];
				$new_user->email = $_POST['email'];
				$new_user->password = Hash::make($_POST['password']);
				$new_user->valid = 1;
				$new_user->save();

				if(Auth::attempt(array('email' => $_POST['email']), true)) {
					$response['message'] = 'Account Created';
				}
			} else if($user[0]->valid == 0) {
				$user = User::find($user[0]->id);
				$user->first_name = $_POST['first_name'];
				$user->last_name = $_POST['last_name'];
				$user->password = Hash::make($_POST['password']);
				$user->valid = 1;
				$user->save();

				if(Auth::attempt(array('email' => $user[0]->email, 'password' => $user[0]->password), true)) {
					$response['message'] = 'Account Created';
				}
			} else {
				$response['message'] = 'Email Taken';
			}
		}
		else
		{
			$response['message'] = 'Password Mismatch';
		}

		header('Content-type: application/json');
		return json_encode($response);
	}

	public function login(){
		if(Auth::attempt(array('email' => $_POST['email'], 'password' => $_POST['password']), true))
		{
			$response['message'] = 'Logged In';
			$response['user'] = Auth::user();
		} else {
			$response['message'] = 'Email or Password is incorrect';
		}

		header('Content-type: application/json');
		return json_encode($response);
	}

	public function getMyInfo()
	{
		if(Auth::check()) {
			$response['message'] = 'Logged In';
			$response['user'] = Auth::user();
		} else {
			$response['message'] = 'Not Logged In';
		}

		header('Content-type: application/json');
		return json_encode($response);
	}

	//Check if friend with given email exists
	public function checkUserExists()
	{
		$friend = User::where('email','=',$_POST['email'])->get();
		if($friend->isEmpty())
		{
			$response['message'] = 'User Not Found';
			$response['email'] = $_POST['email'];
			//next call should be addFriend() as temporary and sendInvite()
		} else {
			$isFriend = DB::table('users')
    					->leftJoin('friends', 'users.id', '=', 'friends.user_id')
    						->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'users.valid')
						->where('friends.friend_id', '=', Auth::user()->id)
						->where('users.id', '=', $friend[0]->id)
						->where('friends.friend_status', '=', 1)
						->get();

			if(empty($isFriend)) {
				$response['message'] = 'Not Friends';
			} else {
				$response['message'] = 'Already Friends';
			}

			$response['user'] = $friend[0];
		}
		header('Content-type: application/json');
		return json_encode($response);
	}

	//Call this if user_info was returned from checkUserExists()
	//frienda is the sender, friendb is the receiver
	public function addFriend($friendId)
	{
		if(Auth::check()) {
			$frienda = new Friend;
			$frienda->user_id = Auth::user()->id;
			$frienda->friend_id = $friendId;
			$frienda->friend_status = 0;
			$frienda->sender_id = Auth::user()->id;

			$friendb = new Friend;
			$friendb->user_id = $friendId;
			$friendb->friend_id = Auth::user()->id;
			$friendb->friend_status = 0;

			if($frienda->save() && $friendb->save() ) {
				$response['message'] = 'Request Sent';
			} else {
				$response['message'] = 'Request Failed';
			}
		}

		header('Content-type: application/json');
		return json_encode($response);
	}

	//accepts a friend request
	public function acceptFriend($friendId)
	{
		if(Auth::check()) {
			Friend::where('user_id','=',$friendId)
					->where('friend_id', '=', Auth::user()->id)
					->update(array('friend_status' => 1));

			Friend::where('user_id','=',Auth::user()->id)
					->where('friend_id', '=', $friendId)
					->update(array('friend_status' => 1));

			$response['message'] = 'Friend Accepted';
		}

		header('Content-type: application/json');
		return json_encode($response);
	}

	//Send email invitation for non-user
	public function sendInvite()
	{
		if(Auth::check()) {
			$user = User::where('email', '=', $_POST['email'])->take(1)->get();

			if($user->isEmpty()){
				$new_user = new User;
				$new_user->email = $_POST['email'];
				if($new_user->save()) {
					$this->addFriend($new_user->id);
				}
			}

			$data = array('first_name' => Auth::user()->first_name, 'email' => Auth::user()->email);
			Mail::queue('emails.test', $data, function($message) {
				$message->to($_POST['email'])
					->subject("Welcome to I'm Free!");
			});
			$response['message'] = 'Sent';
		}

		header('Content-type: application/json');
		return json_encode($response);
	}

	public function logout()
	{
		Auth::logout();
		header('Content-type: application/json');
		$response['message'] = 'Logged Out';
		return json_encode($response);
	}

	public function getFriends()
	{
		if(Auth::check()) {
			$friends = DB::table('users')
        				->join('friends', 'users.id', '=', 'friends.user_id')
	        			->select('users.id', 'users.first_name', 'users.last_name')
						->where('friends.friend_id', '=', Auth::user()->id)
	        			->where('friends.friend_status','=',1)
						->get();
			$response['message'] = 'Success';
			$response['count'] = count($friends);
			$response['friends'] = $friends;
		}
		else {
			$response['message'] = 'Not Logged In';
		}

		header('Content-type: application/json');
		return json_encode($response);
	}

	//Return only friends who sent request to the user.
	public function getRequests()
	{
		if(Auth::check()) {
			$requests = DB::table('users')
        				->join('friends', 'users.id', '=', 'friends.user_id')
	        			->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
						->orderBy('users.first_name', 'asc')
						->where('friends.friend_id', '=', Auth::user()->id)
	        			->where('friends.friend_status','=',0)
						->where('friends.sender_id', '!=', Auth::user()->id)
						->get();
			$response['message'] = 'Success';
			$response['count'] = count($requests);
			$response['friends'] = $requests;
		}
		else {
			$response['message'] = 'Not Logged In';
		}

		header('Content-type: application/json');
		return json_encode($response);
	}


	public function getAvailable()
	{
		$myFriends = $this->getFriends();

		$busyPeople = DB::table('users')
        				->join('events', 'users.id', '=', 'events.user_id')
	        			->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
						->orderBy('users.first_name', 'asc')
						->where('events.start_time', '<', time())
	        			->where('events.end_time', '>', time())
	        			->get();

		$available = $myFriends->diff($busyPeople);

		//foreach($myFriends as $person){
		//	if($busyPeople.contains

		$response['message'] = 'Success';
		$response['count'] = count($requests);
		$response['friends'] = $requests;

		header('Content-type: application/json');
		return json_encode($response);


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
