<?php

class GoogleController extends \BaseController
{
	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create() {
		$response = array();
		if(isset($_POST['google_access_token'])) {
			$profile = $this->getProfile($_POST['google_access_token']);

            if($profile !== false) {
                $user = User::where('email', '=', $profile->email)->take(1)->get();
                if ($user->isEmpty()) {
                    $new_user = new User;
                    $new_user->google_access_token = $_POST['google_access_token'];
                    $new_user->google_refresh_token = $_POST['google_refresh_token'];
                    $new_user->google_id_token = $_POST['google_id_token'];
                    $new_user->google_code = $_POST['google_code'];
                    $new_user->email = $profile->email;
                    $new_user->password = Hash::make($profile->email);
                    $new_user->first_name = $profile->given_name;
                    $new_user->last_name = $profile->family_name;
                    $new_user->google_id = $profile->id;
                    $new_user->valid = 1;
                    $new_user->save();

	                $credentials = array(
					  'email' => $profile->email,
					  'password' => $profile->email
					);
	
					if(Auth::attempt($credentials, true)) {
					    $response['message'] = 'Account Created';
					} else {
						$response['message'] = 'Could Not Login';
					}		
                } else {
	                if($user[0]->valid == 0) {
		            	$new_user = User::find($user[0]->id);
		            	$new_user->google_access_token = $_POST['google_access_token'];
	                    $new_user->google_refresh_token = $_POST['google_refresh_token'];
	                    $new_user->google_id_token = $_POST['google_id_token'];
	                    $new_user->google_code = $_POST['google_code'];
	                    $new_user->password = Hash::make($profile->email);
	                    $new_user->first_name = $profile->given_name;
	                    $new_user->last_name = $profile->family_name;
	                    $new_user->google_id = $profile->id;
	                    $new_user->valid = 1;
	                    $new_user->save();
	
		                $credentials = array(
						  'email' => $new_user->email,
						  'password' => $new_user->email
						);
		
						if(Auth::attempt($credentials, true)) {
						    $response['message'] = 'Account Created';
						} else {
							$response['message'] = 'Could Not Login';
						}
	                } else if($user[0]->valid == 1) {
		            	$response['message'] = 'Email Taken';   
	                }
                }
            } else {
                $response['message'] = 'Profile not created';
            }
        }

        header('Content-type: application/json');
        return json_encode($response);
    }
    
    public function login(){
		if(isset($_POST['google_access_token'])) {
			$profile = $this->getProfile($_POST['google_access_token']);

            if($profile !== false) {
				if(Auth::attempt(array('email' => $profile->email, 'password' => $profile->email), true))
				{
		            $id = Auth::user()->id;
		            $user = User::find($id);
					
					$user->google_access_token = $_POST['google_access_token'];
		            $user->google_refresh_token = $_POST['google_refresh_token'];
		            $user->google_id_token = $_POST['google_id_token'];
		            $user->google_code = $_POST['google_code'];
		            $user->save();
					
					$response['message'] = 'Logged In';
					$response['user'] = Auth::user();
				} else {
					$response['message'] = 'Email or Password is incorrect';
				}
			}
		}

		header('Content-type: application/json');
		return json_encode($response);
	}

	public function getProfile($access_token) {
		$request_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
		$profile = file_get_contents($request_url.'?access_token='.$access_token);

        if ($profile === false) {
            return false;
        } else {
            $profile = json_decode($profile);
        }

        return $profile;
    }

    //Helper function: Get Google Token Info for expiration time
    public function isValidToken($id)
    {
        $user = User::find($id);

        $request_url = 'https://www.googleapis.com/oauth2/v1/tokeninfo';
        $profile = json_decode(file_get_contents($request_url . '?access_token=' . $user->google_access_token));

        if ($profile === false) {
            return false;
        } else {
            //refresh the token if expiration time is less than 10 mins (600 secs)
            if ((int)$profile->expires_in < 600) {
                if ($this->refreshToken($id)) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return true;
            }
        }
    }

    //Helper function: Refresh Google Access Token when the old Access Token expired
    public function refreshToken($id)
    {
        $user = User::find($id);
        $settings = Setting::where('source', '=', 'google')->get();

        $data = array('client_id' => $settings[0]->client_id,
            'refresh_token' => $user->google_refresh_token,
            'grant_type' => 'refresh_token');

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://accounts.google.com/o/oauth2/token');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = json_decode(curl_exec($ch));

//        if (curl_getinfo($ch)['http_code'] == '200') {
            $user->google_access_token = $response->access_token;
            $user->google_id_token = $response->id_token;
            $user->save();
            curl_close($ch);
//			return true;
//        } else {
//        	curl_close($ch);
//			return false;
//        }
    }

    function get_my_url_contents($url)
    {
        $crl = curl_init();
        $timeout = 5;
        curl_setopt($crl, CURLOPT_URL, $url);
        curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
        $ret = curl_exec($crl);
        curl_close($crl);
        return $ret;
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return Response
     */

    public function getCalendars()
    {
        if(Auth::check()) {
            $id = Auth::user()->id;
            $user = User::find($id);

            //check to make sure the user access token is still valid
            $ch = curl_init('https://www.googleapis.com/calendar/v3/users/me/calendarList?access_token=' . $user->google_access_token);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $calendars = curl_exec($ch);
            $calendars = json_decode($calendars);

            //if the user access token is not valid - 401 "Invalid Credentials" refresh the access token
            if (isset($calendars->error->code)) {
                if ($calendars->error->code == '401') {
                    $this->refreshToken($id);
                }
            }
            $calendars = (array)json_decode(file_get_contents('https://www.googleapis.com/calendar/v3/users/me/calendarList?access_token=' . $user->google_access_token));
        }

        $cal_ids = array();
        if ($calendars !== false) {
            foreach ($calendars['items'] as $calendar) {
                if ($calendar->accessRole == "owner") {
                    array_push($cal_ids, array('id' => $calendar->id, 'user_id' => $id,'name' => $calendar->summary));
                }
            }
        }
        $response['calendars'] = $cal_ids;
        //header('Content-type: application/json');
        return json_encode($response);
    }
    
    //User has selected x,y,z calendars
    public function confirmCalendars() {
        if(Auth::check()) {
            $id = Auth::user()->id;
            $user = User::find($id);
            $confirmedCals = array();
            $calIdInDb = array();

            $calendarCurrents = DB::table('google_calendar')
                ->where('user_id', '=', $id)
                ->get();

            //push only the calendar id into an array.
            foreach ($calendarCurrents as $calendarCurrent) {
                array_push($calIdInDb, $calendarCurrent->id);
            }

            array_push($confirmedCals, $_POST['id']); //array of calendar id
            foreach ($confirmedCals[0] as $cal) {
                if (!in_array($cal, $calIdInDb)) {
                    $new_cal = new GoogleCalendar;
                    $new_cal->id = $cal;
                    $new_cal->user_id = $user->id;
                    $new_cal->save();
                    //sync token to be saved later when pullEvents get called
                }
            }
            $response['message'] = 'Calendar confirmation succeed';
        } else {
            $response['message'] = 'Calendar confirmation fail';
        }

        //header('Content-type: application/json');
        return json_encode($response);
    }

	//Can be called with assumption that getCalendars ran successfully
    public function pullEvents()
    {
        if(Auth::check()) {
            $id = Auth::user()->id;
            $user = User::find($id);

            $eventIdInDb = array();
            $calIdInDb = array();
            $cancelledEvents = array();
            $eventsToUpdate = array();
            $calendarArray = array();

            $eventDataInDb = DB::table('events')
                ->select('id')
                ->where('user_id', '=', $id)
                ->get();

            $calendarCurrents = DB::table('google_calendar')
                ->where('user_id', '=', $id)
                ->get();

            //push only the calendar id into an array.
            foreach ($calendarCurrents as $calendarCurrent) {
                array_push($calIdInDb, $calendarCurrent->id);
            }

            //push only the events id into an array
            foreach ($eventDataInDb as $eData) {
                array_push($eventIdInDb, $eData->id);
            }

            //to check if user delete an entire calendar from google
            $calendars = $this->getCalendars($id);
            $googleCalendars = json_decode($calendars);

            //push only the calendar id
            $googleCalIds = array();
            foreach ($googleCalendars->calendars as $cal) {
                array_push($googleCalIds, $cal->id);
            }

            $startDate = date("Y-m-d");
            $startDate = $startDate . 'T00:00:00Z';
            $today = strtotime((date("Y-m-d")));
            $endDate = date("Y-m-d", strtotime("+1 month", $today));
            $endDate = $endDate . 'T23:59:59Z';


            if (empty($eventIdInDb)) {
                $this->pullAllEvents($id);
            } else {
                //compare db against google return value
                foreach ($calendarCurrents as $calendarCurrent) {
                    // if calendar in both google return and db, check for new events in existing calendar(s)
                    if (in_array($calendarCurrent->id, $googleCalIds)) {
                        if (!isset($calendarCurrent->sync_token)) { //user add new calendar, pull events
                            $events = (array)json_decode(file_get_contents('https://www.googleapis.com/calendar/v3/calendars/' . $calendarCurrent->id
                                . '/events?singleEvents=true&access_token=' . $user->google_access_token
                                . '&timeMin=' . $startDate
                                . '&timeMax=' . $endDate));

                            $syncToken = null;
                            if (isset($events['nextPageToken'])) {
                                $flag = true;
                                while ($flag) {
                                    $events = (array)json_decode(file_get_contents('https://www.googleapis.com/calendar/v3/calendars/' . $calendarCurrent->id
                                        . '/events?singleEvents=true&access_token=' . $user->google_access_token
                                        . '&timeMin=' . $startDate . '&timeMax=' . $endDate
                                        . '&pageToken=' . $events['nextPageToken']));
                                    if (isset($events['nextPageToken'])) {
                                        $flag = true;
                                    } else {
                                        $flag = false;
                                        $calendarCurrent->sync_token = $events['nextSyncToken'];
                                    }
                                    $this->saveEventsArray($events, $calendarCurrent, $user, $eventsToUpdate);
                                }
                            } else {
                                $calendarCurrent->sync_token = $events['nextSyncToken'];
                            }
                            $gCalendar = GoogleCalendar::find($calendarCurrent->id);
                            $gCalendar->sync_token = $calendarCurrent->sync_token;
                            $gCalendar->save();

                            $this->saveEventsArray($events, $calendarCurrent, $user, $eventsToUpdate);

                        } else { //update or delete events
                            //check to make sure the user sync token is still valid
                            $ch = curl_init('https://www.googleapis.com/calendar/v3/calendars/' . $calendarCurrent->id
                                . '/events?singleEvents=true&access_token=' . $user->google_access_token
                                . '&syncToken=' . $calendarCurrent->sync_token);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            $events = curl_exec($ch);
                            $events = json_decode($events);

                            //if the sync token is expired - 410, delete all events from db and pull again
                            if (isset($events->error->code)) {
                                if ($events->error->code == '410') {
                                    $this->pullAllEvents($id);
                                }
                            } else { //else check for updated/deleted events
                                $events = (array)json_decode(file_get_contents('https://www.googleapis.com/calendar/v3/calendars/' . $calendarCurrent->id
                                    . '/events?singleEvents=true&access_token=' . $user->google_access_token
                                    . '&syncToken=' . $calendarCurrent->sync_token));

                                if (isset($events['nextPageToken'])) {
                                    $flag = true;
                                } else {
                                    $flag = false;
                                    $calendarCurrent->sync_token = $events['nextSyncToken'];
                                }
                                if (!empty($events)) {
                                    foreach ($events['items'] as $event) {
                                        //an array of cancelled Event to check for moved events vs. really cancelled event
                                        if ($event->status === "cancelled") {
                                            array_push($cancelledEvents, $event->id);
                                        }
                                    }
                                }
                                $this->saveEventsArray($events, $calendarCurrent, $user, $eventsToUpdate);

                                while ($flag) {
                                    $events = (array)json_decode(file_get_contents('https://www.googleapis.com/calendar/v3/calendars/' . $calendarCurrent->id
                                        . '/events?singleEvents=true&access_token=' . $user->google_access_token
                                        . '&timeMin=' . $startDate . '&timeMax=' . $endDate
                                        . '&pageToken=' . $events['nextPageToken']));
                                    if (isset($events['nextPageToken'])) {
                                        $flag = true;
                                    } else {
                                        $flag = false;
                                        $calendarCurrent->sync_token = $events['nextSyncToken'];
                                    }
                                    if (!empty($events)) {
                                        foreach ($events['items'] as $event) {
                                            //an array of cancelled Event to check for moved events vs. really cancelled event
                                            if ($event->status === "cancelled") {
                                                array_push($cancelledEvents, $event->id);
                                            }
                                        }
                                    }
                                    $this->saveEventsArray($events, $calendarCurrent, $user, $eventsToUpdate);
                                }
                                //update new sync_token
                                $gCalendar = GoogleCalendar::find($calendarCurrent->id);
                                $gCalendar->sync_token = $calendarCurrent->sync_token;
                                $gCalendar->save();
                            }
                        }
                        //if calendar found in db but not from google, delete from db
                    } else if (!in_array($calendarCurrent->id, $googleCalIds)) {
                        $gCalendar = GoogleCalendar::find($calendarCurrent->id);
                        $gCalendar->delete(); //all events of the calendar delete on cascade.
                    }

                }
            }

            foreach ($eventsToUpdate as $cal_event) {
                //add new event to the calendar
                if (!in_array($cal_event['id'], $eventIdInDb)) {
                    $new_event = new GoogleEvent;
                    $new_event->id = $cal_event['id'];
                    $new_event->user_id = $cal_event['user_id'];
                    $new_event->calendar_id = $cal_event['calendar_id'];
                    $new_event->created = $cal_event['created'];
                    $new_event->updated = $cal_event['updated'];
                    $new_event->summary = $cal_event['summary'];
                    $new_event->start_date = $cal_event['start_date'];
                    $new_event->start_time = $cal_event['start_time'];
                    $new_event->end_date = $cal_event['end_date'];
                    $new_event->end_time = $cal_event['end_time'];
                    $new_event->save();
                    //regular event modification
                } else if (in_array($cal_event['id'], $eventIdInDb)) {
                    if (in_array($cal_event['id'], $cancelledEvents)) {
                        //remove regular modified event from the cancelledEvents array
                        $cancelledEvents = array_diff($cancelledEvents, array($cal_event['id']));
                    }
                    //save the changes to db
                    $eventToUpdate = GoogleEvent::find($cal_event['id']);
                    $eventToUpdate->calendar_id = $cal_event['calendar_id'];
                    $eventToUpdate->created = $cal_event['created'];
                    $eventToUpdate->updated = $cal_event['updated'];
                    $eventToUpdate->summary = $cal_event['summary'];
                    $eventToUpdate->start_date = $cal_event['start_date'];
                    $eventToUpdate->start_time = $cal_event['start_time'];
                    $eventToUpdate->end_date = $cal_event['end_date'];
                    $eventToUpdate->end_time = $cal_event['end_time'];
                    $eventToUpdate->save();
                }
            }
            //remove all actual cancelled events
            foreach ($cancelledEvents as $cancelledEvent) {
                $gEvent = GoogleEvent::find($cancelledEvent);
                if (!empty($gEvent)) {
                    $gEvent->delete();
                }
            }
            $response['message'] = 'Pull events succeed';
        } else {
            $response['message'] = 'Pull events fail';
        }
        return json_encode($response);
    }

    public function pullAllEvents ($id) {
        $user = User::find($id);
        $calendarArray = array();
        $eventsToStore = array();
        $calendarCurrents = DB::table('google_calendar')
            ->where('user_id', '=', $id)
            ->get();

        $eventDataInDb = DB::table('events')
            ->select('id')
            ->where('user_id', '=', $id)
            ->get();

        $startDate = date("Y-m-d");
        $startDate = $startDate.'T00:00:00Z';
        $today = strtotime((date("Y-m-d")));
        $endDate = date("Y-m-d", strtotime("+1 month", $today));
        $endDate = $endDate.'T23:59:59Z';

        //delete all calendars and events belong to user
        if (!empty($calendarCurrents)) {
            if (isset($eventDataInDb)) {
                foreach ($eventDataInDb as $eData) {
                    $eData->delete(); //all events of the calendar
                }
            }
        }

        foreach ($calendarCurrents as $calendar) {
            $events = (array)json_decode(file_get_contents('https://www.googleapis.com/calendar/v3/calendars/' . $calendar->id
                . '/events?singleEvents=true&access_token=' . $user->google_access_token
                . '&timeMin=' . $startDate
                . '&timeMax=' . $endDate));

            if (isset($events['nextPageToken'])) {
                $flag = true;
            } else {
                $flag = false;
            }
            $this->saveEventsArray($events, $calendar, $user, $eventsToStore);

            while ($flag) {
                $events = (array)json_decode(file_get_contents('https://www.googleapis.com/calendar/v3/calendars/' . $calendar->id
                    . '/events?singleEvents=true&access_token=' . $user->google_access_token
                    . '&timeMin=' . $startDate . '&timeMax=' . $endDate
                    . '&pageToken=' . $events['nextPageToken']));
                if (isset($events['nextPageToken'])) {
                    $flag = true;
                } else {
                    $flag = false;
                }
                $this->saveEventsArray($events, $calendar, $user, $eventsToStore);
            }
            array_push($calendarArray, array('calendar_id' => $calendar->id, 'user_id' => $user->id, 'sync_token' => $events['nextSyncToken']));

            if (!empty($events)){
                $syncToken = $events['nextSyncToken'];
                foreach ($calendarCurrents as $calendar) {
                    $calToUpdate = GoogleCalendar::find($calendar->id);
                    $calToUpdate->sync_token = $syncToken;
                    $calToUpdate->save();
                }
            }
        }
        $this->storeEventsInDb ($eventsToStore);
    }

    public function saveEventsArray ($events, $calendar, $user, &$eventsToStore) {
        foreach ($events['items'] as $event) {
            $today = strtotime(date('Y-m-d'));
            //for full day event that doesn't have time
            if (isset($event->start->dateTime)) {
                $eventStart = explode('T', $event->start->dateTime);
                $eventStartTime = substr($eventStart[1], 0, 8);
            } else if (isset($event->start->date)) {
                $eventStart = explode('T', $event->start->date);
                $eventStartTime = '00:00:00';
            } else {
                $eventStart[0] = $today; //TODO: placeholder for deleted event
            }

            if (isset($event->end->dateTime)) {
                $eventEnd = explode('T', $event->end->dateTime);
                $eventEndTime = substr($eventEnd[1], 0, 8);
            } else if (isset($event->end->date)) {
                $eventEnd = explode('T', $event->end->date);
                $eventEndTime = '23:59:59';
            } else {
                $eventStart[0] = $today; //TODO: placeholder for deleted event
            }

            if (strtotime($eventStart[0]) >= $today) {
                array_push($eventsToStore, array(
                    'id' => $event->id,
                    'calendar_id' => $calendar->id,
                    'user_id' => $user->id,
                    'created' => $event->created,
                    'updated' => $event->updated,
                    'summary' => $event->summary,
                    'start_date' => $eventStart[0],
                    'start_time' => $eventStartTime,
                    'end_date' => $eventEnd[0],
                    'end_time' => $eventEndTime));
            }
        }
    }

    public function storeEventsInDb ($eventsToStore) {
        foreach ($eventsToStore as $cal_event) {
            $new_event = new GoogleEvent;
            $new_event->id = $cal_event['id'];
            $new_event->user_id = $cal_event['user_id'];
            $new_event->calendar_id = $cal_event['calendar_id'];
            $new_event->created = $cal_event['created'];
            $new_event->updated = $cal_event['updated'];
            $new_event->summary = $cal_event['summary'];
            $new_event->start_date = $cal_event['start_date'];
            $new_event->start_time = $cal_event['start_time'];
            $new_event->end_date = $cal_event['end_date'];
            $new_event->end_time = $cal_event['end_time'];
            $new_event->save();
        }
    }
}
