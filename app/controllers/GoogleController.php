<?php

class GoogleController extends \BaseController
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        //
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function createWithGoogleAccount()
    {
        if (isset($_POST['google_access_token'])) {

            $profile = $this->getGoogleProfile($_POST['google_access_token']);

            if ($profile !== false) {
                $users = User::where('email', '=', $profile->email)->take(1)->get();
                if ($users->isEmpty()) {
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

                    if (Auth::attempt($credentials, true)) {
                        $response['message'] = 'Account Created';
                    }
                } else {
                    $response['message'] = 'Email Taken';
                }
            } else {
                $response['message'] = 'Profile not created';
            }
        }

        header('Content-type: application/json');
        return json_encode($response);
    }

    public function getGoogleProfile($access_token)
    {
        $request_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
        $profile = file_get_contents($request_url . '?access_token=' . $access_token);

        if ($profile === false) {
            return false;
        } else {
            $profile = json_decode($profile);
        }

        return $profile;
    }

    //Helper function: Get Google Token Info for expiration time
    public function isValidGoogleToken($id)
    {
        $user = User::find($id);

        $request_url = 'https://www.googleapis.com/oauth2/v1/tokeninfo';
        $profile = json_decode(file_get_contents($request_url . '?access_token=' . $user->google_access_token));

        if ($profile === false) {
            return false;
        } else {
            //refresh the token if expiration time is less than 10 mins (600 secs)
            if ((int)$profile->expires_in < 600) {
                if ($this->refreshGoogleAccessToken($id)) {
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
    public function refreshGoogleAccessToken($id)
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

        if (curl_getinfo($ch)['http_code'] == '200') {
            $user->google_access_token = $response->access_token;
            $user->google_id_token = $response->id_token;
            $user->save();
            curl_close($ch);
            return true;
        } else {
            curl_close($ch);
            return false;
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store()
    {
        //
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

    public function getCalendars($id)
    {
        //if(Auth::check()) {
        //$id = Auth::user()->id;
        $user = User::find($id);

        //check to make sure the user access token is still valid
        $ch =  curl_init('https://www.googleapis.com/calendar/v3/users/me/calendarList?access_token=' . $user->google_access_token.'1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $calendars = curl_exec($ch);
        $calendars = json_decode($calendars);

        //if the user access token is not valid - 401 "Invalid Credentials" refresh the token
        if ($calendars->error->code == '401') {
            print("I'm here <br/>");
            $this->refreshGoogleAccessToken($id);
            $calendars = (array)json_decode(file_get_contents('https://www.googleapis.com/calendar/v3/users/me/calendarList?access_token=' . $user->google_access_token));
        } else {
            $calendars = (array)json_decode(file_get_contents('https://www.googleapis.com/calendar/v3/users/me/calendarList?access_token=' . $user->google_access_token));
        }

        $cal_ids = array();
        if ($calendars !== false) {
            foreach ($calendars['items'] as $calendar) {
                if ($calendar->accessRole == "owner") {
                    array_push($cal_ids, array('id' => $calendar->id, 'name' => $calendar->summary));
                }
            }
        }

        $response['calendars'] = $cal_ids;

        //header('Content-type: application/json');
        return json_encode($response);
    }

    public function pullEvents($id)
    {
        $user = User::find($id);
        $eventInDb = array();
        $cal_events = array();
        $calendarArray = array();
        $cancelledEvents = array();

        $eventData = DB::table('events')
            ->select('id')
            ->where('user_id', '=', $id)
            ->get();
        $calendarCurrents = DB::table('google_calendar')
            ->where('user_id', '=', $id)
            ->get();

        //push only the calendar id into an array.
        $calIds = array();
        foreach ($calendarCurrents as $calendarCurrent) {
            array_push($calIds, $calendarCurrent->id);
        }

        //push only the events id into an array
        foreach ($eventData as $eData) {
            array_push($eventInDb, $eData->id);
        }

        $calendars = $this->getCalendars($id);
        $json_calendars = json_decode($calendars);

        //push only the calendar id
        $jCal = array();
        foreach ($json_calendars->calendars as $cal) {
            array_push($jCal, $cal->id);
        }

        //compare google return value against db
        foreach ($json_calendars->calendars as $calendar) {
            if (!in_array($calendar->id, $calIds)) { //check to see if there's new calendar
                $events = (array)json_decode(file_get_contents('https://www.googleapis.com/calendar/v3/calendars/' . $calendar->id . '/events?singleEvents=true&access_token=' . $user->google_access_token));

                array_push($calendarArray, array('calendar_id' => $calendar->id, 'user_id' => $user->id, 'sync_token' => $events['nextSyncToken']));

                $responseCode = array();
                preg_match('#HTTP/\d+\.\d+ (\d+)#', $http_response_header[0], $responseCode);
                if ($responseCode[1] === '410') {
                    pullAllEvents($id);  //sync_token expire, full synchronization
                } else {
                    if (!empty($events)) {
                        //while (isset($events['nextPageToken'])) {
                        foreach ($events['items'] as $event) {
                            $today = strtotime(date('Y-m-d'));
                            $eventStart = explode('T', $event->start->dateTime);
                            $eventStartTime = substr($eventStart[1], 0, 8);
                            $eventEnd = explode('T', $event->end->dateTime);
                            $eventEndTime = substr($eventEnd[1], 0, 8);

                            if (strtotime($eventStart[0]) >= $today) {
                                if (!in_array($event->id, $eventInDb)) {
                                    array_push($cal_events, array(
                                        'id' => $event->id,
                                        'calendar_id' => $calendar->id,
                                        'user_id' => $user->id,
                                        'syncToken' => $events['nextSyncToken'],
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
                        //}
                        //save calendar sync_token
                        foreach ($calendarArray as $cal) {
                            $new_calendar = new GoogleCalendar;
                            $new_calendar->id = $cal['calendar_id'];
                            $new_calendar->user_id = $cal['user_id'];
                            $new_calendar->sync_token = $cal['sync_token'];
                            $new_calendar->save();
                        }
                        $calendarArray = array(); //reset the array

                        foreach ($cal_events as $cal_event) {
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
                        $cal_events = array(); //reset the array
                    }
                }
            }
        }


        //compare db against google return value
        foreach ($calendarCurrents as $calendarCurrent) {
            // if calendar in both google return and db, check for new events in existing calendar(s)
            if (in_array($calendarCurrent->id, $jCal)) {
                $events = (array)json_decode(file_get_contents('https://www.googleapis.com/calendar/v3/calendars/' . $calendarCurrent->id . '/events?singleEvents=true&syncToken=' . $calendarCurrent->sync_token . '&access_token=' . $user->google_access_token));

                if (!empty($events)) {
                    //while (isset($events['nextPageToken'])) {
                    foreach ($events['items'] as $event) {
                        //an array of cancelled Event to check for moved events vs. really cancelled event
                        if ($event->status === "cancelled") {
                            array_push($cancelledEvents, $event->id);
                        } else {

                            $today = strtotime(date('Y-m-d'));
                            $eventStart = explode('T', $event->start->dateTime);
                            $eventStartTime = substr($eventStart[1], 0, 8);
                            $eventEnd = explode('T', $event->end->dateTime);
                            $eventEndTime = substr($eventEnd[1], 0, 8);

                            if (strtotime($eventStart[0]) >= $today) {
                                array_push($cal_events, array(
                                    'id' => $event->id,
                                    'calendar_id' => $event->organizer->email,
                                    'user_id' => $user->id,
                                    'syncToken' => $events['nextSyncToken'],
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
                }

            //if calendar found in db but not from google, delete from db
            } else if (!in_array($calendarCurrent->id, $jCal)) {
                $gCalendar = GoogleCalendar::find($calendarCurrent->id);
                $gCalendar->delete(); //all events of the calendar delete on cascade.
            }

            //update new sync_token
            $gCalendar = GoogleCalendar::find($calendarCurrent->id);
            $gCalendar->sync_token = $events['nextSyncToken'];
            $gCalendar->save();
        }

        foreach ($cal_events as $cal_event) {
            //add new event to the calendar
            if (!in_array($cal_event['id'], $eventInDb)) {
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
            } else if (in_array($cal_event['id'], $eventInDb)) {
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
        foreach($cancelledEvents as $cancelledEvent) {
            $gEvent = GoogleEvent::find($cancelledEvent);
            if (!empty($gEvent)) {
                $gEvent->delete();
            }
        }
    }

    public function pullAllEvents ($id) {
        $user = User::find($id);
        $calendarCurrents = DB::table('google_calendar')
            ->where('user_id', '=', $id)
            ->get();

        $calendars = $this->getCalendars($id);
        $json_calendars = json_decode($calendars);

        //delete all calendars and events belong to user
        foreach ($calendarCurrents as $calendarCurrent) {
            $gCalendar = GoogleCalendar::find($calendarCurrent->id);
            $gCalendar->delete(); //all events of the calendar delete on cascade.
        }

        foreach ($json_calendars->calendars as $calendar) {
            $events = (array)json_decode(file_get_contents('https://www.googleapis.com/calendar/v3/calendars/' . $calendar->id . '/events?singleEvents=true&access_token=' . $user->google_access_token));
            array_push($calendarArray, array('calendar_id' => $calendar->id, 'user_id' => $user->id, 'sync_token' => $events['nextSyncToken']));

            if (!empty($events)){
                //while (isset($events['nextPageToken'])) {
                foreach ($events['items'] as $event) {
                    $today = strtotime(date('Y-m-d'));
                    $eventStart = explode('T', $event->start->dateTime);
                    $eventStartTime = substr($eventStart[1], 0, 8);
                    $eventEnd = explode('T', $event->end->dateTime);
                    $eventEndTime = substr($eventEnd[1], 0, 8);

                    if (strtotime($eventStart[0]) >= $today) {
                        array_push($cal_events, array(
                            'id' => $event->id,
                            'calendar_id' => $calendar->id,
                            'user_id' => $user->id,
                            'syncToken' => $events['nextSyncToken'],
                            'created' => $event->created,
                            'updated' => $event->updated,
                            'summary' => $event->summary,
                            'start_date' => $eventStart[0],
                            'start_time' => $eventStartTime,
                            'end_date' => $eventEnd[0],
                            'end_time' => $eventEndTime));
                    }
                }
                //}
                //save calendar sync_token
                foreach ($calendarArray as $cal) {
                    $new_calendar = new GoogleCalendar;
                    $new_calendar->id = $cal['calendar_id'];
                    $new_calendar->user_id = $cal['user_id'];
                    $new_calendar->sync_token = $cal['sync_token'];
                    $new_calendar->save();
                }
                $calendarArray = array(); //reset the array

                foreach ($cal_events as $cal_event) {
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
                $cal_events = array(); //reset the array
            }
        }
    }

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
