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
		if(isset($_POST['google_access_token'])) {

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

	public function getGoogleProfile ($access_token) {
		$request_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
		$profile = file_get_contents($request_url.'?access_token='.$access_token);

		if($profile === false) {
			return false;
		} else {
			$profile = json_decode($profile);
		}

		return $profile;
	}

    //Helper function: Get Google Token Info for expiration time
    public function isValidGoogleToken ($id) {
        $user = User::find($id);

        $request_url = 'https://www.googleapis.com/oauth2/v1/tokeninfo';
        $profile = json_decode(file_get_contents($request_url.'?access_token='.$user->google_access_token));

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
			$user = User::where('email', '=','gyngaiu@gmail.com')->take(1)->get();

			$calendars = (array) json_decode(file_get_contents('https://www.googleapis.com/calendar/v3/users/me/calendarList?access_token='.$user[0]->google_access_token));

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

		$user = User::where('email', '=','gyngaiu@gmail.com')->take(1)->get();

		$response = $this->getCalendars($id);


		$json_output = json_decode($response);
		foreach ( $json_output->calendars as $calendar )
		{
			$events = (array) json_decode(file_get_contents('https://www.googleapis.com/calendar/v3/calendars/37qf0gif7kjcepgssfa0npndhk@group.calendar.google.com/events?access_token='.$user[0]->google_access_token));
		}
		Helpers::pr($events);

		$json_output = json_decode($events);
		foreach ($$json_output['items'] as $item) {
				print ($item->kind);
		}




		//if(Auth::check()) {
			//$id = Auth::user()->id;
			// $calendars = (array) json_decode(file_get_contents('https://www.googleapis.com/calendar/v3/calendars'..'?access_token='.$user->google_access_token));
			// https://www.googleapis.com/calendar/v3/calendars/{calendarId}/events
			//
			// $cal_ids = array();
			// if($calendars !== false) {
			// 	foreach($calendars['items'] as $calendar) {
			// 		if($calendar->accessRole == "owner") {
			// 		  array_push($cal_ids, array('id' => $calendar->id, 'name' => $calendar->summary));
			// 		}
			// 	}
			// }
			//
			// $response['calendars'] = $cal_ids;
		//}

		// header('Content-type: application/json');
		// return json_encode($response);
	}

	public function show($id)
	{

			// $event = new GoogleEvent;
			// $event->start_time = '2014-09-27 21:00:00';
			// $event->end_time = '2014-09-27 21:00:00';
			// $event->kind = '2014-09-27 21:00:00';
			// $event->created = '2014-09-27 21:00:00';
			// $event->updated = '2014-09-27 21:00:00';
			// $event->save();
//		}


		// Israel's code:
		// foreach($calendars['items'] as $calendar) {
		// 	if($calendar->accessRole == "owner") {
		// 		print('id: '.$calendar->id.'</br>');
		// 		print('calendar: '.$calendar->summary.'</br>');
		// 		$events = (array) json_decode(file_get_contents('https://www.googleapis.com/calendar/v3/calendars/'.$calendar->id.'/events?access_token='.$user->google_access_token.'&singleEvents=true'));
		// 		Helpers::pr($events);
		// 	}
		// }
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
