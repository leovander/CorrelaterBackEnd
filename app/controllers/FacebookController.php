<?php

class FacebookController extends \BaseController
{
	public function create() {
		if(isset($_POST['facebook_token'])) {            
            $user = User::where('email', '=', $_POST['email'])->take(1)->get();
            if ($user->isEmpty()) {
                $new_user = new User;
                $new_user->facebook_token = $_POST['facebook_token'];
                $new_user->facebook_id = $_POST['facebook_id'];
                $new_user->email = $_POST['email'];
                $new_user->password = Hash::make($_POST['email']);
                $new_user->first_name = $_POST['first_name'];
                $new_user->last_name = $_POST['last_name'];
                $new_user->valid = 1;
                $new_user->save();

                $credentials = array(
				  'email' => $_POST['email'],
				  'password' => $_POST['email']
				);

				if(Auth::attempt($credentials, true)) {
				    $response['message'] = 'Account Created';
				} else {
					$response['message'] = 'Could Not Login';
				}		
            } else {
                if($user[0]->valid == 0) {
	            	$new_user = User::find($user[0]->id);
	            	$new_user->facebook_token = $_POST['facebook_token'];
	                $new_user->facebook_id = $_POST['facebook_id'];
	                $new_user->password = Hash::make($_POST['email']);
	                $new_user->first_name = $_POST['first_name'];
	                $new_user->last_name = $_POST['last_name'];
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
        }

        header('Content-type: application/json');
        return json_encode($response);
    }
    
    public function login(){
		if(isset($_POST['facebook_token'])) {
			if(Auth::attempt(array('email' => $_POST['email'], 'password' => $_POST['email']), true))
			{
	            $id = Auth::user()->id;
	            $user = User::find($id);
				
				$user->facebook_id = $_POST['facebook_id'];
	            $user->facebook_token = $_POST['facebook_token'];
	            $user->save();
				
				$response['message'] = 'Logged In';
				$response['user'] = Auth::user();
			} else {
				$response['message'] = 'Email or Password Incorrect';
			}
		}

		header('Content-type: application/json');
		return json_encode($response);
	}
}
