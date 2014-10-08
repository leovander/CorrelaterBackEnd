<?php

class GoogleEvent extends Eloquent {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'events';

    protected $fillable = array('id', 'start_date', 'start_time', 'end_date', 'end_time', 'summary', 'created', 'updated');

}