<!doctype html>
<html>
	<head>
		<meta charset="utf-8">
	</head>
	
	<body>
		{{ Form::open(['user/getMyInfo'])}}
			<div>
				{{ Form::label('email','Email: ') }}
				{{ Form::email('email') }}
			</div>
			
			<div>
				{{ Form::label('password','Password: ') }}
				{{ Form::password('password')}}
			</div>
			
			<div>
				{{Form::submit('Login')}}
			</div>
		{{ Form::close() }}
	</body>
</html>