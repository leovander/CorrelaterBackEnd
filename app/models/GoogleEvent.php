<?php

class GoogleEvent extends Eloquent {

	public $timestamps = false;

	/**
	* The database table used by the model.
	*
	* @var string
	*/
	protected $table = 'events';

	//protected $fillable = array('start_time', 'end_time', 'kind', 'created', 'updated');

}