<?php
class Helpers {
	public static function pr($object) {
		print('<pre>');
		print_r($object);
		print('</pre>');
	}
}