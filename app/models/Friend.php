<?php

class Friend extends Eloquent {

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'friends';
	
	protected $fillable = array('user_id', 'friend_id', 'friend_status');
}