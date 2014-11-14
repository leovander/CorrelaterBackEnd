<?php

/* TODO LIST:
	1. Favoriting 
	2. Nudge - Sending/receiving/accept or decline/some feedback to sender on nudge
	3. Set availability for a set period of time
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

                $availability = new Availability();
                $availability->user_id = Auth::user()->id;
                $availability->start_date = "0000-00-00";
                $availability->end_date = "0000-00-00";
                $availability->start_time = "00:00:00";
                $availability->end_time = "00:00:00";
                $availability->status = 1;
                $availability->save();
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

                $availability = new Availability();
                $availability->user_id = Auth::user()->id;
                $availability->start_date = "0000-00-00";
                $availability->end_date = "0000-00-00";
                $availability->start_time = "00:00:00";
                $availability->end_time = "00:00:00";
                $availability->status = 1;
                $availability->save();
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
					->subject("Welcome to Corral!");
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
        				->join('friends', 'users.id', '=', 'friends.friend_id')
	        			->select('users.id', 'users.first_name', 'users.last_name', 'friends.favorite')
	        			->orderBy('friends.favorite', 'desc')
						->orderBy('users.first_name', 'asc')
						->where('friends.user_id', '=', Auth::user()->id)
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
            //2-free, 1-schedule, 0-busy(invisible)
            $friendsTwo = DB::table('users')
                            ->join('friends', 'users.id', '=', 'friends.friend_id')
                            ->select('users.id', 'users.first_name', 'users.last_name', 'users.mood', 'friends.favorite')
                        	->orderBy('friends.favorite', 'desc')    
                            ->orderBy('users.first_name', 'asc')
                            ->where('friends.user_id', '=', Auth::user()->id)
                            ->where('friends.friend_status','=',1)
                            ->where('users.status', '=', 2)
                            ->get();

            $friendsOne = DB::table('users')
                            ->join('friends', 'users.id', '=', 'friends.friend_id')
                            ->select('user  s.id', 'users.first_name', 'users.last_name', 'users.mood', 'friends.favorite')
                            ->orderBy('friends.favorite', 'desc')
                            ->orderBy('users.first_name', 'asc')
                            ->where('friends.user_id', '=', Auth::user()->id)
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
                    ->join('friends', 'users.id', '=', 'friends.friend_id')
                    ->select('users.id', 'users.first_name', 'users.last_name', 'users.mood', 'friends.favorite')
                    ->orderBy('friends.favorite', 'desc')
                    ->orderBy('users.first_name', 'asc')
                    ->whereIn('users.id', $friendsOneAvailableId)
                    ->where('friends.user_id', '=', Auth::user()->id)
                    ->where('friends.friend_status','=',1)
                    ->where('users.status', '=', 1)
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

    public function getAvailableV2()
    {
        if (Auth::check()) {
            $today = date("Y-m-d");
            $now = date("H:i:s");
            $friendsOneAvailable = array();
            $allAvailableFriends = array();

            //Status Legend: 2-free, 1-schedule, 0-busy(invisible)
            //Status 2 with time limit
            $friendsTwo = DB::table('users')
                ->join('friends', 'users.id', '=', 'friends.friend_id')
                ->join('availabilities', 'users.id', '=', 'availabilities.user_id')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.mood', 'friends.favorite')
                ->orderBy('friends.favorite', 'desc')
                ->orderBy('users.first_name', 'asc')
                ->where('friends.user_id', '=', Auth::user()->id)
                ->where('friends.friend_status','=',1)
                ->where('availabilities.status', '=', 2)
                ->where('availabilities.start_date', '=', $today)
                ->where('availabilities.end_date', '=', $today)
                ->where('availabilities.start_time', '<=', $now)
                ->where('availabilities.end_time', '>=', $now)
                ->get();

            //Status 2 with no time limit
            $friendsTwoForever = DB::table('users')
                ->join('friends', 'users.id', '=', 'friends.friend_id')
                ->join('availabilities', 'users.id', '=', 'availabilities.user_id')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.mood', 'friends.favorite')
                ->orderBy('friends.favorite', 'desc')
                ->orderBy('users.first_name', 'asc')
                ->where('friends.user_id', '=', Auth::user()->id)
                ->where('friends.friend_status','=',1)
                ->where('availabilities.status', '=', 2)
                ->where('availabilities.start_date', '=', '0000-00-00')
                ->where('availabilities.start_time', '=', '00:00:00')
                ->get();

            //Status 1
            $friendsOne = DB::table('users')
                ->join('friends', 'users.id', '=', 'friends.friend_id')
                ->join('availabilities', 'users.id', '=', 'availabilities.user_id')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.mood', 'friends.favorite')
                ->orderBy('friends.favorite', 'desc')
                ->orderBy('users.first_name', 'asc')
                ->where('friends.user_id', '=', Auth::user()->id)
                ->where('friends.friend_status','=',1)
                ->where('availabilities.status', '=', 1)
                ->get();

            $busyFriends = array();
            if(!empty($friendsOne)) {
                $friendsOneId = array();
                foreach ($friendsOne as $friend) {
                    array_push($friendsOneId, $friend->id);
                }

                //find the busy friends among the friend with schedule
                $busyFriends = DB::table('events')
                    ->select('events.id', 'events.user_id')
                    ->whereIn('events.user_id', $friendsOneId)
                    ->where('events.start_date', '=', $today)
                    ->where('events.start_time', '<', $now)
                    ->where('events.end_time', '>', $now)
                    ->get();

                $busyFriendsId = array();
                foreach ($busyFriends as $people) {
                    array_push($busyFriendsId, $people->user_id);
                }

                //if busyFriend is empty, then friendOneAvailable = friendOne
                $friendsOneAvailableId = array_diff($friendsOneId, $busyFriendsId);

                if (!empty($friendsOneAvailableId)) {
                    //find only the available friends (schedule) based on the friendsOneAvailableId
                    $friendsOneAvailable = DB::table('users')
                        ->join('friends', 'users.id', '=', 'friends.friend_id')
                        ->select('users.id', 'users.first_name', 'users.last_name', 'users.mood', 'friends.favorite')
                        ->orderBy('friends.favorite', 'desc')
                        ->orderBy('users.first_name', 'asc')
                        ->whereIn('users.id', $friendsOneAvailableId)
                        ->where('friends.user_id', '=', Auth::user()->id)
                        ->where('friends.friend_status','=',1)
                        ->where('users.status', '=', 1)
                        ->get();
                }
            }

            if(!empty($friendsTwo)) {
                $allAvailableFriends = array_merge($friendsTwo);
            }
            if(!empty($friendsOneAvailable)) {
                $allAvailableFriends = array_merge($allAvailableFriends, $friendsOneAvailable);
            }
            if(!empty($friendsTwoForever)) {
                $allAvailableFriends = array_merge($allAvailableFriends, $friendsTwoForever);
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
		header('Content-type: application/json');
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
	
	public function setFavorite($friendId)
	{
		if(Auth::check()) {
			$ifFav = 0;
			$favorite = DB::table('friends')
				->where('user_id', '=', Auth::user()->id)
				->where('friend_id', '=', $friendId)
				->get();
			if($favorite[0]->favorite == 0) {
				$ifFav = 1;
				$response['message'] = 'Favorited';
			} else {
				$response['message'] = 'Not Favorited';
			}

			DB::table('friends')
				->where('user_id', '=', Auth::user()->id)
				->where('friend_id', '=', $friendId)
				->update(array('favorite' => $ifFav));
		}

		header('Content-type: application/json');
		return json_encode($response);
	}
	
	public function getNudges()
	{
		if(Auth::check()) {
			$nudges = DB::table('users')
        				->join('nudges', 'users.id', '=', 'nudges.sender_id')
						->join('friends', 'friends.user_id', '=', 'users.id')
	        			->select('nudges.message', 'users.first_name', 'users.last_name')
						->where('friends.friend_id', '=', Auth::user()->id)
						->where('nudges.receiver_id', '=', Auth::user()->id)
	        			->where('friends.friend_status','=',1)
						->get();
			$response['message'] = 'Success';
			$response['count'] = count($nudges);
			$response['friends'] = $nudges;
		}
		else {
			$response['message'] = 'Not Logged In';
		}

		header('Content-type: application/json');
		return json_encode($nudges);
	}

    public function setNudges() {
        $message = $_POST['message'];
        $receiverId = $_POST['receiver_id'];
        if(strlen($message) >= 50) {
            $message = substr($message, 0, 50);
        }

        if(Auth::check()) {
            $check = $this->isNudgeSet($receiverId);

            //if nudge exist from the sender to the user, update the message
            if($check["message"] === "Previously Set") {
                DB::table('nudges')
                    ->where('sender_id', '=', Auth::user()->id)
                    ->where('receiver_id', '=', $receiverId)
                    ->update(array('message' => $message));
                $response['message'] = 'Update nudge';
            //else save the nudge
            } else {
                $new_nudge = new Nudge();
                $new_nudge->sender_id = Auth::user()->id;
                $new_nudge->receiver_id = $receiverId;
                $new_nudge->message = $message;
                $new_nudge->save();
                $response['message'] = 'Set nudge';
            }
        }

        //header('Content-type: application/json');
        return json_encode($response);
    }

    public function deleteNudge($senderId) {
        if(Auth::check()) {
            DB::table('nudges')
                ->where('sender_id', '=', $senderId)
                ->where('receiver_id', '=', Auth::user()->id)
                ->delete() ;
            $response['message'] = 'Nudge Deleted';
        }
        header('Content-type: application/json');
        return json_encode($response);
    }

    //Check to see if user already sent nudge to the receiverId
    public function isNudgeSet($receiverId) {
        if (Auth::check()) {
            $nudge = DB::table('nudges')
                        ->select('sender_id', 'receiver_id')
                        ->where('sender_id', '=', Auth::user()->id)
                        ->where('receiver_id', '=', $receiverId)
                        ->take(1)
                        ->get();

            if(!empty($nudge)) {
                $response['message'] = 'Previously Set';
            } else {
                $response['message'] = 'Not Set';
            }
        }

        return $response;
    }

    public function setTimeAvailability () {
        $minutes = $_POST['time'];
        $status = $_POST['status'];

        if (Auth::check()) {
            DB::table('availabilities')
                ->where('user_id', '=', Auth::user()->id)
                ->delete();

            if ($minutes == 0) { //forever mode
                $startDate = date("0000-00-00");
                $startTime = date("00:00:00");
                $endDate = date("0000-00-00");
                $endTime = date("00:00:00");
            } else { //given minutes more than 0
                $startDate = date("Y-m-d");
                $startTime = date("H:i:s"); //now
                $temp = date("Y-m-d H:i:s", strtotime("+" . $minutes . " minutes"));
                $temp = explode(' ', $temp);
                $endDate = $temp[0];
                $endTime = $temp[1];
            }
            $availability = new Availability();
            $availability->user_id = Auth::user()->id;
            $availability->start_date = $startDate;
            $availability->end_date = $endDate;
            $availability->start_time = $startTime;
            $availability->end_time = $endTime;
            $availability->status = $status;
            $availability->save();
            $response['message'] = "Availability Time Set";

        } else {
            $response['message'] = "Not Logged In";
        }

        header('Content-type: application/json');
        return json_encode($response);
    }
	
	//TODO: to remove, this is for the http://e-wit.co.uk/correlate/submit.html page
	public function googleLogin(){
		if(Auth::attempt(array('email' => $_POST['email'], 'password' => $_POST['email']), true))
		{
			$response['message'] = 'Logged In';
			$response['user'] = Auth::user();
		} else {
			$response['message'] = 'Email or Password is incorrect';
		}

		header('Content-type: application/json');
		return json_encode($response);
	}
}
