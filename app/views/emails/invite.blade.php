<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		{{ HTML::style('css/colors.css') }}
	</head>
	<body>
		<h1>Hi there!</h1>
		<h2>
			{{ $first_name }} (<a href="mailto:{{ $email }}">{{ $email }}</a>) has invited you to <strong>Corral</strong>.
		</h2>
		<h2>Get Started:</h2>
		<ol>
			<li>Download Corral</li>
			<li>Import Events</li>
			<li>Start Corraling!</li>
		</ol>
		<p>
			Hope to see you soon!
		</p>
		<footer><em>Helping people get together at anytime with less hassle</em></footer>
	</body>
</html>