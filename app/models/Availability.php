<?php

class Availability extends Eloquent {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'availabilities';

    protected $fillable = array('date', 'start_time', 'end_time', 'status');
}