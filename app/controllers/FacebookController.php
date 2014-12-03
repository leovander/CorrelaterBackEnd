<?php

class FacebookController extends \BaseController
{
	public function create() {
		if(isset($_POST['facebook_token'])) {            
            $user = User::where('email', '=', $_POST['email'])->take(1)->get();
            if ($user->isEmpty()) {
                $new_user = new User;
                $new_user->email = $_POST['email'];
                $new_user->password = Hash::make($_POST['email']);
                $new_user->first_name = $_POST['first_name'];
                $new_user->last_name = $_POST['last_name'];
                $new_user->valid = 1;
                $new_user->save();

                $facebook_user = new FacebookUsers();
                $facebook_user->user_id = $new_user->id;
                $facebook_user->facebook_token = $_POST['facebook_token'];
                $facebook_user->facebook_id = $_POST['facebook_id'];
                $facebook_user->save();

                $credentials = array(
				  'email' => $_POST['email'],
				  'password' => $_POST['email']
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
				} else {
					$response['message'] = 'Could Not Login';
				}		
            } else {
                if($user[0]->valid == 0) {
	            	$new_user = User::find($user[0]->id);
	                $new_user->password = Hash::make($_POST['email']);
	                $new_user->first_name = $_POST['first_name'];
	                $new_user->last_name = $_POST['last_name'];
	                $new_user->valid = 1;
                    $new_user->save();

                    $facebook_user = new FacebookUsers();
                    $facebook_user->user_id = $new_user->id;
                    $facebook_user->facebook_token = $_POST['facebook_token'];
                    $facebook_user->facebook_id = $_POST['facebook_id'];
                    $facebook_user->save();

	                $credentials = array(
					  'email' => $new_user->email,
					  'password' => $new_user->email
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
	            $user = FacebookUsers::find($id);
				
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
	
	public function getAccessToken() {
		$settings = Setting::where('source', '=', 'facebook')->get();
		
		if(Auth::check()) {
			$user = FacebookUsers::find(Auth::user()->id);
			
			
			$isValid = json_decode(file_get_contents('https://graph.facebook.com/debug_token?'.
						'input_token=CAAVCYmVk1zEBAIF8IPc3LEQtnesiOvCNh8KvoK93giztNGrywb0wiGxW3BbY3HY4s8ToCLJLSSGL4CvJcBvZA57XGqZBF88AUeWx6uLYz94zkz32qEmw7OpPDNI0kLZCgTvGQZBmJGMVOPrxZBneA2YZBFy12clqfeEPolJBRCZCZCYNQCmQ4IYjhTOkQiiueRFBpSh6cJjgNbZBfAXiStlwS'.
						'&access_token='.$user->facebook_token));

			if($isValid->data->is_valid == 1) {
				$events = json_decode(file_get_contents('https://graph.facebook.com/v2.1/me?access_token='.$user->facebook_token.'&fields=events'));
			
				Helpers::pr($events->events->data);
			}
		}
	}
}
