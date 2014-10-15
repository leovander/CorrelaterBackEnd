<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		{{ HTML::style('css/colors.css') }}
	</head>
	<body>
		<h1 class="primary">Hi there!</h1>
		<h1 class="primary-1">Hi there!</h1>
		<h1 class="primary-2">Hi there!</h1>
		<h1 class="primary-3">Hi there!</h1>
		<h1 class="primary-4">Hi there!</h1>
		<h1 class="secondary-a">Hi there!</h1>
		<h1 class="secondary-a-1">Hi there!</h1>
		<h1 class="secondary-a-2">Hi there!</h1>
		<h1 class="secondary-a-3">Hi there!</h1>
		<h1 class="secondary-a-4">Hi there!</h1>
		<h1 class="secondary-b">Hi there!</h1>
		<h1 class="secondary-b-1">Hi there!</h1>
		<h1 class="secondary-b-2">Hi there!</h1>
		<h1 class="secondary-b-3">Hi there!</h1>
		<h1 class="secondary-b-4">Hi there!</h1>
		<h1 class="complementary">Hi there!</h1>
		<h1 class="complementary-1">Hi there!</h1>
		<h1 class="complementary-2">Hi there!</h1>
		<h1 class="complementary-3">Hi there!</h1>
		<h1 class="complementary-4">Hi there!</h1>
		<h2>
			{{ $first_name }} (<a href="mailto:{{ $email }}">{{ $email }}</a>) has invited you to <strong>I'm Free</strong>.
		</h2>
		<h2>Get Started:</h2>
		<ol>
			<li>Download I'm Free</li>
		</ol>
		<p>
			Hope to see you soon!
		</p>
		<footer><em>Helping people get together at anytime with less hassle</em></footer>
	</body>
</html>