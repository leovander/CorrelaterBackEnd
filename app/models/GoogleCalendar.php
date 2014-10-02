<?php

class GoogleCalendar extends Eloquent {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'google_calendar';

    protected $fillable = array('id', 'user_id', 'sync_token');

}