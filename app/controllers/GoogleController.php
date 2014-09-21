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
	public function createWithGoogleAccount()
	{
		//
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
