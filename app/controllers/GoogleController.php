<?php

class GoogleController extends \BaseController {

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
	public function createWithGoogleAccount() {
		$response = array();
		if(isset($_POST['google_access_token'])) {
			$profile = $this->getGoogleProfile($_POST['google_access_token']);

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
                    $response['message'] = 'Email Taken';
                }
            } else {
                $response['message'] = 'Profile not created';
            }
		}

		header('Content-type: application/json');
		return json_encode($response);
	}

	public function getGoogleProfile($access_token) {
		$request_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
		$profile = file_get_contents($request_url.'?access_token='.$access_token);

		if($profile === false) {
			return false;
		} else {
			$profile = json_decode($profile);
		}

		return $profile;
	}

    //Helper function: Refresh Google Access Token when the old Access Token expired
	public function refreshGoogleAccessToken($id) {
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

		if(curl_getinfo($ch)['http_code'] == '200') {
			$user->google_access_token = $response->access_token;
			$user->google_id_token = $response->id_token;
			$user->save();
            curl_close($ch);
            print('refreshed token');
		} else {
            curl_close($ch);
            print('invalid token');
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


	function get_my_url_contents($url){
		$crl = curl_init();
		$timeout = 5;
		curl_setopt ($crl, CURLOPT_URL,$url);
		curl_setopt ($crl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
		$ret = curl_exec($crl);
		curl_close($crl);
		return $ret;
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */

	public function getCalendars($id)
	{
		//if(Auth::check()) {
			//$id = Auth::user()->id;
            $user = User::find($id);

			$calendars = (array) json_decode(file_get_contents('https://www.googleapis.com/calendar/v3/users/me/calendarList?access_token='.$user->google_access_token));

			$cal_ids = array();
			if($calendars !== false) {
				foreach($calendars['items'] as $calendar) {
					if($calendar->accessRole == "owner") {
					  array_push($cal_ids, array('id' => $calendar->id, 'name' => $calendar->summary));
					}
				}
			}

			$response['calendars'] = $cal_ids;
		//}

		//header('Content-type: application/json');
		return json_encode($response);
	}

	public function pullEvents($id)
	{
		$user = User::find($id);
        $eventArrays = array();
        $cal_events = array();
        $calendarIds = array();
        $myObjArray = array();
        $eventIds = DB::table('events')
                            ->select('id')
                            ->where('user_id', '=', $id)
                            ->get();
        $calendarCurrents = DB::table('google_calendar')
                            ->where('user_id', '=', $id)
                            ->get();

        //convert to an array of event id
        foreach ($eventIds as $eventId) {
            array_push($eventArrays, $eventId->id);
        }

		$calendars = $this->getCalendars($id);
		$json_calendars = json_decode($calendars);

        if(!empty($calendarCurrents) && !empty($json_calendars)) {
            foreach ($calendarCurrents as $calendarCurrent) {
                foreach ($json_calendars->calendars as $calendar) {
                    $events = (array)json_decode(file_get_contents('https://www.googleapis.com/calendar/v3/calendars/' . $calendar->id . '/events?singleEvents=true&syncToken=' . $calendarCurrent->sync_token . '&access_token=' . $user->google_access_token));
                    //array_push($myObjArray, array('calendar_id' => $calendar->id, 'user_id' => $user->id, 'sync_token' => $events['nextSyncToken']));

                    Helpers::pr($events);

//                    foreach ($events['items'] as $event) {
////                        if ($events['nextSyncToken'] == $cal_events[$i]['syncToken']) {
//                        if (!array_key_exists($event->id, $eventArrays)) {
//                            array_push($cal_events, array(
//                                'id' => $event->id,
//                                'calendar_id' => $calendar->id,
//                                'user_id' => $user->id,
//                                //'syncToken' => $events['nextSyncToken'],
//                                'created' => $event->created,
//                                'updated' => $event->updated,
//                                'summary' => $event->summary,
//                                'start_time' => $event->start->dateTime,
//                                'end_time' => $event->end->dateTime));
//                        }
//                    }

                    //overwrite the old token
//                    $calendarCurrent->sync_token = $events['nextSyncToken'];
//                    $calendarCurrent->save();
//                    Helpers::pr($events);
                }

            }

        } else { //first time pulling user events
            if (!empty($json_calendars)) {
                foreach ($json_calendars->calendars as $calendar) {
                    $events = (array)json_decode(file_get_contents('https://www.googleapis.com/calendar/v3/calendars/' . $calendar->id . '/events?singleEvents=true&access_token=' . $user->google_access_token));
                    array_push($myObjArray, array('calendar_id' => $calendar->id, 'user_id' => $user->id, 'sync_token' => $events['nextSyncToken']));

                    $i = 0;
                    //$cal_events[$calendar->id] = array();

                    //while (isset($events['nextPageToken'])) {
                    foreach ($events['items'] as $event) {
//                        if ($events['nextSyncToken'] == $cal_events[$i]['syncToken']) {
                            if (!in_array($event->id, $eventArrays)) {
                                array_push($cal_events, array(
                                                              'id' => $event->id,
                                                     'calendar_id' => $calendar->id,
                                                         'user_id' => $user->id,
                                                       'syncToken' => $events['nextSyncToken'],
                                                         'created' => $event->created,
                                                         'updated' => $event->updated,
                                                         'summary' => $event->summary,
                                                      'start_time' => $event->start->dateTime,
                                                        'end_time' => $event->end->dateTime));
                            }
//                        }
                        $i++;
                    }
                    //}
                }

                foreach ($myObjArray as $event) {
                    $new_calendar = new GoogleCalendar;
                    $new_calendar->id = $event['calendar_id'];
                    $new_calendar->user_id = $event['user_id'];
                    $new_calendar->sync_token = $event['sync_token'];
                    $new_calendar->save();
                }

                foreach ($cal_events as $event) {
                    $new_event = new GoogleEvent;
                    $new_event->id = $event['id'];
                    $new_event->user_id = $event['user_id'];
                    $new_event->calendar_id = $event['calendar_id'];
                    $new_event->created = $event['created'];
                    $new_event->updated = $event['updated'];
                    $new_event->summary = $event['summary'];
                    $new_event->start_time = $event['start_time'];
                    $new_event->end_time = $event['end_time'];
                    $new_event->save();
                }
            }
        }




//        } else {
//            $response['message'] = "Could Not Pull Calendars";
//        }

//        print("EVENTS <br/>");
//        Helpers::pr($events);`
//        print("EVENTS_TO_PUSH");
//        Helpers::pr($eventsToPush);
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
