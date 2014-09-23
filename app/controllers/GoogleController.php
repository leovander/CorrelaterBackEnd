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
		header('Content-type: application/json');			
		$response = array();
		
		if(isset($_POST['google_access_token'])) {
			$profile = $this->getGoogleProfile($_POST['google_access_token']);
			
			$users = User::where('email', '=', $profile->email)->take(1)->get();
			if($users->isEmpty()){
				$new_user = new User;
				$new_user->google_access_token = $_POST['google_access_token'];
				$new_user->google_refresh_token = $_POST['google_refresh_token'];
				$new_user->google_id_token = $_POST['google_id_token'];
				$new_user->google_code = $_POST['google_code'];
				$new_user->email = $profile->email;
				$new_user->first_name = $profile->given_name;
				$new_user->last_name = $profile->family_name;
				$new_user->google_id = $profile->id;
				$new_user->save();
				$response['message'] = 'Account Created';
			} else {
				$response['message'] = 'Email Taken';
			}
		}
		
		return json_encode($response);
	}
		
	
	public function getGoogleProfile ($access_token) {
		$request_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
		$profile = file_get_contents($request_url.'?access_token='.$access_token);
		
		if($profile === false) {
			header('Content-type: application/json');
			$response['message'] = 'Unauthorized Google Access';
			return json_encode($response);
		} else {
			$profile = json_decode($profile);
		}
		
		return $profile;
	}
	
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
		}
		
		curl_close($ch);
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
