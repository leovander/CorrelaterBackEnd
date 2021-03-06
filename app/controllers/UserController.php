<?php

class UserController extends \BaseController {
    /**
     * precondition: email address has to be one that is not yet associated
     *               with any account in db.
     * postcondition: account created with given email, password, first_name,
     *               and last_name.
     * @return [json] [response]
     */
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

    /**
     * precondition: user must be a current user
     * postcondition: log  the user into the app
     * @return [json] [response]
     */
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

    /**
     * precondition: user must already log into the app
     * postcondition: return user's information, status, and whether
     *               the user is a Google or Facebook user
     * @return [json] [response]
     */
    public function getMyInfo()
    {
        if(Auth::check()) {
            $response['message'] = 'Logged In';
            $response['user'] = Auth::user();

            $temp = $this->checkAvailability(Auth::user()->id);
            $temp = json_decode($temp);
            $response['remaining_time'] = $temp->remaining_time;

            $status = DB::table('users')
                ->join('availabilities', 'users.id', '=', 'availabilities.user_id')
                ->select('availabilities.status')
                ->where('availabilities.user_id', '=', Auth::user()->id)
                ->get();
            $response['status'] = $status[0]->status;

            $google = DB::table('google_users')
                ->select('*')
                ->where('user_id', '=', Auth::user()->id)
                ->get();
            if (!empty($google[0])) {
                $response['google'] = 'Google User';
            }
            
            $facebook = DB::table('facebook_users')
                ->select('*')
                ->where('user_id', '=', Auth::user()->id)
                ->get();
            if (!empty($facebook[0])) {
                $response['facebook'] = 'Facebook User';
            }
        } else {
            $response['message'] = 'Not Logged In';
        }

        header('Content-type: application/json');
        return json_encode($response);
    }

    /**
     * precondition: valid email address
     * postcondition: if user found in db, return user's information
     * @return [json] [response]
     */
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

    /**
     * precondition: friendId must exist in db
     * postcondition: add friendship with status 0 (not confirmed)
     *               frienda = sender
     *               friendb = receiver
     * @param [int] $friendId [ID of requested friend]
     * @return [json] [response]
     */
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

    /**
     * precondition: user must be logged in
     *               $friendId must exist in db
     * postcondition: accept the friend request, set friendship status to 1
     * @param  [int] $friendId [ID of friend who send request]
     * @return [json] [response]
     */
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

    /**
     * precondition: user must be logged in
     *               $friendId must exist in db
     * postcondition: remove the friendship or reject a friend request
     * @param  [int] $friendId [ID of friend that friendship is to be deleted]
     * @return [json] [response]
     */
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

    /**
     * precondition: user must be logged in
     *               email address must be one not yet in db (non-user)
     * postcondition: email invitation is sent to the email address
     * @return [json] [response]
     */
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

    /**
     * precondition: user must be logged in
     * postcondition: log the user out of the app
     * @return [json] [response]
     */
    public function logout()
    {
        if(Auth::check()) {
            Auth::logout();
            $response['message'] = 'Logged Out';
        } else {
            $response['message'] = 'Not Logged In';
        }

        header('Content-type: application/json');
        return json_encode($response);
    }

    /**
     * precondition: user must be logged in
     * postcondition: return all friends' information and the number of friends
     * @return [json] [response]
     */
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

    /**
     * precondition: user must be logged in
     * postcondition: return the number of friends
     * @return [json] [response]
     */
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

    /**
     * precondition: user must be logged in
     * postcondition: return friends' info who submit friend request to the user
     * @return [json] [response]
     */
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


    /**
     * precondition: user must be logged in
     *               date and time must be in the future
     * postcondition: return list of friends who are available based on given date and time
     *               sorted by the favorite friends then by first name
     * @return [json] [response]
     */
    public function getAvailableFuture()
    {
        $date = $_POST['date'];
        $time = $_POST['time'];

        if (Auth::check())
        {
            $allAvailableFriends = array();
            $friendsOneAvailable = array();
            $busyFriends = array();

            $allConfirmedFriends = DB::table('users')
                ->join('friends', 'users.id', '=', 'friends.friend_id')
                ->join('availabilities', 'users.id', '=', 'availabilities.user_id')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.mood', 'friends.favorite')
                ->where('friends.user_id', '=', Auth::user()->id)
                ->where('friends.friend_status','=',1)
                ->get();

            //clean up the status in the availabilities table
            foreach ($allConfirmedFriends as $friend) {
                $this->checkAvailabilityHelper($friend->id);
            }

            //Status Legend: 2-free, 1-schedule, 0-busy(invisible)
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

            $friendsTwoForeverId = array();
            if(!empty($friendsTwoForever)) {
                foreach($friendsTwoForever as $friend) {
                    array_push($friendsTwoForeverId, $friend->id);
                }
            }

            //Status 0 with no time limit
            $friendsZeroForever = DB::table('users')
                ->join('friends', 'users.id', '=', 'friends.friend_id')
                ->join('availabilities', 'users.id', '=', 'availabilities.user_id')
                ->select('users.id')
                ->orderBy('friends.favorite', 'desc')
                ->orderBy('users.first_name', 'asc')
                ->where('friends.user_id', '=', Auth::user()->id)
                ->where('friends.friend_status','=',1)
                ->where('availabilities.status', '=', 0)
                ->where('availabilities.start_date', '=', '0000-00-00')
                ->where('availabilities.start_time', '=', '00:00:00')
                ->get();

            $friendsZeroForeverId = array();
            if (!empty($friendsZeroForever)) {
                foreach($friendsZeroForever as $friend) {
                    array_push($friendsZeroForeverId, $friend->id);
                }
            }

            //Status 1, ignore availability status due to projecting future
            if(empty($friendsTwoForeverId)) {
                $friendsOne = DB::table('users')
                    ->join('friends', 'users.id', '=', 'friends.friend_id')
                    ->select('users.id', 'users.first_name', 'users.last_name', 'users.mood', 'friends.favorite')
                    ->orderBy('friends.favorite', 'desc')
                    ->orderBy('users.first_name', 'asc')
                    ->where('friends.user_id', '=', Auth::user()->id)
                    ->where('friends.friend_status', '=', 1)
                    ->get();
            } else {
                $friendsOne = DB::table('users')
                    ->join('friends', 'users.id', '=', 'friends.friend_id')
                    ->select('users.id', 'users.first_name', 'users.last_name', 'users.mood', 'friends.favorite')
                    ->orderBy('friends.favorite', 'desc')
                    ->orderBy('users.first_name', 'asc')
                    ->whereNotIn('users.id', $friendsTwoForeverId)
                    ->where('friends.user_id', '=', Auth::user()->id)
                    ->where('friends.friend_status', '=', 1)
                    ->get();
            }

            if (!empty($friendsOne)) {
                $friendsOneId = array();
                foreach ($friendsOne as $friend) {
                    array_push($friendsOneId, $friend->id);
                }

                //find the busy friends among the friends with schedule
                $busyFriends = DB::table('events')
                    ->select('events.id', 'events.user_id')
                    ->whereIn('events.user_id', $friendsOneId)
                    ->where('events.start_date', '=', $date)
                    ->where('events.start_time', '<', $time)
                    ->where('events.end_time', '>', $time)
                    ->get();

                $busyFriendsId = array();
                foreach ($busyFriends as $friend) {
                    array_push($busyFriendsId, $friend->user_id);
                }

                //if busyFriend is empty, then friendOneAvailable = friendOne
                $friendsOneAvailableId = array_diff($friendsOneId, $busyFriendsId);

                //remove all friends who are status = 0 with no time limit
                $friendsOneAvailableId = array_diff($friendsOneAvailableId, $friendsZeroForeverId);

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
                        ->get();
                }
            }

            if(!empty($friendsTwoForever)) {
                $allAvailableFriends = array_merge($friendsTwoForever);
            }
            if(!empty($friendsOneAvailable)) {
                $allAvailableFriends = array_merge($allAvailableFriends, $friendsOneAvailable);
            }

            $favorite = array();
            $firstName = array();
            // Obtain a list of columns
            foreach ($allAvailableFriends as $key => $row) {
                $favorite[$key]  = $row->favorite;
                $firstName[$key] = strtolower($row->first_name); //case to lowercase for sorting
            }
            //sort by favorite then first name
            array_multisort($favorite, SORT_DESC, $firstName, SORT_ASC, $allAvailableFriends);

            if(!empty($allAvailableFriends)) {
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

    /**
     * precondition: user must be logged in
     * postcondition: return the list of friends who are available at the moment
     * @return [json] [response]
     */
    public function getAvailableV2()
    {
        if (Auth::check()) {
            $today = date("Y-m-d");
            $now = date("H:i:s");
            $friendsOneAvailable = array();
            $allAvailableFriends = array();

            $allConfirmedFriends = DB::table('users')
                ->join('friends', 'users.id', '=', 'friends.friend_id')
                ->join('availabilities', 'users.id', '=', 'availabilities.user_id')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.mood', 'friends.favorite')
                ->where('friends.user_id', '=', Auth::user()->id)
                ->where('friends.friend_status','=',1)
                ->get();

            foreach ($allConfirmedFriends as $friend) {
                $this->checkAvailabilityHelper($friend->id);
            }

            //Status Legend: 2-free, 1-schedule, 0-busy(invisible)
            //Status 2 with time limit
            $friendsTwo = DB::table('users')
                ->join('friends', 'users.id', '=', 'friends.friend_id')
                ->join('availabilities', 'users.id', '=', 'availabilities.user_id')
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.mood', 'friends.favorite', 'availabilities.end_date', 'availabilities.end_time')
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

            //adding the remaining time
            if(!empty($friendsTwo)) {
                foreach ($friendsTwo as $key => $val) {
                    $val->remaining = round((strtotime($val->end_date . " " . $val->end_time)
                            - strtotime(date("Y-m-d") . " " . $now)) / 60);
                }
            }

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
            if(!empty($friendsTwoForever)) {
                foreach ($friendsTwoForever as $key => $val) {
                    $val->remaining = 9999; //arbitrary large number
                }
            }

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
                        ->join('availabilities', 'users.id', '=', 'availabilities.user_id')
                        ->select('users.id', 'users.first_name', 'users.last_name', 'users.mood', 'friends.favorite')
                        ->orderBy('friends.favorite', 'desc')
                        ->orderBy('users.first_name', 'asc')
                        ->whereIn('users.id', $friendsOneAvailableId)
                        ->where('friends.user_id', '=', Auth::user()->id)
                        ->where('friends.friend_status','=',1)
                        ->where('availabilities.status', '=', 1)
                        ->get();

                    foreach($friendsOneAvailable as $key => $value) {
                        //find all events of friendsOneAvailable
                        $friendsOneEvents = DB::table('events')
                            ->select('events.id', 'events.user_id', 'events.start_date', 'events.start_time')
                            ->where('events.user_id', $value->id)
                            ->where('events.start_date', '=', $today)
                            ->where('events.start_time', '>', $now)
                            ->get();

                        $remainingTime = 1440;  //60min * 24hr
                        if(!empty($friendsOneEvents)) { //if there are events
                            foreach ($friendsOneEvents as $key => $val) {
                                $val->remain  = round((strtotime($val->start_date . " " . $val->start_time)
                                        - strtotime(date("Y-m-d") . " " . $now)) / 60);
                                if ($val->remain < $remainingTime) {
                                    $remainingTime = $val->remain;
                                }
                            }
                        } else {
                            $remainingTime = 8888; //status 1, but no events in DB arbitrary large number
                        }
                        $value->remaining = $remainingTime; //set the remaining time of friends one array.
                    }
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
			
			$favorite = array();
			$firstName = array();
            // Obtain a list of columns
            foreach ($allAvailableFriends as $key => $row) {
                $favorite[$key]  = $row->favorite;
                $firstName[$key] = strtolower($row->first_name); //case to lowercase for sorting
            }
            //sort by favorite then first name
            array_multisort($favorite, SORT_DESC, $firstName, SORT_ASC, $allAvailableFriends);
            
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

    /**
     * precondition: user must be logged in
     * postcondition: change user's availability status to schedule mode after
     *               the availability time of a given mode is expired,
     *               otherwise return the mode and the remaining time
     * @return [json] [response]
     */
    public function checkAvailability () {
        if(Auth::check()) {
            $response = $this->checkAvailabilityHelper(Auth::user()->id);
            $response = json_decode($response);
        }

        header('Content-type: application/json');
        return json_encode($response);
    }

    /**
     * precondition: $id must exist in db
     * postcondition: change user's availability status to schedule mode after
     *               the availability time of a given mode is expired,
     *               otherwise return the mode and the remaining time
     * @param  [int] $id [user ID]
     * @return [json] [response]
     */
    public function checkAvailabilityHelper ($id) {
        $availability = DB::table('availabilities')
            ->where('availabilities.user_id', '=', $id)
            ->get();

        $today = date("Y-m-d");
        $now = date("H:i:s");
        $remainingTime = 0;

        if ($availability[0]->start_date != "0000-00-00" && $availability[0]->start_time != "00:00:00") {
            if ($availability[0]->end_date < $today) {
                DB::table('availabilities')
                    ->where('availabilities.user_id', '=', $id)
                    ->update(array('start_date' => "0000-00-00",
                        'end_date' => "0000-000-00",
                        'start_time' => "00:00:00",
                        'end_time' => "00:00:00",
                        'status' => 1));
                $remainingTime = 0;
            } elseif ($availability[0]->end_date == $today) {
                if ($availability[0]->end_time <= $now) {
                    DB::table('availabilities')
                        ->where('availabilities.user_id', '=', $id)
                        ->update(array('start_date' => "0000-00-00",
                            'end_date' => "0000-000-00",
                            'start_time' => "00:00:00",
                            'end_time' => "00:00:00",
                            'status' => 1));
                    $remainingTime = 0;
                } else {
                    $remainingTime = round((strtotime($availability[0]->end_date." ".$availability[0]->end_time)
                            - strtotime($today." ".$now)) / 60);
                }
            }
        } else {
            $remainingTime = -1;
        }
        $availability = DB::table('availabilities')
            ->where('availabilities.user_id', '=', $id)
            ->get();

        $response['status'] = $availability[0]->status;
        $response['remaining_time'] = $remainingTime;

        header('Content-type: application/json');
        return json_encode($response);
    }

    /**
     * precondition: user must be logged in
     * postcondition: set the mood message of the user based on input
     */
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

    /**
     * precondition: user must be logged in
     *               $friendId must exist in db
     * postcondition: set the given friend based on ID as favorite friend
     * @param [int] $friendId [ID of friend to be set as favorite]
     * @return [json] [reponse]
     */
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

    /**
     * precondition: user must be logged in
     * postcondition: return all the nudges the are sent to the user
     * @return [json] [response]
     */
    public function getNudges()
    {
        if(Auth::check()) {
            $nudges = DB::table('users')
                ->join('nudges', 'users.id', '=', 'nudges.sender_id')
                ->join('friends', 'friends.user_id', '=', 'users.id')
                ->select('nudges.message', 'users.id','users.first_name', 'users.last_name')
                ->where('friends.friend_id', '=', Auth::user()->id)
                ->where('nudges.receiver_id', '=', Auth::user()->id)
                ->where('friends.friend_status','=',1)
                ->get();
            $response['message'] = 'Success';
            $response['count'] = count($nudges);
            $response['nudges'] = $nudges;
        }
        else {
            $response['message'] = 'Not Logged In';
        }

        header('Content-type: application/json');
        return json_encode($response);
    }

    /**
     * precondition: user must be logged in
     * postcondition: sent a nudge to a friend, with the given friendId and message
     * @return [json] [response]
     */
    public function setNudges() {
        $message = $_POST['message'];
        $receiverId = $_POST['receiver_id'];
        if(strlen($message) >= 50) {
            $message = substr($message, 0, 50);
        }

        if(Auth::check()) {
            $check = $this->isNudgeSet($receiverId);
            //if nudge exist from the sender to the user, update the message
            if($check["message"] == "Previously Set") {
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

    /**
     * precondition: user must be logged in
     * postcondition: delete the nudge
     * @param  [int] $senderId [ID of the friend who sent nudge]
     * @return [json] [response]
     */
    public function deleteNudge($senderId) {
        if(Auth::check()) {
            DB::table('nudges')
                ->where('sender_id', '=', $senderId)
                ->where('receiver_id', '=', Auth::user()->id)
                ->delete();
            $response['message'] = 'Nudge Deleted';
        }
        header('Content-type: application/json');
        return json_encode($response);
    }

    /**
     * precondition: user must be logged in
     * postcondition: check to see if user already sent nudge to the receiverId
     * @param  [int]  $receiverId [ID of the friend who receive nudge]
     * @return [Array] [response]
     */
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

    /**
     * precondition: user must be logged in
     * postcondition: set the user's availability based on the given date and time
     */
    public function setTimeAvailability () {
        $minutes = $_POST['time'];
        $status = $_POST['status'];

        if (Auth::check()) {
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

            DB::table('availabilities')
                ->where('user_id', '=', Auth::user()->id)
                ->update(array(
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'status' => $status
                ));

            $response['message'] = "Availability Time Set";
        } else {
            $response['message'] = "Not Logged In";
        }

        header('Content-type: application/json');
        return json_encode($response);
    }

    /**
     * precondition: user must have Google account, and user must be existing Corral app user
     * postcondition: allow user to log in
     * note: this is for the testing page: http://e-wit.co.uk/correlate/submit.html
     * @return [json] [response]
     */
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
