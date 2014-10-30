<?php

/* TODO LIST:
 * 1. Get a list of my friend's events and return if the ones who are free
 * 2. Multiple invites: parse and see if they are in requests or email invites
 * 4. Deny Friend Request
 */

class UserController extends \BaseController {
	public function create()
	{
		$response = array();
		$user = User::where('email', '=', $_POST['email'])->take(1)->get();
		if($user->isEmpty()) {
			$new_user = new User;
			$new_user->first_name = $_POST['first_name'];
			$new_user->last_name = $_POST['last_name'];
			$new_user->email = $_POST['email'];
			$new_user->password = Hash::make($_POST['password']);
			$new_user->valid = 1;
			$new_user->save();

			$credentials = array(
			    'email' => $_POST['email'],
			    'password' => $_POST['password']
			);
			
			if(Auth::attempt($credentials, true)) {
			    $response['message'] = 'Account Created';
			}
		} else if($user[0]->valid == 0) {
			$user = User::find($user[0]->id);
			$user->first_name = $_POST['first_name'];
			$user->last_name = $_POST['last_name'];
			$user->password = Hash::make($_POST['password']);
			$user->valid = 1;
			$user->save();

			$credentials = array(
			    'email' => $user[0]->email,
			    'password' => $_POST['password']
			);
			
			if (Auth::attempt($credentials, true)) {
			    $response['message'] = 'Account Created';
			}
		} else if($user[0]->valid == 1) {
			$response['message'] = 'Email Taken';
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
			$response['message'] = 'Email or Password Incorrect';
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
	
	//Delete/Reject a friend/friend request
	public function deleteFriend($friendId)
	{
		if(Auth::check()) {
			DB::table('friends')
				->where('user_id','=',$friendId)
				->where('friend_id', '=', Auth::user()->id)
				->delete();

			DB::table('friends')
				->where('user_id','=',Auth::user()->id)
				->where('friend_id', '=', $friendId)
				->delete();

			$response['message'] = 'Friend Deleted';
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

			$data = array('first_name' => ucfirst(Auth::user()->first_name), 'email' => Auth::user()->email);
			Mail::queue('emails.invite', $data, function($message) {
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
	        			->orderBy('users.first_name', 'asc')
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
	
	public function getFriendsCount() {
		if(Auth::check()) {
			$count = Friend::where('user_id', '=', Auth::user()->id)
						   ->where('friend_status', '=', 1)
						   ->count();
			$response['message'] = 'Success';
			$response['count'] = $count;			
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
        if (Auth::check()) {
            //2-free, 1-schedule, 1-busy(invisible)
            $friendsTwo = DB::table('users')
                            ->join('friends', 'users.id', '=', 'friends.user_id')
                            ->select('users.id', 'users.first_name', 'users.last_name', 'users.mood')
                            ->orderBy('users.first_name', 'asc')
                            ->where('friends.friend_id', '=', Auth::user()->id)
                            ->where('friends.friend_status','=',1)
                            ->where('users.status', '=', 2)
                            ->get();

            $friendsOne = DB::table('users')
                            ->join('friends', 'users.id', '=', 'friends.user_id')
                            ->select('users.id', 'users.first_name', 'users.last_name', 'users.mood')
                            ->orderBy('users.first_name', 'asc')
                            ->where('friends.friend_id', '=', Auth::user()->id)
                            ->where('friends.friend_status','=',1)
                            ->where('users.status', '=', 1)
                            ->get();

            $friendsOneId = array();

            foreach ($friendsOne as $friend) {
                array_push($friendsOneId, $friend->id);
            }

            $today = date("Y-m-d");
            $now = date("H:i:s");
            $busyFriendsId = array();
            
            if (!empty($friendsOneId)) {
                $busyFriends = DB::table('events')
                    ->select('events.id', 'events.user_id')
                    ->whereIn('events.user_id', $friendsOneId)
                    ->where('events.start_date', '=', $today)
                    ->where('events.start_time', '<', $now)
                    ->where('events.end_time', '>', $now)
                    ->get();
                    
                foreach ($busyFriends as $people) {
                    array_push($busyFriendsId, $people->user_id);
                }
            }

            $friendsOneAvailableId = array_diff($friendsOneId, $busyFriendsId);

            if (!empty($friendsOneAvailableId)) {
                //find only the available friends (schedule) based on the friendsOneAvailableId
                $friendsOneAvailable = DB::table('users')
                    ->select('users.id', 'users.first_name', 'users.last_name', 'users.mood')
                    ->whereIn('users.id', $friendsOneAvailableId)
                    ->get();

                $allAvailFriends = array();

                $allAvailableFriends = array_merge($friendsTwo, $friendsOneAvailable);
            } else {
	            $allAvailableFriends = $friendsTwo;
            }

            if (!empty($allAvailableFriends)) {
                $response['message'] = 'Success';
                $response['count'] = count($allAvailableFriends);
                $response['friends'] = $allAvailableFriends;
            } else {
                $response['message'] = 'Fail';
                $response['count'] = 0;
                $response['friends'] = "";
            }

        }



//		header('Content-type: application/json');
		return json_encode($response);

	}
	
	public function getFriendsNow()
	{
		if(Auth::check()) {
			$friends = DB::table('users')
        				->join('friends', 'users.id', '=', 'friends.user_id')
	        			->select('users.id', 'users.first_name', 'users.last_name', 'users.mood')
	        			->orderBy('users.first_name', 'asc')
						->where('friends.friend_id', '=', Auth::user()->id)
	        			->where('friends.friend_status','=',1)
	        			->whereIn('users.status', array(1, 2))
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
	
	//2 makes the user available no matter what, 1 uses the times in schedule, and 0 doesn't show the current user at all
	public function setAvailability($status)
	{
		if(Auth::check())
		{
			if($status == 1){ //based on schedule
				DB::table('users')
					->where('id', '=', Auth::user()->id)
					->update(array('status' => 1));
	        }
			else if($status == 0) //invisible
			{
				DB::table('users')
					->where('id', '=', Auth::user()->id)
					->update(array('status' => 0));
			}
			else //completely free
			{
				DB::table('users')
					->where('id', '=', Auth::user()->id)
					->update(array('status' => 2));
			}
		}
		
		$response['message'] = "Availability Set";
		header('Content-type: application/json');
		return json_encode($response);
	}
	
	public function setMood()
	{
		$mood = $_POST['mood'];
		
		if(strlen($mood) >= 50)
			$mood = substr($mood, 0, 50);
		
		if(Auth::check())
		{
			DB::table('users')
				->where('id', '=', Auth::user()->id)
				->update(array('mood' => $mood));
				
			$response['message'] = 'Mood Set';
		} else {
			$response['message'] = 'Mood Not Set';
		}
		
		header('Content-type: application/json');
		return json_encode($response);
	}
}
